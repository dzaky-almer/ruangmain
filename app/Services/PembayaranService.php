<?php

// File ini berfungsi seperti bendahara: semua perhitungan uang ada di sini.
// Konsep penting: total yang sudah dibayar TIDAK disimpan di kolom tersendiri.
//        Jadi tidak ada risiko datanya berbeda atau tertinggal. Setiap dibutuhkan, dihitung ulang:
//        SUM(jumlah) dari pembayaran yang statusnya 'sukses' saja.
//        Yang masih pending/gagal/dibatalkan tidak pernah dihitung.
// Dipanggil oleh: PembayaranController, PembayaranCallbackController,
//                 AdminPembayaranController, dan CheckinService.
// Menulis ke tabel: `pembayaran`. Berkomunikasi ke gateway lewat PaymentGateway.

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\DetailReservasi;
use App\Models\Pembayaran;
use App\Models\Reservasi;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PembayaranService
{
    /** Kalau memilih DP, jumlahnya 50% dari total reservasi. */
    public const PERSEN_DP = 0.5;

        // Status reservasi yang dianggap gagal kalau jadwalnya sudah lewat.
        public const STATUS_LEWAT_JADWAL = ['menunggu_pembayaran', 'dikonfirmasi', 'check_in'];

    // PaymentGateway masuk otomatis (isinya MockQrisGateway atau MidtransGateway,
    // tergantung isi .env — lihat AppServiceProvider).
    public function __construct(private readonly PaymentGateway $gateway) {}

        // Menghitung total yang sudah masuk. Diambil dari relasi pembayaran() milik Reservasi.
    public function totalDibayar(Reservasi $reservasi): float
    {
        return (float) $reservasi->pembayaran()
            ->where('status_pembayaran', 'sukses')
            ->sum('jumlah');
    }

        // Sisa tagihan = total dikurangi yang sudah dibayar. max(0, ...) supaya tidak minus.
    public function sisaTagihan(Reservasi $reservasi): float
    {
        return max(0, (float) $reservasi->total_reservasi - $this->totalDibayar($reservasi));
    }

        // Sudah lunas atau belum?
    public function lunas(Reservasi $reservasi): bool
    {
        return $this->totalDibayar($reservasi) >= (float) $reservasi->total_reservasi;
    }

        // Membungkus 3 perhitungan di atas menjadi 1 array agar controller mudah memakainya.
    /**
     * @return array{sudahDibayar: float, sisaTagihan: float, lunas: bool}
     */
    public function ringkasan(Reservasi $reservasi): array
    {
        $sudahDibayar = $this->totalDibayar($reservasi);

        return [
            'sudahDibayar' => $sudahDibayar,
            'sisaTagihan' => max(0, (float) $reservasi->total_reservasi - $sudahDibayar),
            'lunas' => $sudahDibayar >= (float) $reservasi->total_reservasi,
        ];
    }

    /**
         * Harus membayar berapa kalau jenisnya 'dp' atau 'pelunasan'?
     */
    public function hitungJumlah(Reservasi $reservasi, string $jenis): float
    {
        $total = (float) $reservasi->total_reservasi;
        $sudahDibayar = $this->totalDibayar($reservasi);

            // DP = separuh dari total.
        if ($jenis === 'dp') {
            return round($total * self::PERSEN_DP, 2);
        }

            // Pelunasan = sisa tagihan. Kalau belum membayar apa pun, berarti bayar penuh.
        return $sudahDibayar > 0
            ? max(0, round($total - $sudahDibayar, 2))
            : round($total, 2);
    }

    /**
         * Pilihan pembayaran yang masuk akal untuk reservasi ini.
         * Belum membayar apa pun → boleh DP atau langsung lunas.
         * Sudah DP → hanya boleh pelunasan sisanya.
         * Sudah lunas → tidak ada pilihan.
     *
     * @return array<int, array{jenis: string, label: string, jumlah: float}>
     */
    public function opsi(Reservasi $reservasi): array
    {
        if ($this->lunas($reservasi)) {
            return [];
        }

        if ($this->totalDibayar($reservasi) > 0) {
            return [[
                'jenis' => 'pelunasan',
                'label' => 'Pelunasan sisa tagihan',
                'jumlah' => $this->hitungJumlah($reservasi, 'pelunasan'),
            ]];
        }

        return [
            ['jenis' => 'dp', 'label' => 'DP 50%', 'jumlah' => $this->hitungJumlah($reservasi, 'dp')],
            ['jenis' => 'pelunasan', 'label' => 'Bayar lunas', 'jumlah' => $this->hitungJumlah($reservasi, 'pelunasan')],
        ];
    }

    // Mencari pembayaran yang masih pending (belum selesai). Ini dipakai untuk
        // mencegah pengguna membuat 2 pembayaran pending sekaligus.
    public function pendingAktif(Reservasi $reservasi): ?Pembayaran
    {
        return $reservasi->pembayaran()->where('status_pembayaran', 'pending')->latest('id_pembayaran')->first();
    }

    /**
         * Membuat baris pembayaran baru dengan status 'pending'.
         * Aturannya: 1 reservasi hanya boleh punya 1 pembayaran pending.
     */
    public function buat(Reservasi $reservasi, string $jenis, string $metode): Pembayaran
    {
            // Validasi sederhana: jenis dan metode harus salah satu yang dikenal.
        if (! in_array($jenis, ['dp', 'pelunasan'], true)) {
            throw ValidationException::withMessages(['jenis_pembayaran' => 'Jenis pembayaran tidak dikenal.']);
        }

        if (! in_array($metode, ['qris', 'cash'], true)) {
            throw ValidationException::withMessages(['metode_pembayaran' => 'Metode pembayaran tidak dikenal.']);
        }

        return DB::transaction(function () use ($reservasi, $jenis, $metode): Pembayaran {
                // Mengunci baris reservasinya dulu supaya tidak ada 2 pemrosesan bersamaan.
            $terkunci = Reservasi::query()->whereKey($reservasi->id_reservasi)->lockForUpdate()->firstOrFail();

                // Reservasi yang sudah dibatalkan atau selesai tidak bisa dibayar lagi.
            if (! in_array($terkunci->status_reservasi, ['menunggu_pembayaran', 'dikonfirmasi'], true)) {
                throw ValidationException::withMessages([
                    'jenis_pembayaran' => "Reservasi {$terkunci->kode_booking} berstatus {$terkunci->status_reservasi} sehingga tidak dapat dibayar lagi.",
                ]);
            }

            // Masih ada pembayaran pending? Selesaikan dulu yang itu.
            if ($this->pendingAktif($terkunci) !== null) {
                throw ValidationException::withMessages([
                    'jenis_pembayaran' => 'Masih ada pembayaran yang menunggu penyelesaian. Selesaikan atau batalkan dulu sebelum membuat yang baru.',
                ]);
            }

            $jumlah = $this->hitungJumlah($terkunci, $jenis);

                        // Tidak ada yang perlu dibayar? Berarti tidak perlu membuat baris baru.
            if ($jumlah <= 0) {
                throw ValidationException::withMessages(['jenis_pembayaran' => 'Tidak ada sisa tagihan yang perlu dibayar.']);
            }

                        // Menyimpan baris pembayarannya.
            $pembayaran = $terkunci->pembayaran()->create([
                'jenis_pembayaran' => $jenis,
                'metode_pembayaran' => $metode,
                'jumlah' => $jumlah,
                'status_pembayaran' => 'pending',
            ]);

                        // Kalau QRIS, minta id transaksi ke gateway. Kalau cash, tidak perlu.
            if ($metode === 'qris') {
                $pembayaran->update(['id_transaksi_gateway' => $this->gateway->buatTransaksi($pembayaran)]);
            }

            return $pembayaran->refresh();
        });
    }

    /**
                 * Mengubah status pembayaran dari pending menjadi sukses.
                 * QRIS: kolom diverifikasi_oleh dibiarkan kosong (sistem yang memverifikasi).
                 * Cash: diisi id admin yang mencatat.
                 * Sekaligus: kalau reservasinya masih 'menunggu_pembayaran' dan sudah ada
                 * pembayaran masuk, statusnya diubah menjadi 'dikonfirmasi'.
     */
    public function tandaiSukses(Pembayaran $pembayaran, ?int $idAdmin = null): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran, $idAdmin): Pembayaran {
            $terkunci = Pembayaran::query()->whereKey($pembayaran->id_pembayaran)->lockForUpdate()->firstOrFail();

                        // Kalau statusnya bukan pending, berarti sudah pernah diproses. Cukup kembalikan data itu
                        // (inilah yang membuat callback dobel tidak menjadi masalah).
            if ($terkunci->status_pembayaran !== 'pending') {
                return $terkunci;
            }

            $reservasi = Reservasi::query()->whereKey($terkunci->id_reservasi)->lockForUpdate()->firstOrFail();

            $terkunci->update([
                'status_pembayaran' => 'sukses',
                'waktu_bayar' => now(),
                'diverifikasi_oleh' => $idAdmin,
                            // Kalau berasal dari QRIS (idAdmin null), waktu verifikasi dibiarkan kosong.
                'waktu_verifikasi' => $idAdmin === null ? null : now(),
            ]);

                        // Menaikkan status reservasi kalau perlu.
            if ($reservasi->status_reservasi === 'menunggu_pembayaran' && $this->totalDibayar($reservasi) > 0) {
                $reservasi->update(['status_reservasi' => 'dikonfirmasi']);
            }

            return $terkunci->refresh();
        });
    }

                // Mengubah status dari pending menjadi gagal. Yang sudah sukses tidak diubah.
    public function tandaiGagal(Pembayaran $pembayaran): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran): Pembayaran {
            $terkunci = Pembayaran::query()->whereKey($pembayaran->id_pembayaran)->lockForUpdate()->firstOrFail();

            if ($terkunci->status_pembayaran === 'pending') {
                $terkunci->update(['status_pembayaran' => 'gagal']);
            }

            return $terkunci->refresh();
        });
    }

    /**
         * Menerima notifikasi (webhook) dari gateway pembayaran.
         * Dipanggil oleh PembayaranCallbackController.
     * Aturan mainnya:
         *  - tanda tangan wajib sah, kalau tidak maka ditolak;
         *  - hanya baris dengan id_transaksi_gateway yang cocok DAN masih pending
         *    yang diproses → jadi callback dobel tidak menyebabkan proses dobel;
         *  - status sukses/gagal/dibatalkan bersifat final, tidak bisa diubah lagi.
     *
     * @param  array<string, mixed>  $payload
     */
    public function terimaCallback(array $payload): ?Pembayaran
    {
        Log::info('Callback gateway QRIS diterima.', [
            'id_transaksi' => $payload['id_transaksi'] ?? null,
            'status' => $payload['status'] ?? null,
        ]);

        $idTransaksi = (string) ($payload['id_transaksi'] ?? '');
        $status = (string) ($payload['status'] ?? '');
        $tandaTangan = (string) ($payload['tanda_tangan'] ?? '');

        if (! in_array($status, ['sukses', 'gagal'], true)) {
            throw ValidationException::withMessages(['status' => 'Status callback tidak dikenal.']);
        }

        // Ini gerbang keamanannya. Tanda tangan palsu berarti ditolak dan dicatat di log.
        if (! $this->gateway->tandaTanganSah($idTransaksi, $status, $tandaTangan)) {
            Log::warning('Callback gateway dengan tanda tangan tidak sah ditolak.', ['id_transaksi' => $idTransaksi]);

            throw ValidationException::withMessages(['tanda_tangan' => 'Tanda tangan callback tidak sah.']);
        }

        $pembayaran = Pembayaran::query()
            ->where('id_transaksi_gateway', $idTransaksi)
            ->where('status_pembayaran', 'pending')
            ->first();

        // Tidak ketemu atau sudah bukan pending → tidak ada yang perlu dilakukan.
        if ($pembayaran === null) {
            return null;
        }

        return $status === 'sukses'
            ? $this->tandaiSukses($pembayaran)
            : $this->tandaiGagal($pembayaran);
    }

    /**
         * Membatalkan otomatis reservasi yang melewati batas bayar.
         * Reservasi yang sudah dikonfirmasi tidak disentuh.
     * Ini dipanggil scheduler tiap menit lewat command
     * `php artisan reservasi:batalkan-kedaluwarsa`.
         * Nilai baliknya = jumlah reservasi yang dibatalkan.
     */
    public function batalkanKedaluwarsa(): int
    {
            // Mengambil id-nya saja dulu (lebih ringan), lalu diproses satu per satu.
        $id = Reservasi::query()
            ->where('status_reservasi', 'menunggu_pembayaran')
            ->where('batas_pembayaran', '<', now())
                // Reservasi yang sudah punya pembayaran sukses tidak boleh dibatalkan.
            ->whereDoesntHave('pembayaran', fn ($query) => $query->where('status_pembayaran', 'sukses'))
            ->pluck('id_reservasi');

        $jumlah = 0;

        foreach ($id as $idReservasi) {
            DB::transaction(function () use ($idReservasi, &$jumlah): void {
                $reservasi = Reservasi::query()->whereKey($idReservasi)->lockForUpdate()->first();

                // Dicek ulang setelah dikunci: mungkin statusnya sudah berubah.
                if ($reservasi === null || $reservasi->status_reservasi !== 'menunggu_pembayaran') {
                    return;
                }

                // Dicek ulang juga batas waktunya.
                if ($reservasi->batas_pembayaran?->isFuture() !== false) {
                    return;
                }

                // Terakhir, pastikan lagi tidak ada pembayaran sukses.
                if ($reservasi->pembayaran()->where('status_pembayaran', 'sukses')->exists()) {
                    return;
                }

                $reservasi->update(['status_reservasi' => 'dibatalkan']);
                // Pembayaran pending-nya sekalian ikut dibatalkan.
                $reservasi->pembayaran()->where('status_pembayaran', 'pending')->update(['status_pembayaran' => 'dibatalkan']);
                $jumlah++;
            });
        }

        return $jumlah;
    }

    /**
     * Membatalkan reservasi yang jadwalnya sudah lewat (jam selesai room terakhir).
     * Berlaku untuk status menunggu_pembayaran, dikonfirmasi, dan check_in.
     * Uang yang sudah masuk (pembayaran 'sukses') tidak dikembalikan, hanya
     * pembayaran yang masih 'pending' yang ikut dibatalkan.
     * Nilai baliknya = jumlah reservasi yang dibatalkan.
     */
    public function batalkanLewatJadwal(): int
    {
        // Ambil id-nya dulu. Reservasi yang tanggalnya hari ini atau sebelumnya
        // masih mungkin lewat, jadi ikut diperiksa satu per satu di bawah.
        $id = Reservasi::query()
            ->whereIn('status_reservasi', self::STATUS_LEWAT_JADWAL)
            ->whereHas('detailReservasi', fn ($query) => $query->whereDate('tanggal', '<=', now()->toDateString()))
            ->pluck('id_reservasi');

        $jumlah = 0;

        foreach ($id as $idReservasi) {
            DB::transaction(function () use ($idReservasi, &$jumlah): void {
                $reservasi = Reservasi::query()->whereKey($idReservasi)->lockForUpdate()->first();

                // Dicek ulang setelah dikunci: mungkin admin baru mengubah statusnya.
                if ($reservasi === null || ! in_array($reservasi->status_reservasi, self::STATUS_LEWAT_JADWAL, true)) {
                    return;
                }

                $selesaiTerakhir = $this->jadwalTerakhir($reservasi);

                // Belum lewat, atau tidak punya baris room sama sekali, jadi jangan disentuh.
                if ($selesaiTerakhir === null || $selesaiTerakhir->isFuture()) {
                    return;
                }

                $reservasi->update(['status_reservasi' => 'dibatalkan']);
                $reservasi->pembayaran()->where('status_pembayaran', 'pending')->update(['status_pembayaran' => 'dibatalkan']);

                // Reservasi yang sudah check-in menahan room berstatus 'digunakan'.
                // Karena dibatalkan, room-nya harus dilepas lagi supaya bisa dipesan.
                $idRoom = $reservasi->detailReservasi()->pluck('id_room');
                Room::query()
                    ->whereIn('id_room', $idRoom)
                    ->where('status_room', 'digunakan')
                    ->update(['status_room' => 'tersedia']);

                $jumlah++;
            });
        }

        return $jumlah;
    }

    /**
     * Jam selesai paling akhir dari semua room di satu reservasi.
     * Reservasi baru dianggap lewat kalau semua room-nya sudah selesai.
     * Null = reservasi tanpa baris room.
     */
    private function jadwalTerakhir(Reservasi $reservasi): ?CarbonInterface
    {
        return $reservasi->detailReservasi()->get()
            ->map(fn (DetailReservasi $detail) => $detail->tanggal->copy()->setTimeFromTimeString((string) $detail->jam_selesai))
            ->max();
    }
}

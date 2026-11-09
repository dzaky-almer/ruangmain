<?php

// File ini berfungsi sebagai penyusun laporan. Semua angka di dashboard admin dan
// halaman laporan dihitung di sini.
// Aturan utamanya: pendapatan = SUM(jumlah) dari pembayaran yang statusnya 'sukses' SAJA.
//        Tidak ada kolom "total pendapatan" yang disimpan di database, jadi
//        angkanya selalu mengikuti data terbaru dan tidak mungkin berbeda.
// Dipanggil oleh: DashboardController dan LaporanController.
// Membaca tabel: `reservasi`, `detail_reservasi`, `pembayaran`, `room`, dan `pengguna`.

namespace App\Services;

use App\Models\DetailReservasi;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LaporanService
{
    /** Pilihan periode laporan pendapatan yang sah. Dipakai untuk validasi filter. */
    public const PERIODE = ['harian', 'mingguan', 'bulanan', 'rentang'];

        /** Pilihan status reservasi yang sah untuk filter laporan. */
    public const STATUS_RESERVASI = ['menunggu_pembayaran', 'dikonfirmasi', 'check_in', 'selesai', 'dibatalkan'];

    public const STATUS_PEMBAYARAN = ['pending', 'sukses', 'gagal', 'dibatalkan'];

    public const METODE_PEMBAYARAN = ['qris', 'cash'];

    public const JENIS_PEMBAYARAN = ['dp', 'pelunasan'];

    // ---------------------------------------------------------------------
    // Dashboard Admin
    // ---------------------------------------------------------------------

    /**
         * Angka-angka pada kartu di halaman dashboard admin.
     *
     * @return array{reservasiHariIni: int, reservasiMendatang: int, roomTersedia: int,
     *               roomDigunakan: int, jumlahPelanggan: int, pendapatanHariIni: float}
     */
    public function ringkasanDashboard(): array
    {
        $hariIni = Carbon::today();
        $besok = $hariIni->copy()->addDay();

        return [
                // Reservasi yang jadwal mainnya hari ini, yang dibatalkan tidak dihitung.
            'reservasiHariIni' => $this->queryReservasiPadaTanggal('=', $hariIni)
                ->where('status_reservasi', '!=', 'dibatalkan')
                ->count(),
                // Reservasi besok dan seterusnya yang masih berjalan (belum selesai atau batal).
            'reservasiMendatang' => $this->queryReservasiPadaTanggal('>=', $besok)
                ->whereIn('status_reservasi', ['menunggu_pembayaran', 'dikonfirmasi'])
                ->count(),
            'roomTersedia' => Room::query()->where('status_room', 'tersedia')->count(),
            'roomDigunakan' => Room::query()->where('status_room', 'digunakan')->count(),
                // Menghitung pelanggan yang aktif saja (admin tidak dihitung).
            'jumlahPelanggan' => Pengguna::query()
                ->where('peran', Pengguna::PERAN_PELANGGAN)
                ->where('status_akun', Pengguna::STATUS_AKTIF)
                ->count(),
            'pendapatanHariIni' => $this->totalPendapatan($hariIni, $hariIni),
        ];
    }

    /**
         * Daftar "yang perlu dikerjakan admin sekarang": room yang menunggu check-in
     * hari ini, dan pembayaran cash yang belum diverifikasi.
     *
     * @return array{checkinMenunggu: Collection<int, DetailReservasi>, cashMenunggu: Collection<int, Pembayaran>}
     */
    public function perluDiproses(): array
    {
        $hariIni = Carbon::today();

        return [
            'checkinMenunggu' => DetailReservasi::query()
                     ->with(['room', 'reservasi.pengguna'])
                     ->whereDate('tanggal', $hariIni)
                         // Belum check-in, tetapi reservasinya sudah dikonfirmasi.
                     ->whereNull('waktu_checkin')
                     ->whereHas('reservasi', fn ($query) => $query->whereIn('status_reservasi', ['dikonfirmasi', 'check_in']))
                     ->orderBy('jam_mulai')
                     ->get(),
            'cashMenunggu' => Pembayaran::query()
                ->with('reservasi.pengguna')
                ->where('metode_pembayaran', 'cash')
                ->where('status_pembayaran', 'pending')
                ->orderByDesc('id_pembayaran')
                ->get(),
        ];
    }

    /** Tabel "Reservasi terbaru" di dashboard. Secara bawaan mengambil 5 yang paling baru. */
    public function reservasiTerbaru(int $batas = 5): Collection
    {
        return Reservasi::query()
            ->with(['pengguna', 'detailReservasi.room'])
            ->orderByDesc('tanggal_dibuat')
            ->orderByDesc('id_reservasi')
            ->limit($batas)
            ->get();
    }

    // ---------------------------------------------------------------------
    // Laporan
    // ---------------------------------------------------------------------

    /**
     * Laporan reservasi. Bisa difilter tanggal, status, fasilitas, dan room.
         * Nilai baliknya: baris datanya, totalnya, rekap per hari, dan rekap per status.
     *
     * @param  array{mulai: ?Carbon, akhir: ?Carbon, status: ?string, fasilitas: ?int, room: ?int}  $filter
     * @return array{baris: Collection<int, Reservasi>, total: float, perHari: Collection<int, array{tanggal: string, label: string, jumlah: int, total: float}>, perStatus: Collection<int, array{status: string, jumlah: int, total: float}>}
     */
    public function laporanReservasi(array $filter): array
    {
            // when() hanya memasang filter kalau nilainya ada isinya.
        $baris = Reservasi::query()
            ->with(['pengguna', 'detailReservasi.room.fasilitas', 'pembayaran'])
                // Filter tanggal memakai tanggal jadwal main (di detail_reservasi), bukan tanggal pesan.
            ->when($filter['mulai'] ?? null, fn ($query, $tanggal) => $query->whereHas(
                'detailReservasi',
                fn ($detail) => $detail->whereDate('tanggal', '>=', $tanggal),
            ))
            ->when($filter['akhir'] ?? null, fn ($query, $tanggal) => $query->whereHas(
                'detailReservasi',
                fn ($detail) => $detail->whereDate('tanggal', '<=', $tanggal),
            ))
            ->when($filter['status'] ?? null, fn ($query, $status) => $query->where('status_reservasi', $status))
            ->when($filter['fasilitas'] ?? null, fn ($query, $id) => $query->whereHas(
                'detailReservasi.room',
                fn ($room) => $room->where('id_fasilitas', $id),
            ))
            ->when($filter['room'] ?? null, fn ($query, $id) => $query->whereHas(
                'detailReservasi',
                fn ($detail) => $detail->where('id_room', $id),
            ))
            ->orderByDesc('tanggal_dibuat')
            ->orderByDesc('id_reservasi')
            ->get();

        return [
            'baris' => $baris,
            // Total nilai reservasi (bukan yang sudah dibayar).
            'total' => (float) $baris->sum('total_reservasi'),
            'perHari' => $this->reservasiPerHari($baris, $filter['mulai'] ?? null, $filter['akhir'] ?? null),
                        // Dikelompokkan per status, hasilnya untuk grafik/donut di view.
            'perStatus' => $baris
                ->groupBy('status_reservasi')
                ->map(fn (Collection $grup, string $status): array => [
                    'status' => $status,
                    'jumlah' => $grup->count(),
                    'total' => (float) $grup->sum('total_reservasi'),
                ])
                ->sortByDesc('jumlah')
                ->values(),
        ];
    }

    /**
     * Data grafik batang laporan reservasi: jumlah reservasi per hari jadwal.
         * Hari yang tidak ada reservasi tetap ditampilkan supaya grafiknya tidak kosong.
         * Kalau rentangnya lebih dari 92 hari, grafiknya dikosongkan agar
         * tidak merender ratusan batang dan membuat halaman berat.
     *
     * @param  Collection<int, Reservasi>  $baris
     * @return Collection<int, array{tanggal: string, label: string, jumlah: int, total: float}>
     */
    private function reservasiPerHari(Collection $baris, ?Carbon $mulai, ?Carbon $akhir): Collection
    {
            // Mengambil semua tanggal jadwal dari detail reservasinya.
        $tanggal = $baris->flatMap(fn (Reservasi $item) => $item->detailReservasi->pluck('tanggal'))->filter();

            // Kalau filter tanggal kosong, pakai tanggal paling awal dan paling akhir dari data.
        $awal = ($mulai ?? $tanggal->min())?->copy()->startOfDay();
        $puncak = ($akhir ?? $tanggal->max())?->copy()->startOfDay();

        if (! $awal || ! $puncak || $awal->diffInDays($puncak) > 92) {
            return collect();
        }

        $peta = $baris->flatMap(fn (Reservasi $item) => $item->detailReservasi
            ->map(fn ($detail) => ['tanggal' => $detail->tanggal?->format('Y-m-d'), 'item' => $item]))
            ->filter(fn (array $isi) => $isi['tanggal'] !== null)
            ->groupBy('tanggal');

        $hasil = collect();
        // Berjalan hari per hari dari awal sampai akhir rentang.
        for ($hari = $awal->copy(); $hari->lte($puncak); $hari->addDay()) {
                    // unique('id_reservasi') supaya 1 reservasi multi-room hanya dihitung sekali.
            $grup = $peta->get($hari->format('Y-m-d'))?->pluck('item')->unique('id_reservasi');

            $hasil->push([
                'tanggal' => $hari->format('Y-m-d'),
                'label' => $hari->format('d/m'),
                'jumlah' => $grup?->count() ?? 0,
                'total' => (float) ($grup?->sum('total_reservasi') ?? 0),
            ]);
        }

        return $hasil;
    }

    /**
         * Laporan transaksi pembayaran. Filter: tanggal, metode, jenis, dan status.
     *
     * @param  array{mulai: ?Carbon, akhir: ?Carbon, metode: ?string, jenis: ?string, status: ?string}  $filter
     * @return array{baris: Collection<int, Pembayaran>, berhasil: float, perHari: Collection<int, array{tanggal: string, label: string, jumlah: int, total: float}>, perStatus: Collection<int, array{status: string, jumlah: int, total: float}>, perMetode: Collection<int, array{metode: string, jumlah: int, total: float}>}
     */
    public function laporanTransaksi(array $filter): array
    {
        $baris = Pembayaran::query()
            ->with(['reservasi.pengguna', 'diverifikasiOleh'])
            // Filter tanggal memakai waktu_bayar, dari awal hari sampai akhir hari.
            ->when(
                $filter['mulai'] ?? null,
                fn ($query, $tanggal) => $query->where('waktu_bayar', '>=', $tanggal->copy()->startOfDay()),
            )
            ->when(
                $filter['akhir'] ?? null,
                fn ($query, $tanggal) => $query->where('waktu_bayar', '<=', $tanggal->copy()->endOfDay()),
            )
            ->when($filter['metode'] ?? null, fn ($query, $metode) => $query->where('metode_pembayaran', $metode))
            ->when($filter['jenis'] ?? null, fn ($query, $jenis) => $query->where('jenis_pembayaran', $jenis))
            ->when($filter['status'] ?? null, fn ($query, $status) => $query->where('status_pembayaran', $status))
            ->orderByDesc('id_pembayaran')
            ->get();

        return [
            'baris' => $baris,
            // "Berhasil" hanya yang berstatus sukses. Yang pending/gagal tidak dihitung.
            'berhasil' => (float) $baris->where('status_pembayaran', 'sukses')->sum('jumlah'),
            'perHari' => $this->transaksiPerHari($baris, $filter['mulai'] ?? null, $filter['akhir'] ?? null),
            // Rekap jumlah dan nominal per status pembayaran.
            'perStatus' => $baris
                ->groupBy('status_pembayaran')
                ->map(fn (Collection $grup, string $status): array => [
                    'status' => $status,
                    'jumlah' => $grup->count(),
                    'total' => (float) $grup->sum('jumlah'),
                ])
                ->sortByDesc('jumlah')
                ->values(),
            // Rekap per metode (qris/cash).
            'perMetode' => $baris
                ->groupBy('metode_pembayaran')
                ->map(fn (Collection $grup, string $metode): array => [
                    'metode' => $metode,
                    'jumlah' => $grup->count(),
                    'total' => (float) $grup->where('status_pembayaran', 'sukses')->sum('jumlah'),
                ])
                ->sortByDesc('jumlah')
                ->values(),
        ];
    }

    /**
     * Grafik batang laporan transaksi: jumlah transaksi per hari waktu bayar.
         * Yang belum punya waktu_bayar (misalnya masih pending) dilewati karena
         * tidak punya tanggal. Rentang di atas 92 hari juga dilewati.
     *
     * @param  Collection<int, Pembayaran>  $baris
     * @return Collection<int, array{tanggal: string, label: string, jumlah: int, total: float}>
     */
    private function transaksiPerHari(Collection $baris, ?Carbon $mulai, ?Carbon $akhir): Collection
    {
        $bertanggal = $baris->filter(fn (Pembayaran $item) => $item->waktu_bayar !== null);

        $awal = ($mulai ?? $bertanggal->min('waktu_bayar'))?->copy()->startOfDay();
        $puncak = ($akhir ?? $bertanggal->max('waktu_bayar'))?->copy()->startOfDay();

        if (! $awal || ! $puncak || $awal->diffInDays($puncak) > 92) {
            return collect();
        }

        $peta = $bertanggal->groupBy(fn (Pembayaran $item) => $item->waktu_bayar->format('Y-m-d'));

        $hasil = collect();
        for ($hari = $awal->copy(); $hari->lte($puncak); $hari->addDay()) {
            $grup = $peta->get($hari->format('Y-m-d'));

            $hasil->push([
                'tanggal' => $hari->format('Y-m-d'),
                'label' => $hari->format('d/m'),
                'jumlah' => $grup?->count() ?? 0,
                // Nominalnya hanya dari yang sukses.
                'total' => (float) ($grup?->where('status_pembayaran', 'sukses')->sum('jumlah') ?? 0),
            ]);
        }

        return $hasil;
    }

    /**
     * Laporan pendapatan (fitur 89–93). Dikelompokkan sesuai periode:
     * harian/rentang = per hari, mingguan = per pekan, bulanan = per bulan.
     *
     * @return array{baris: Collection<int, array{label: string, total: float, transaksi: int}>, total: float, transaksi: int}
     */
    public function laporanPendapatan(string $periode, Carbon $mulai, Carbon $akhir): array
    {
        // Kunci pengelompokannya berbeda tergantung periode (ini fungsi MySQL).
        $kunci = match ($periode) {
            'mingguan' => 'YEARWEEK(waktu_bayar, 1)',
            'bulanan' => "DATE_FORMAT(waktu_bayar, '%Y-%m')",
            default => 'DATE(waktu_bayar)',
        };

        // Langsung dijumlahkan di database, jadi tidak perlu mengambil semua baris ke PHP.
        $baris = Pembayaran::query()
            ->where('status_pembayaran', 'sukses')
            ->whereBetween('waktu_bayar', [$mulai, $akhir])
            ->selectRaw("{$kunci} as kunci, MIN(waktu_bayar) as contoh, SUM(jumlah) as total, COUNT(*) as transaksi")
            ->groupByRaw($kunci)
            ->orderByRaw('MIN(waktu_bayar)')
            ->get();

        // Untuk periode harian/rentang: hari yang tidak ada transaksi tetap diberi
                // baris dengan angka 0, supaya grafiknya tetap punya batang.
        if (in_array($periode, ['harian', 'rentang'], true)) {
            $peta = $baris->keyBy('kunci');
            $baris = collect();
            for ($tanggal = $mulai->copy()->startOfDay(); $tanggal->lte($akhir); $tanggal->addDay()) {
                $kunciHari = $tanggal->format('Y-m-d');
                $ada = $peta->get($kunciHari);
                $baris->push((object) [
                    'kunci' => $kunciHari,
                    'contoh' => $kunciHari,
                    'total' => $ada->total ?? 0,
                    'transaksi' => $ada->transaksi ?? 0,
                ]);
            }
        }

        return [
            // label() mengubah kunci tadi menjadi tulisan yang mudah dibaca (misalnya "November 2026").
            'baris' => $baris->map(fn ($item) => [
                'label' => $this->label($periode, (string) $item->kunci, $item->contoh),
                'tanggal' => Carbon::parse($item->contoh)->format('Y-m-d'),
                'total' => (float) $item->total,
                'transaksi' => (int) $item->transaksi,
            ]),
            'total' => (float) $baris->sum('total'),
            'transaksi' => (int) $baris->sum('transaksi'),
        ];
    }

    /**
         * Menghitung rentang tanggal yang dipakai. Kalau pengguna tidak mengisi tanggal,
         * nilai bawaannya mengikuti periode supaya grafiknya tidak kosong.
         * Kalau tanggalnya terbalik (mulai > akhir), otomatis ditukar.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rentang(string $periode, ?Carbon $mulai, ?Carbon $akhir): array
    {
            // Nilai bawaan: mingguan = 8 pekan terakhir, bulanan = 12 bulan terakhir, lainnya 7 hari.
        $awal = $mulai?->copy()->startOfDay() ?? match ($periode) {
            'mingguan' => Carbon::today()->subWeeks(7)->startOfWeek(),
            'bulanan' => Carbon::today()->subMonths(11)->startOfMonth(),
            default => Carbon::today()->subDays(6)->startOfDay(),
        };

        $selesai = $akhir?->copy()->endOfDay() ?? Carbon::today()->endOfDay();

        return $awal->lte($selesai) ? [$awal, $selesai] : [$selesai->copy()->startOfDay(), $awal->copy()->endOfDay()];
    }

    /** Grafik "Pendapatan berhasil" di dashboard. Secara bawaan 7 hari terakhir. */
    public function grafikDashboard(int $hari = 7): array
    {
        return $this->laporanPendapatan('harian', Carbon::today()->subDays($hari - 1)->startOfDay(), Carbon::today()->endOfDay());
    }

    // ---------------------------------------------------------------------

    // Menjumlahkan pendapatan sukses dalam rentang tanggal tertentu.
    private function totalPendapatan(Carbon $mulai, Carbon $akhir): float
    {
        return (float) Pembayaran::query()
            ->where('status_pembayaran', 'sukses')
            ->whereBetween('waktu_bayar', [$mulai->copy()->startOfDay(), $akhir->copy()->endOfDay()])
            ->sum('jumlah');
    }

    // Membuat query reservasi berdasarkan tanggal jadwalnya.
    // $operator isinya '=' (hari itu) atau '>=' (mulai tanggal itu).
    private function queryReservasiPadaTanggal(string $operator, Carbon $tanggal)
    {
        return Reservasi::query()->whereHas(
            'detailReservasi',
            fn ($detail) => $detail->whereDate('tanggal', $operator, $tanggal),
        );
    }

    // Mengubah kunci grup menjadi tulisan yang mudah dibaca di laporan.
    private function label(string $periode, string $kunci, ?string $contoh): string
    {
        return match ($periode) {
            'mingguan' => 'Pekan '.Carbon::parse($contoh)->startOfWeek()->translatedFormat('d M Y'),
            'bulanan' => Carbon::parse("{$kunci}-01")->translatedFormat('F Y'),
            default => Carbon::parse($kunci)->translatedFormat('d M Y'),
        };
    }
}

<?php

// File ini berfungsi seperti kasir: membuat reservasi baru, menghitung harga, dan memberi kode booking.
// Harga, subtotal, dan total SEMUANYA dihitung di sini (di server), bukan dari input form.
//        Ini penting: kalau harga diambil dari form, orang bisa mengubah harganya dari sisi klien.
// Dipanggil oleh: CheckoutController (saat pelanggan menekan tombol "Pesan").
// Bekerja sama dengan: KetersediaanService.php (untuk memeriksa jadwal bentrok atau tidak).
// Menulis ke tabel: `reservasi` dan `detail_reservasi`.

namespace App\Services;

use App\Models\Reservasi;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservasiService
{
    /** Batas waktu bayar = waktu pesan + 60 menit. Lewat dari itu nanti dibatalkan otomatis. */
    public const MENIT_BATAS_PEMBAYARAN = 60;

    // Sekali pesan maksimal 5 room, dan 1 room maksimal 6 jam.
    public const MAKS_ROOM = 5;

    public const MAKS_DURASI = 6;

    // KetersediaanService masuk otomatis lewat constructor (dependency injection).
    public function __construct(private readonly KetersediaanService $ketersediaan) {}

    /**
     * Memeriksa apakah jadwal bentrok atau tidak untuk 1 room.
     * Baris room-nya "dikunci" dulu (lockForUpdate) supaya kalau ada 2 orang
     * memesan bersamaan di room dan jam yang sama, salah satunya tidak bisa ikut lolos.
     */
    public function cekKetersediaan(Room $room, string $tanggal, string $jamMulai, string $jamSelesai): bool
    {
        Room::query()->whereKey($room->id_room)->lockForUpdate()->first();

        // true = kosong (aman dipesan), false = sudah dipesan.
        return ! $this->ketersediaan->bentrok($room, $tanggal, $jamMulai, $jamSelesai);
    }

    // Menghitung jam selesai dari jam mulai + durasi. Contoh: 14:00 + 2 jam = 16:00.
    public function jamSelesai(string $jamMulai, int $durasi): string
    {
        return sprintf('%02d:%s', (int) substr($jamMulai, 0, 2) + $durasi, substr($jamMulai, 3, 2));
    }

    // Menjumlahkan subtotal semua baris room menjadi total reservasi.
    /**
     * @param  Collection<int, array<string, mixed>>  $baris
     */
    public function hitungTotal(Collection $baris): float
    {
        return (float) $baris->sum('subtotal');
    }

    /**
         * Membuat kode booking harian dengan format RM-YYYYMMDD-NNN.
         * Contoh: RM-20261108-001. NNN di-reset setiap hari.
         * lockForUpdate() dipasang supaya 2 permintaan bersamaan tidak mendapat nomor yang sama.
         */
        public function buatKodeBooking(): string
        {
            $awalan = 'RM-'.now()->format('Ymd').'-';
                // Mengambil nomor terbesar hari ini, lalu ditambah 1.
            $terakhir = Reservasi::query()
                ->where('kode_booking', 'like', $awalan.'%')
                ->lockForUpdate()
                ->max('kode_booking');
            $urutan = $terakhir === null ? 1 : ((int) substr((string) $terakhir, -3)) + 1;

                // str_pad membuatnya jadi 3 digit, kekurangannya diisi nol di depan (1 menjadi 001).
            return $awalan.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
        }

    /**
         * Menyimpan reservasi multi-room sekaligus. Kalau ada 1 baris gagal,
         * semuanya dibatalkan (DB::transaction) sehingga tidak ada data setengah jadi.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function simpanReservasi(int $idPengguna, array $items): Reservasi
    {
        return DB::transaction(function () use ($idPengguna, $items): Reservasi {
                // Susun dulu barisnya (harga, cek bentrok, dan sebagainya).
            $baris = $this->susunBaris($items);

                // Membuat 1 baris "induk" di tabel reservasi.
            $reservasi = Reservasi::query()->create([
                'id_pengguna' => $idPengguna,
                'kode_booking' => $this->buatKodeBooking(),
                'tanggal_dibuat' => now(),
                'batas_pembayaran' => now()->addMinutes(self::MENIT_BATAS_PEMBAYARAN),
                'total_reservasi' => $this->hitungTotal($baris),
                'status_reservasi' => 'menunggu_pembayaran',
            ]);

                // Lalu baris detail room-nya (bisa lebih dari satu).
            $reservasi->detailReservasi()->createMany($baris->all());

            return $reservasi;
        });
    }

    /**
         * Mengubah input pelanggan menjadi baris detail yang lengkap.
         * Di sini harga diambil dari database (bukan dari form) — inilah inti keamanannya.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function susunBaris(array $items): Collection
    {
            // Mengambil daftar id room yang dipilih, membuang duplikat, lalu mengurutkannya.
        $idRoom = collect($items)->map(fn (array $item): int => (int) $item['id_room'])->unique()->sort()->values()->all();

            // Mengambil data room beserta fasilitasnya sekaligus, lalu dikunci.
        $room = Room::query()
            ->with('fasilitas')
            ->whereIn('id_room', $idRoom)
            ->orderBy('id_room')
            ->lockForUpdate()
            ->get()
            ->keyBy('id_room');

        $baris = collect($items)->map(fn (array $item): array => $this->barisUntuk($room, $item))->values();

        // Periksa juga: dalam 1 pengajuan ini, ada 2 baris yang jadwalnya saling bertabrakan?
        $this->periksaBentrokAntarBaris($baris);

        return $baris;
    }

    /**
             * Membuat 1 baris detail dari 1 input pelanggan. Di sinilah semua validasi bisnis diperiksa.
     *
     * @param  Collection<int, Room>  $room
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function barisUntuk(Collection $room, array $item): array
    {
        $dipilih = $room->get((int) $item['id_room']);

                // Room tidak ada di database? Tolak.
        if ($dipilih === null) {
            throw ValidationException::withMessages(['items' => 'Room yang dipilih tidak ditemukan.']);
        }

                // Room dinonaktifkan admin? Tolak.
        if ($dipilih->status_room === 'tidak_aktif') {
            throw ValidationException::withMessages(['items' => "Room {$dipilih->nama_room} sedang tidak aktif sehingga tidak dapat dipesan."]);
        }

                // Fasilitasnya dinonaktifkan? Tolak juga.
        if ($dipilih->fasilitas === null || $dipilih->fasilitas->status_fasilitas !== 'aktif') {
            throw ValidationException::withMessages(['items' => "Fasilitas room {$dipilih->nama_room} sedang tidak aktif sehingga tidak dapat dipesan."]);
        }

        $tanggal = (string) $item['tanggal'];
        $jamMulai = substr((string) $item['jam_mulai'], 0, 5);
        $durasi = (int) $item['durasi'];
        $jamSelesai = $this->jamSelesai($jamMulai, $durasi);

        // Sudah dipesan orang lain di jam yang sama? Tolak.
        if (! $this->cekKetersediaan($dipilih, $tanggal, $jamMulai, $jamSelesai)) {
            throw ValidationException::withMessages(['items' => "Jadwal {$dipilih->nama_room} pada {$tanggal} pukul {$jamMulai}–{$jamSelesai} sudah dipesan. Pilih jam lain."]);
        }

        // Harga diambil dari model Room (hargaEfektif()), bukan dari form.
        $harga = $dipilih->hargaEfektif();

        return [
            'id_room' => $dipilih->id_room,
            'tanggal' => $tanggal,
            'jam_mulai' => $jamMulai,
            'durasi_jam' => $durasi,
            'jam_selesai' => $jamSelesai,
            'harga_per_jam' => round($harga, 2),
            'subtotal' => round($durasi * $harga, 2),
        ];
    }

    /**
         * Memeriksa bentrok antar baris dalam SATU pengajuan.
         * Contoh kasus: pengguna memilih room 1 jam 14-16, lalu memilih room 1 lagi jam 15-17.
         * Kondisi seperti itu harus ditolak, tidak boleh lolos ke database.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     */
    private function periksaBentrokAntarBaris(Collection $baris): void
    {
            // Mengelompokkan per kombinasi room dan tanggal.
        $perSlot = $baris->groupBy(fn (array $item): string => $item['id_room'].'|'.$item['tanggal']);

        foreach ($perSlot as $kelompok) {
                // Mengurutkan dari jam paling awal, lalu membandingkan yang bersebelahan.
            $urut = $kelompok->sortBy('jam_mulai')->values();

            for ($i = 1; $i < $urut->count(); $i++) {
                    // Kalau jam mulai yang ini lebih awal dari jam selesai sebelumnya, berarti bertabrakan.
                if ($urut[$i]['jam_mulai'] < $urut[$i - 1]['jam_selesai']) {
                    throw ValidationException::withMessages(['items' => 'Ada dua room yang sama dengan jadwal beririsan dalam satu pengajuan.']);
                }
            }
        }
    }
}

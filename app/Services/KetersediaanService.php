<?php

// File ini berfungsi memeriksa apakah sebuah room masih kosong pada jam tertentu.
// Penilaiannya dihitung dari jadwal yang sudah dipesan, bukan dari status room-nya.
// Dipakai oleh: ReservasiService.php (saat akan menyimpan pesanan),
//               KatalogController (untuk menampilkan slot jam di halaman detail room).
// Sumber datanya: tabel `detail_reservasi` yang digabung dengan tabel `reservasi`.

namespace App\Services;

use App\Models\DetailReservasi;
use App\Models\Room;
use Illuminate\Support\Collection;

class KetersediaanService
{
    // Jam buka dan jam tutup tempat. Slot dihitung per 1 jam dari jam 10 sampai jam 20.
    public const JAM_BUKA = 10;

    public const JAM_TUTUP = 21;

    /**
     * Mengambil jadwal yang sudah dipesan di 1 room pada 1 tanggal.
     * Reservasi berstatus 'dibatalkan' tidak dihitung, jadi slotnya kembali kosong.
     *
     * @return Collection<int, array{jam_mulai: string, jam_selesai: string}>
     */
    public function jadwalTerpesan(Room $room, string $tanggal): Collection
    {
        // Memanggil versi "semua room", lalu mengambil data milik room ini saja.
        return $this->jadwalTerpesanSemua([$room->id_room], $tanggal)->get($room->id_room, collect());
    }

    /**
     * Sama seperti di atas, tetapi untuk banyak room sekaligus agar query tidak diulang.
     *
     * @param  array<int, int|string>  $idRoom
     * @return Collection<int|string, Collection<int, array{jam_mulai: string, jam_selesai: string}>>
     */
    public function jadwalTerpesanSemua(array $idRoom, string $tanggal): Collection
    {
        // Tidak ada room yang diperiksa, kembalikan koleksi kosong.
        if ($idRoom === []) {
            return collect();
        }

        // Mengambil dari model DetailReservasi, lalu di-join ke tabel `reservasi`
        // agar reservasi yang sudah dibatalkan bisa dikeluarkan dari hasil.
        return DetailReservasi::query()
            ->join('reservasi', 'reservasi.id_reservasi', '=', 'detail_reservasi.id_reservasi')
            ->whereIn('detail_reservasi.id_room', $idRoom)
            ->whereDate('detail_reservasi.tanggal', $tanggal)
            ->where('reservasi.status_reservasi', '<>', 'dibatalkan')
            ->orderBy('detail_reservasi.jam_mulai')
            ->get(['detail_reservasi.id_room', 'detail_reservasi.jam_mulai', 'detail_reservasi.jam_selesai'])
            ->groupBy('id_room')
            // substr(...,0,5) mengambil "HH:MM" saja, membuang bagian detiknya.
            ->map(fn ($baris): Collection => $baris
                ->map(fn ($item): array => [
                    'jam_mulai' => substr((string) $item->jam_mulai, 0, 5),
                    'jam_selesai' => substr((string) $item->jam_selesai, 0, 5),
                ])
                ->values());
    }

    /**
         * Jam mulai paling awal yang masih bisa dipesan.
         * Kalau tanggalnya hari ini, jam yang sudah lewat dianggap tidak bisa dipesan lagi.
         */
        public function jamMulaiTerdekat(string $tanggal): int
        {
            // Bukan hari ini, berarti masih boleh mulai dari jam buka.
            if ($tanggal !== now()->toDateString()) {
                return self::JAM_BUKA;
            }

            // Dibatasi jam tutup: setelah pukul 21.00 tidak ada jam mulai yang sah lagi.
            return min(self::JAM_TUTUP, max(self::JAM_BUKA, (int) now()->format('H') + 1));
        }

        /** Masih ada jam sisa di tanggal itu? (false = hari itu sudah habis) */
        public function adaSisaJam(string $tanggal): bool
        {
            return $this->jamMulaiTerdekat($tanggal) < self::JAM_TUTUP;
        }

        /**
         * Menghitung berapa slot jam kosong per room di tanggal tersebut.
         * 0 berarti room penuh seharian.
         *
         * @param  array<int, int|string>  $idRoom
         * @return array<int|string, int>
         */
        public function slotKosongSemua(array $idRoom, string $tanggal): array
        {
            $terpesan = $this->jadwalTerpesanSemua($idRoom, $tanggal);
            // Jam yang sudah lewat tidak dihitung: room hanya tersedia kalau masih ada jam tersisa.
            $jamAwal = $this->jamMulaiTerdekat($tanggal);

            $hasil = [];

            foreach ($idRoom as $id) {
                $jadwal = $terpesan->get($id, collect());
                $kosong = 0;

                // Memeriksa jam per jam: 10:00-11:00, 11:00-12:00, dan seterusnya sampai jam tutup.
                for ($jam = $jamAwal; $jam < self::JAM_TUTUP; $jam++) {
                    $mulai = sprintf('%02d:00', $jam);
                    $selesai = sprintf('%02d:00', $jam + 1);

                    // Kalau slot ini bertabrakan dengan jadwal yang sudah ada, tidak dihitung kosong.
                    $kosong += $jadwal->contains(
                        fn (array $item): bool => $mulai < $item['jam_selesai'] && $selesai > $item['jam_mulai'],
                    ) ? 0 : 1;
                }

                $hasil[$id] = $kosong;
            }

            return $hasil;
        }

        /**
         * Method inilah yang dipakai untuk mencegah double booking.
         * Bentrok artinya jam mainnya bertabrakan dengan jadwal lain.
     * Kalau jadwalnya bersambung tepat (14.00 selesai, 14.00 mulai) itu tidak dianggap bentrok.
     */
    public function bentrok(Room $room, string $tanggal, string $jamMulai, string $jamSelesai): bool
    {
        // Logikanya: bentrok kalau jadwal lama selesainya LEBIH BESAR dari jam mulai baru,
        // DAN jadwal lama mulainya LEBIH KECIL dari jam selesai baru.
        return DetailReservasi::query()
            ->join('reservasi', 'reservasi.id_reservasi', '=', 'detail_reservasi.id_reservasi')
            ->where('detail_reservasi.id_room', $room->id_room)
            ->whereDate('detail_reservasi.tanggal', $tanggal)
            ->where('reservasi.status_reservasi', '<>', 'dibatalkan')
            ->where('detail_reservasi.jam_selesai', '>', $jamMulai)
            ->where('detail_reservasi.jam_mulai', '<', $jamSelesai)
            ->exists();
    }

    /**
     * Membuat daftar status tiap slot jam. Dipakai untuk menampilkan grid jam di halaman room.
     *
     * @return array<string, string> jam "HH:MM" => 'tersedia' | 'dipesan'
     */
    public function statusSlot(Room $room, string $tanggal, ?Collection $terpesan = null): array
    {
        // Room yang dinonaktifkan admin tidak punya slot sama sekali.
        if ($room->status_room === 'tidak_aktif') {
            return [];
        }

        // Kalau jadwalnya sudah diambil dari luar, pakai itu. Kalau belum, ambil sendiri.
        $terpesan ??= $this->jadwalTerpesan($room, $tanggal);
        $slot = [];

        for ($jam = self::JAM_BUKA; $jam < self::JAM_TUTUP; $jam++) {
            $mulai = sprintf('%02d:00', $jam);
            $selesai = sprintf('%02d:00', $jam + 1);

            $slot[$mulai] = $terpesan->contains(
                fn (array $jadwal): bool => $mulai < $jadwal['jam_selesai'] && $selesai > $jadwal['jam_mulai'],
            ) ? 'dipesan' : 'tersedia';
        }

        return $slot;
    }
}
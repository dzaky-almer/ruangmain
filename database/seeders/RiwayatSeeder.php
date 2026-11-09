<?php

// Jalankan: php artisan db:seed                  (dipanggil otomatis oleh DatabaseSeeder)
//           php artisan db:seed --class=RiwayatSeeder   (jalankan sendiri)
// Fungsi: seeder riwayat massal — 15 pelanggan tambahan + 30 reservasi lampau
//         (selesai/dibatalkan) beserta detail & pembayarannya.
// Hasil: dashboard, grafik, dan laporan admin tidak kosong.
// Catatan: semua tanggal >= 8 hari ke belakang supaya tidak bertabrakan dengan
//          slot milik DemoSeeder maupun jadwal hari ini.

namespace Database\Seeders;

use App\Models\DetailReservasi;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Data riwayat massal: pelanggan tambahan + reservasi selesai/dibatalkan pada
 * 8???180 hari terakhir, supaya dashboard, grafik, dan laporan tidak kosong.
 *
 * Semua tanggal lampau (>= 8 hari) agar tidak bertabrakan dengan slot yang
 * dipakai DemoSeeder maupun jadwal hari ini.
 */
class RiwayatSeeder extends Seeder
{
    // Jumlah pelanggan yang dibuat (email pelanggan01..15@ruangmain.test).
    public const JUMLAH_PELANGGAN = 15;

    // Jumlah reservasi historis yang dibuat.
    public const JUMLAH_RESERVASI = 30;

    // php artisan db:seed --class=RiwayatSeeder
    public function run(): void
    {
            $this->pelangganTambahan();

            // ponytail: riwayat tidak idempoten; cukup lewati kalau sudah pernah diisi.
            if (Reservasi::query()->where('tanggal_dibuat', '<', now()->subDays(7))->exists()) {
                $this->command?->info('RiwayatSeeder: riwayat sudah ada, dilewati.');

                return;
            }

            // Seed acak tetap: hasil seeding selalu sama tiap kali dijalankan ulang.
            mt_srand(20261108);

        // Pelanggan sudah termasuk 15 tambahan dari pelangganTambahan() di atas.
        $pelanggan = Pengguna::query()->where('peran', Pengguna::PERAN_PELANGGAN)->orderBy('id_pengguna')->get();
        // with('fasilitas') agar tidak N+1 saat view/relasi dipakai di seeder lain.
        $room = Room::query()->with('fasilitas')->orderBy('id_room')->get();

        // Butuh data induk: pelanggan untuk pemesan, room untuk detail.
        if ($pelanggan->isEmpty() || $room->isEmpty()) {
            $this->command?->warn('Pelanggan atau room belum ada, jalankan DatabaseSeeder dulu.');

            return;
        }

        for ($i = 0; $i < self::JUMLAH_RESERVASI; $i++) {
            // Tanggal main 8..180 hari lalu; reservasi dibuat 1..3 hari sebelumnya.
            $tanggal = Carbon::today()->subDays(mt_rand(8, 180));
            $dibuat = $tanggal->copy()->subDays(mt_rand(1, 3))->setTime(mt_rand(8, 20), mt_rand(0, 59));
            // Sekitar 20% reservasi dibatalkan supaya laporan punya kasus gagal.
            $dibatalkan = mt_rand(1, 100) <= 20;

            $reservasi = Reservasi::query()->create([
                'id_pengguna' => $pelanggan[mt_rand(0, $pelanggan->count() - 1)]->id_pengguna,
                'kode_booking' => $this->kodeBerikutnya($dibuat),
                'tanggal_dibuat' => $dibuat,
                'batas_pembayaran' => $dibuat->copy()->addHour(),
                'total_reservasi' => 0, // dihitung setelah baris detail selesai
                'status_reservasi' => $dibatalkan ? 'dibatalkan' : 'selesai',
            ]);

            $total = 0;
            // Penanda room yang sudah dipakai di reservasi ini agar tidak dobel.
            $terpakai = [];

            // 1..2 baris detail per reservasi (bisa sewa dua room sekaligus).
            for ($baris = 0, $jumlahBaris = mt_rand(1, 2); $baris < $jumlahBaris; $baris++) {
                $dipilih = $room[mt_rand(0, $room->count() - 1)];

                if (isset($terpakai[$dipilih->id_room])) {
                    continue;
                }

                $terpakai[$dipilih->id_room] = true;

                // Durasi 1..3 jam; jam mulai dibatasi agar tidak lewat jam tutup 21:00.
                $durasi = mt_rand(1, 3);
                $jam = mt_rand(10, 21 - $durasi);
                $harga = $dipilih->hargaEfektif();
                $subtotal = $harga * $durasi;
                $total += $subtotal;

                DetailReservasi::query()->create([
                    'id_reservasi' => $reservasi->id_reservasi,
                    'id_room' => $dipilih->id_room,
                    'tanggal' => $tanggal->toDateString(),
                    'jam_mulai' => sprintf('%02d:00', $jam),
                    'durasi_jam' => $durasi,
                    'jam_selesai' => sprintf('%02d:00', $jam + $durasi),
                    'harga_per_jam' => $harga,
                    'subtotal' => $subtotal,
                    // Reservasi batal tidak pernah check-in/check-out.
                    'waktu_checkin' => $dibatalkan ? null : $tanggal->copy()->setTime($jam, mt_rand(0, 12)),
                    'waktu_checkout' => $dibatalkan ? null : $tanggal->copy()->setTime($jam + $durasi, mt_rand(0, 15)),
                ]);
            }

            $reservasi->update(['total_reservasi' => $total]);

            // Satu baris pembayaran per reservasi: gagal (DP 50%) kalau batal, sukses penuh kalau selesai.
            Pembayaran::query()->create([
                'id_reservasi' => $reservasi->id_reservasi,
                'jenis_pembayaran' => $dibatalkan ? 'dp' : 'pelunasan',
                'metode_pembayaran' => mt_rand(0, 1) === 0 ? 'qris' : 'cash',
                'jumlah' => $dibatalkan ? round($total * 0.5, 2) : $total,
                'status_pembayaran' => $dibatalkan ? 'gagal' : 'sukses',
                'id_transaksi_gateway' => $dibatalkan ? null : 'MOCK-'.strtoupper(bin2hex(random_bytes(6))),
                'waktu_bayar' => $dibatalkan ? null : $dibuat->copy()->addMinutes(mt_rand(3, 40)),
            ]);
        }

        $this->command?->info('RiwayatSeeder: '.self::JUMLAH_RESERVASI.' reservasi riwayat dibuat.');
    }

    // Dipanggil run() sebelum loop reservasi; juga memastikan akun pelanggan ada.
    private function pelangganTambahan(): void
    {
        $namaDepan = ['Rizky', 'Dewi', 'Fajar', 'Putri', 'Bayu', 'Nadia', 'Ari', 'Sinta', 'Doni', 'Maya', 'Reza', 'Intan', 'Galih', 'Citra', 'Hendra'];
        $namaBelakang = ['Pratama', 'Lestari', 'Nugroho', 'Anggraini', 'Saputra', 'Ramadhani', 'Wijaya', 'Kusuma', 'Hidayat', 'Puspita'];

        foreach (range(1, self::JUMLAH_PELANGGAN) as $i) {
                    $email = sprintf('pelanggan%02d@ruangmain.test', $i);

                    // Guard idempoten: seeder boleh dijalankan ulang tanpa duplikat email.
                    if (Pengguna::query()->where('email', $email)->exists()) {
                        continue;
                    }

                    // peran/status_akun di luar $fillable, jadi wajib forceCreate.
                    Pengguna::forceCreate([
                        'nama' => $namaDepan[$i - 1].' '.$namaBelakang[$i % count($namaBelakang)],
                        'email' => $email,
                        'no_hp' => sprintf('0812000%05d', 100 + $i),
                        'password' => Hash::make('Pelanggan123!'),
                        'peran' => Pengguna::PERAN_PELANGGAN,
                        'status_akun' => Pengguna::STATUS_AKTIF,
                    ]);
                }
    }

    // Dipanggil run() untuk tiap reservasi; pola sama dengan ReservasiService::buatKodeBooking().
    /** Nomor urut kode booking yang belum terpakai pada tanggal tersebut. */
    private function kodeBerikutnya(Carbon $dibuat): string
    {
        $awalan = 'RM-'.$dibuat->format('Ymd').'-';
        $terakhir = Reservasi::query()->where('kode_booking', 'like', $awalan.'%')->max('kode_booking');
        // Lanjut dari nomor tertinggi agar tidak melanggar unique index kode_booking.
        $urutan = $terakhir === null ? 1 : ((int) substr((string) $terakhir, -3)) + 1;

        return $awalan.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
    }
}

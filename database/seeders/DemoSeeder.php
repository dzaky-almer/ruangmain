<?php

// Jalankan: php artisan db:seed --class=DemoSeeder   (setelah DatabaseSeeder, butuh >= 7 room)
// Fungsi: seeder OPSIONAL — membuat 10 reservasi contoh yang mencakup semua status
//         (check_in, selesai, dikonfirmasi, menunggu_pembayaran, dibatalkan) + pembayaran
//         qris/cash yang sukses/pending/gagal, supaya dashboard & laporan admin ada isinya.
// Hasil: 10 reservasi + detail + pembayarannya; sebagian room jadi `digunakan`.
// Catatan: tidak dipanggil DatabaseSeeder supaya instalasi awal tetap bersih.

namespace Database\Seeders;

use App\Models\DetailReservasi;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data contoh untuk demo: reservasi dengan berbagai status, pembayaran, dan
 * check-in/check-out. Hanya dijalankan lewat DemoSeeder agar DatabaseSeeder
 * tetap bersih untuk instalasi awal.
 */
class DemoSeeder extends Seeder
{
    // php artisan db:seed --class=DemoSeeder
    public function run(): void
    {
        // Admin dipakai sebagai `diverifikasi_oleh` untuk pembayaran cash.
        $admin = Pengguna::query()->where('peran', 'admin')->firstOrFail();
        // Skenario di bawah mengakses room lewat index 0..6, jadi minimal harus ada 7 room.
        $room = Room::query()->orderBy('id_room')->get();

        if ($room->count() < 7) {
            $this->command?->warn('Room belum lengkap, jalankan DatabaseSeeder dulu.');

            return;
        }

        // Pelanggan dirotasi pakai modulo supaya tiap reservasi punya pemesan berbeda.
        $pelanggan = Pengguna::query()
            ->where('peran', 'pelanggan')
            ->orderBy('id_pengguna')
            ->get()
            ->values();

        $hariIni = Carbon::today();

        foreach ($this->skenario($hariIni, $room) as $indeks => $skenario) {
            $pemesan = $pelanggan[$indeks % $pelanggan->count()];

            $reservasi = Reservasi::query()->create([
                'id_pengguna' => $pemesan->id_pengguna,
                'kode_booking' => $this->kodeBerikutnya($skenario['dibuat']),
                'tanggal_dibuat' => $skenario['dibuat'],
                // Sama dengan ReservasiService::MENIT_BATAS_PEMBAYARAN (60 menit).
                'batas_pembayaran' => $skenario['dibuat']->copy()->addHour(),
                'total_reservasi' => 0, // diisi ulang setelah detail dihitung
                'status_reservasi' => $skenario['status'],
            ]);

            $total = 0;

            // Satu reservasi bisa punya beberapa baris detail (beberapa room).
            foreach ($skenario['room'] as $baris) {
                $dipilih = $room[$baris['index']];
                // Harga selalu lewat Room::hargaEfektif(), sama seperti alur reservasi asli.
                $harga = $dipilih->hargaEfektif();
                $subtotal = $harga * $baris['durasi'];
                $total += $subtotal;

                DetailReservasi::query()->create([
                    'id_reservasi' => $reservasi->id_reservasi,
                    'id_room' => $dipilih->id_room,
                    'tanggal' => $baris['tanggal'],
                    'jam_mulai' => $baris['jam'],
                    'durasi_jam' => $baris['durasi'],
                    // jam_selesai = jam_mulai + durasi, dihitung manual dari string "HH:00".
                    'jam_selesai' => sprintf('%02d:00', (int) substr($baris['jam'], 0, 2) + $baris['durasi']),
                    'harga_per_jam' => $harga,
                    'subtotal' => $subtotal,
                    'waktu_checkin' => $baris['checkin'],
                    'waktu_checkout' => $baris['checkout'],
                ]);

                // Skenario "sedang bermain" -> room ditandai terpakai.
                if ($baris['checkin'] !== null && $baris['checkout'] === null) {
                    $dipilih->update(['status_room' => 'digunakan']);
                }
            }

            $reservasi->update(['total_reservasi' => $total]);

            $sudahDibayar = 0.0;

            // Satu reservasi bisa punya 0, 1, atau 2 baris pembayaran (DP lalu pelunasan).
            foreach ($skenario['bayar'] as $bayar) {
                // Tanpa nominal eksplisit: dp = separuh total (PERSEN_DP 0.5), pelunasan = sisanya.
                $jumlah = $bayar['jumlah'] ?? ($bayar['jenis'] === 'dp'
                    ? $total / 2
                    : $total - $sudahDibayar);

                Pembayaran::query()->create([
                    'id_reservasi' => $reservasi->id_reservasi,
                    // Hanya cash yang punya admin verifikator.
                    'diverifikasi_oleh' => $bayar['metode'] === 'cash' ? $admin->id_pengguna : null,
                    'jenis_pembayaran' => $bayar['jenis'],
                    'metode_pembayaran' => $bayar['metode'],
                    'jumlah' => $jumlah,
                    'status_pembayaran' => $bayar['status'],
                    // Tiruan id transaksi Midtrans untuk pembayaran qris.
                    'id_transaksi_gateway' => $bayar['metode'] === 'qris' ? 'MOCK-'.strtoupper(bin2hex(random_bytes(6))) : null,
                    // Pending berarti belum ada waktu bayar.
                    'waktu_bayar' => $bayar['status'] === 'pending' ? null : $reservasi->tanggal_dibuat->copy()->addMinutes(12),
                    'waktu_verifikasi' => $bayar['metode'] === 'cash' && $bayar['status'] === 'sukses'
                        ? $reservasi->tanggal_dibuat->copy()->addMinutes(20)
                        : null,
                ]);

                if ($bayar['status'] === 'sukses') {
                    $sudahDibayar += $jumlah;
                }
            }
        }

        $this->command?->info('DemoSeeder: '.count($this->skenario($hariIni, $room)).' reservasi contoh dibuat.');
    }

    // php artisan db:seed --class=DemoSeeder
    /** Nomor urut kode booking yang belum terpakai pada tanggal tersebut. */
    private function kodeBerikutnya(Carbon $dibuat): string
    {
        // Format sama dengan ReservasiService::buatKodeBooking(): RM-YYYYMMDD-NNN.
        $awalan = 'RM-'.$dibuat->format('Ymd').'-';
        $terakhir = Reservasi::query()->where('kode_booking', 'like', $awalan.'%')->max('kode_booking');
        // Nomor lanjut dari kode tertinggi yang sudah ada supaya tidak bentrok unique index.
        $urutan = $terakhir === null ? 1 : ((int) substr((string) $terakhir, -3)) + 1;

        return $awalan.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Skenario dirancang agar tiap status punya contoh, termasuk slot yang
     * menahan jadwal hari ini dan room yang sedang dipakai.
     *
     * @return array<int, array<string, mixed>>
     */
    // Data skenario; dipanggil run() dua kali (untuk jumlah & untuk insert).
    private function skenario(Carbon $hariIni, $room): array
    {
        $kemarin = $hariIni->copy()->subDay();
        $besok = $hariIni->copy()->addDay();
        $lusa = $hariIni->copy()->addDays(2);

        // `status` = kolom reservasi.status_reservasi; `room` = baris detail_reservasi;
        // `bayar` = baris pembayaran. `index` menunjuk urutan $room hasil query.
        return [
            // 1. Lunas, sudah check-in, sedang bermain sekarang.
            [
                'status' => 'check_in',
                'dibuat' => $hariIni->copy()->subMinutes(90),
                'room' => [
                    ['index' => 0, 'tanggal' => $hariIni->toDateString(), 'jam' => $this->jamBerjalan($hariIni), 'durasi' => 2, 'checkin' => $hariIni->copy()->setTime((int) $this->jamBerjalan($hariIni), 5), 'checkout' => null],
                ],
                'bayar' => [
                    ['jenis' => 'pelunasan', 'metode' => 'qris', 'status' => 'sukses'],
                ],
            ],

            // 2. Sudah selesai (check-out), kemarin.
            [
                'status' => 'selesai',
                'dibuat' => $kemarin->copy()->subHours(3),
                'room' => [
                    ['index' => 1, 'tanggal' => $kemarin->toDateString(), 'jam' => '13:00', 'durasi' => 3, 'checkin' => $kemarin->copy()->setTime(13, 2), 'checkout' => $kemarin->copy()->setTime(16, 1)],
                    ['index' => 4, 'tanggal' => $kemarin->toDateString(), 'jam' => '13:00', 'durasi' => 3, 'checkin' => $kemarin->copy()->setTime(13, 4), 'checkout' => $kemarin->copy()->setTime(16, 0)],
                ],
                'bayar' => [
                    ['jenis' => 'pelunasan', 'metode' => 'cash', 'status' => 'sukses'],
                ],
            ],

            // 3. Selesai, bayar DP lalu pelunasan (dua baris pembayaran).
            [
                'status' => 'selesai',
                'dibuat' => $kemarin->copy()->subHours(6),
                'room' => [
                    ['index' => 2, 'tanggal' => $kemarin->toDateString(), 'jam' => '17:00', 'durasi' => 2, 'checkin' => $kemarin->copy()->setTime(17, 0), 'checkout' => $kemarin->copy()->setTime(19, 0)],
                ],
                'bayar' => [
                    ['jenis' => 'dp', 'metode' => 'qris', 'status' => 'sukses'],
                    ['jenis' => 'pelunasan', 'metode' => 'qris', 'status' => 'sukses'],
                ],
            ],

            // 4. Dikonfirmasi, DP sukses, jadwal besok.
            [
                'status' => 'dikonfirmasi',
                'dibuat' => $hariIni->copy()->subHours(2),
                'room' => [
                    ['index' => 3, 'tanggal' => $besok->toDateString(), 'jam' => '11:00', 'durasi' => 2, 'checkin' => null, 'checkout' => null],
                ],
                'bayar' => [
                    ['jenis' => 'dp', 'metode' => 'qris', 'status' => 'sukses'],
                ],
            ],

            // 5. Menunggu pembayaran, belum bayar sama sekali, jadwal besok.
            [
                'status' => 'menunggu_pembayaran',
                'dibuat' => $hariIni->copy()->subMinutes(20),
                'room' => [
                    ['index' => 5, 'tanggal' => $besok->toDateString(), 'jam' => '15:00', 'durasi' => 3, 'checkin' => null, 'checkout' => null],
                ],
                'bayar' => [],
            ],

            // 6. Menunggu verifikasi cash oleh admin.
            [
                'status' => 'menunggu_pembayaran',
                'dibuat' => $hariIni->copy()->subMinutes(45),
                'room' => [
                    ['index' => 6, 'tanggal' => $besok->toDateString(), 'jam' => '19:00', 'durasi' => 2, 'checkin' => null, 'checkout' => null],
                ],
                'bayar' => [
                    ['jenis' => 'dp', 'metode' => 'cash', 'status' => 'pending'],
                ],
            ],

            // 7. QRIS pending (belum dibayar di gateway).
            [
                'status' => 'menunggu_pembayaran',
                'dibuat' => $hariIni->copy()->subMinutes(30),
                'room' => [
                    ['index' => 1, 'tanggal' => $lusa->toDateString(), 'jam' => '16:00', 'durasi' => 1, 'checkin' => null, 'checkout' => null],
                ],
                'bayar' => [
                    ['jenis' => 'dp', 'metode' => 'qris', 'status' => 'pending'],
                ],
            ],

            // 8. Dibatalkan, slot tidak menahan jadwal.
            [
                'status' => 'dibatalkan',
                'dibuat' => $hariIni->copy()->subHours(5),
                'room' => [
                    ['index' => 0, 'tanggal' => $besok->toDateString(), 'jam' => '20:00', 'durasi' => 1, 'checkin' => null, 'checkout' => null],
                ],
                'bayar' => [
                    ['jenis' => 'dp', 'metode' => 'qris', 'status' => 'gagal'],
                ],
            ],

            // 9. Dikonfirmasi lunas cash, jadwal lusa — siap check-in.
            [
                'status' => 'dikonfirmasi',
                'dibuat' => $hariIni->copy()->subHours(1),
                'room' => [
                    ['index' => 4, 'tanggal' => $lusa->toDateString(), 'jam' => '10:00', 'durasi' => 4, 'checkin' => null, 'checkout' => null],
                ],
                'bayar' => [
                    ['jenis' => 'pelunasan', 'metode' => 'cash', 'status' => 'sukses'],
                ],
            ],

            // 10. Dikonfirmasi, dua room sekaligus, jadwal lusa.
            [
                'status' => 'dikonfirmasi',
                'dibuat' => $hariIni->copy()->subMinutes(70),
                'room' => [
                    ['index' => 2, 'tanggal' => $lusa->toDateString(), 'jam' => '14:00', 'durasi' => 2, 'checkin' => null, 'checkout' => null],
                    ['index' => 3, 'tanggal' => $lusa->toDateString(), 'jam' => '14:00', 'durasi' => 2, 'checkin' => null, 'checkout' => null],
                ],
                'bayar' => [
                    ['jenis' => 'dp', 'metode' => 'qris', 'status' => 'sukses'],
                ],
            ],
        ];
    }

    // php artisan db:seed --class=DemoSeeder
    /** Jam penuh terdekat yang masih di dalam operasional hari ini. */
    private function jamBerjalan(Carbon $hariIni): string
    {
        // Clamp ke 10..19 (JAM_BUKA 10, dan 19 supaya slot 2 jam masih <= JAM_TUTUP 21).
        $jam = min(max($hariIni->copy()->hour, 10), 19);

        return sprintf('%02d:00', $jam);
    }
}

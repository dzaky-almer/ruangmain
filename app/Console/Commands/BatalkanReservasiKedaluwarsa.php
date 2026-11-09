<?php

// File ini adalah perintah terminal (artisan command) untuk membereskan reservasi
// yang tidak dilanjutkan. Ada dua tugas yang dijalankan sekaligus:
//   1. Reservasi yang lewat batas waktu pembayaran → dibatalkan.
//   2. Reservasi yang jadwalnya sudah lewat (jam selesai terakhir sudah lampau) → dibatalkan.
// Cara kerja: command ini cuma memanggil PembayaranService, lalu menampilkan
//             berapa reservasi yang dibatalkan.
// Dijalankan otomatis tiap menit oleh penjadwal di routes/console.php,
// jadi tidak perlu dijalankan manual.
//
// Untuk menjalankan manual:
//   php artisan reservasi:batalkan-kedaluwarsa
// Hasilnya: contoh "3 reservasi kedaluwarsa dibatalkan."
//                  "1 reservasi lewat jadwal dibatalkan."
//
// Untuk menguji:
//   php artisan test --filter=BatalkanReservasiKedaluwarsa

namespace App\Console\Commands;

use App\Services\PembayaranService;
use Illuminate\Console\Command;

/**
 * Batalkan reservasi yang tidak dilanjutkan:
 * 1. `menunggu_pembayaran` yang melewati `batas_pembayaran` tanpa pembayaran sukses.
 * 2. `menunggu_pembayaran`, `dikonfirmasi`, atau `check_in` yang jadwalnya sudah lewat
 *    (jam selesai room terakhir).
 */
class BatalkanReservasiKedaluwarsa extends Command
{
    // Nama command yang diketik di terminal: php artisan reservasi:batalkan-kedaluwarsa
    protected $signature = 'reservasi:batalkan-kedaluwarsa';

    // Keterangan singkat yang muncul saat menjalankan: php artisan list
    protected $description = 'Batalkan reservasi yang lewat batas pembayaran atau lewat jadwal';

    // Dijalankan saat command dipanggil.
    // PembayaranService disuntik otomatis oleh Laravel (dependency injection),
    // jadi tidak perlu membuat `new PembayaranService()` sendiri.
    // self::SUCCESS = kode keluar 0, artinya command berhasil.
    // Kalau perlu kode gagal, bisa pakai self::FAILURE.
    public function handle(PembayaranService $pembayaran): int
    {
        // Tugas pertama: lewat batas waktu pembayaran.
        $jumlah = $pembayaran->batalkanKedaluwarsa();

        $this->info("{$jumlah} reservasi kedaluwarsa dibatalkan.");

        // Tugas kedua: jadwalnya sudah lewat, apa pun statusnya.
        $jumlahLewat = $pembayaran->batalkanLewatJadwal();

        $this->info("{$jumlahLewat} reservasi lewat jadwal dibatalkan.");

        return self::SUCCESS;
    }
}

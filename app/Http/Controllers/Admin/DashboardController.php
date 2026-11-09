<?php

// File ini menampilkan halaman utama dashboard admin.
// Fungsi: mengumpulkan angka ringkasan (reservasi hari ini, room dipakai, pendapatan)
//         lalu mengirimkannya ke view admin.dashboard.
// Dipakai oleh: route `admin.dashboard` di routes/web.php (GET /admin).
// Mengambil data lewat: LaporanService.php.
// Data yang diambil berasal dari tabel `reservasi`, `pembayaran`, `room`, dan `pengguna`.
//
// Untuk membuka halamannya, jalankan server lalu login sebagai admin:
//   php artisan serve
// Buka http://localhost:8000/admin

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LaporanService;
use Illuminate\View\View;

/**
 * Dashboard admin (fitur 73–78): reservasi hari ini, reservasi mendatang, room
 * tersedia, room digunakan, jumlah pelanggan, dan ringkasan pendapatan.
 */
class DashboardController extends Controller
{
    // LaporanService disuntikkan otomatis oleh Laravel (dependency injection).
    // Artinya controller tidak perlu membuat objek service sendiri.
    public function __construct(private readonly LaporanService $laporan) {}

    // Method ini dipanggil saat admin membuka /admin, karena route-nya memakai __invoke.
    // Untuk menguji tampilannya:
    //   php artisan test --filter=Dashboard
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            // Angka ringkasan: reservasi hari ini, room tersedia, jumlah pelanggan, pendapatan.
            'ringkasan' => $this->laporan->ringkasanDashboard(),
            // Daftar reservasi yang masih menunggu tindakan admin.
            'perluDiproses' => $this->laporan->perluDiproses(),
            // Data grafik pendapatan beberapa hari terakhir.
            'grafik' => $this->laporan->grafikDashboard(),
            // Reservasi terbaru untuk ditampilkan di tabel bawah.
            'reservasiTerbaru' => $this->laporan->reservasiTerbaru(),
        ]);
    }
}

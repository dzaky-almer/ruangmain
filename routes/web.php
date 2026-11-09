<?php

// ============================================================================
// DAFTAR ALAMAT HALAMAN (ROUTES) — RUANGMAIN
// ============================================================================
// File ini yang menentukan: alamat apa saja bisa dibuka, dan controller mana
// yang menanganinya.
// Jadi kalau mau cari "halaman ini file-nya di mana", cari dulu alamatnya di
// file ini.
//
// Bentuk penulisannya: Route::get('/alamat', [Controller::class, 'namaMethod'])
//   - get  = buka halaman
//   - post = kirim form
//   - put / patch = ubah data
//
// Isi file ini dibagi 5 bagian:
//   1. Halaman depan (beranda)
//   2. Halaman daftar & login (khusus yang belum login)
//   3. Logout + callback pembayaran
//   4. Halaman pelanggan (wajib login sebagai pelanggan)
//   5. Halaman admin (wajib login sebagai admin, alamatnya diawali /admin)
//
// Alias controller memakai `as` (contoh: CheckinController as CheckinAdminController)
// supaya tidak bentrok dengan controller pelanggan yang namanya sama.
//
// Untuk melihat semua route beserta namanya:
//   php artisan route:list
//
// Untuk mencoba: php artisan serve → buka http://localhost:8000

use App\Http\Controllers\Admin\CheckinController as CheckinAdminController;
use App\Http\Controllers\Admin\CheckoutController as CheckoutAdminController;
use App\Http\Controllers\Admin\DashboardController as DashboardAdminController;
use App\Http\Controllers\Admin\FasilitasController;
use App\Http\Controllers\Admin\LaporanController;
use App\Http\Controllers\Admin\PelangganController;
use App\Http\Controllers\Admin\PembayaranController as PembayaranAdminController;
use App\Http\Controllers\Admin\ReservasiController as ReservasiAdminController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Auth\AutentikasiController;
use App\Http\Controllers\Pelanggan\KatalogController;
use App\Http\Controllers\Pelanggan\PembayaranController;
use App\Http\Controllers\Pelanggan\ProfilController;
use App\Http\Controllers\Pelanggan\ReservasiController;
use App\Http\Controllers\PembayaranCallbackController;
use Illuminate\Support\Facades\Route;

// Bagian 1 — Halaman depan.
// Tidak ada controller di sini: halaman beranda langsung menampilkan view
// 'beranda'. Jadi teks dan angkanya ditulis manual di resources/views/beranda.blade.php.
// Coba: buka http://localhost:8000
Route::get('/', function () {
    return view('beranda');
})->name('beranda');

// Bagian 2 — Halaman daftar & login.
// Middleware 'guest' = hanya untuk yang BELUM login.
// Kalau sudah login lalu membuka /login, otomatis dialihkan.
// Coba: php artisan test --filter=Autentikasi
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AutentikasiController::class, 'formDaftar'])->name('register');
    Route::post('/register', [AutentikasiController::class, 'daftar'])->name('register.store');
    Route::get('/login', [AutentikasiController::class, 'formLogin'])->name('login');
    Route::post('/login', [AutentikasiController::class, 'login'])->name('login.store');
});

// Bagian 3 — Logout & callback pembayaran.

// Logout pakai POST (bukan GET) supaya tidak bisa dipicu hanya dengan membuka link.
// Middleware 'auth' = wajib sudah login.
Route::post('/logout', [AutentikasiController::class, 'logout'])->middleware('auth')->name('logout');

// Webhook gateway: tanpa login, wajib verifikasi tanda tangan (SECURITY.md §5).
// URL ini dipanggil oleh server Midtrans, bukan oleh user.
// CSRF-nya dimatikan di bootstrap/app.php karena server luar tidak bisa mengirim token CSRF.
Route::post('/pembayaran/callback', PembayaranCallbackController::class)->name('pembayaran.callback');

// Bagian 4 — Halaman pelanggan (wajib login sebagai pelanggan).
// Middleware ['auth', 'peran:pelanggan'] = harus login DAN perannya pelanggan.
// Kalau admin membuka /rooms, hasilnya error 403.
Route::middleware(['auth', 'peran:pelanggan'])->group(function (): void {
    // --- Katalog room ---
    // Tanda {room} artinya bagian alamat itu berisi data, contoh /rooms/5.
    // Laravel menyerahkan angka 5 itu ke method sebagai variabel.
    Route::get('/rooms', [KatalogController::class, 'index'])->name('pelanggan.katalog');

    // --- Reservasi (pilih room, tanggal, jam) ---
    Route::get('/rooms/{room}', [KatalogController::class, 'detail'])->name('pelanggan.room.detail');
    Route::get('/rooms/{room}/jadwal', [KatalogController::class, 'jadwal'])->name('pelanggan.room.jadwal');
    // Dicek pakai AJAX saat user memilih jam, untuk tahu jam itu masih kosong atau tidak.
    Route::post('/reservasi/ketersediaan', [KatalogController::class, 'ketersediaan'])->name('pelanggan.ketersediaan');
    Route::get('/reservasi/baru', [ReservasiController::class, 'formBaru'])->name('pelanggan.reservasi.baru');
    Route::post('/reservasi', [ReservasiController::class, 'simpan'])->name('pelanggan.reservasi.simpan');
    Route::get('/reservasi', [ReservasiController::class, 'index'])->name('pelanggan.reservasi.index');
    Route::get('/reservasi/{reservasi}', [ReservasiController::class, 'detail'])->name('pelanggan.reservasi.detail');

    // --- Pembayaran ---
    Route::get('/reservasi/{reservasi}/bayar', [PembayaranController::class, 'form'])->name('pelanggan.pembayaran.form');
    Route::post('/reservasi/{reservasi}/bayar', [PembayaranController::class, 'simpan'])->name('pelanggan.pembayaran.simpan');
    Route::get('/pembayaran', [PembayaranController::class, 'index'])->name('pelanggan.pembayaran.index');
    Route::get('/pembayaran/{pembayaran}', [PembayaranController::class, 'status'])->name('pelanggan.pembayaran.status');
    Route::get('/pembayaran/{pembayaran}/lanjut', [PembayaranController::class, 'lanjut'])->name('pelanggan.pembayaran.lanjut');
    // Hanya jalan saat gateway tiruan dipakai (untuk latihan tanpa uang asli).
    Route::post('/pembayaran/{pembayaran}/simulasi', [PembayaranController::class, 'simulasi'])->name('pelanggan.pembayaran.simulasi');

    // --- Profil ---
    // put = mengubah data, post = menambah data. Form HTML mengirim PUT lewat
    // bantuan @method('PUT') di dalam Blade.
    Route::get('/profil', [ProfilController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfilController::class, 'perbarui'])->name('profil.update');
    Route::put('/profil/password', [ProfilController::class, 'ubahPassword'])->name('profil.password.update');
});

// Bagian 5 — Halaman admin.
// Wajib login sebagai admin.
// `prefix('admin')` = semua alamat diawali /admin.
// `name('admin.')` = semua nama route diawali 'admin.' (contoh admin.room.index).
// Coba: login admin@ruangmain.test / Admin123! lalu buka http://localhost:8000/admin
Route::middleware(['auth', 'peran:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    // Dashboard. Dipanggil tanpa nama method karena controllernya hanya satu method (__invoke).
    Route::get('/', DashboardAdminController::class)->name('dashboard');

    // Route::resource membuat 7 route CRUD sekaligus (index, create, store, show, edit, update, destroy).
    // `except('show')` = route show dihapus karena tidak dipakai.
    // `parameters(...)` menyamakan nama parameter di URL dengan nama route,
    // supaya helper route() tidak perlu menulis variabel tambahan.
    // Coba: php artisan route:list --path=admin/room
    Route::resource('fasilitas', FasilitasController::class)
        ->parameters(['fasilitas' => 'fasilitas'])
        ->except('show');
    Route::resource('room', RoomController::class)
        ->parameters(['room' => 'room'])
        ->except('show');

    // --- Reservasi: admin hanya melihat dan mengubah status, tidak membuat reservasi ---
    Route::get('/reservasi', [ReservasiAdminController::class, 'index'])->name('reservasi.index');
    Route::get('/reservasi/{reservasi}', [ReservasiAdminController::class, 'detail'])->name('reservasi.detail');
    Route::patch('/reservasi/{reservasi}/status', [ReservasiAdminController::class, 'ubahStatus'])->name('reservasi.status');

    // --- Check-in: pelanggan datang, admin menandai reservasi sudah mulai ---
    Route::get('/checkin', [CheckinAdminController::class, 'index'])->name('checkin.index');
    Route::post('/checkin', [CheckinAdminController::class, 'simpan'])->name('checkin.simpan');
    // {kode_booking} = kode huruf/angka, bukan id. Contoh /admin/checkin/RM-ABC123.
    Route::get('/checkin/{kode_booking}', [CheckinAdminController::class, 'detail'])->name('checkin.detail');

    // --- Check-out: sesi selesai ---
    Route::get('/checkout', [CheckoutAdminController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutAdminController::class, 'simpan'])->name('checkout.simpan');

    // --- Pembayaran: termasuk mencatat pembayaran tunai dan memverifikasi bukti transfer ---
    Route::get('/pembayaran', [PembayaranAdminController::class, 'index'])->name('pembayaran.index');
    Route::post('/pembayaran/cash', [PembayaranAdminController::class, 'catatCash'])->name('pembayaran.cash');
    Route::get('/pembayaran/{pembayaran}', [PembayaranAdminController::class, 'detail'])->name('pembayaran.detail');
    Route::post('/pembayaran/{pembayaran}/verifikasi', [PembayaranAdminController::class, 'verifikasi'])->name('pembayaran.verifikasi');

    // --- Pelanggan: admin melihat daftar pelanggan dan mengaktifkan/menonaktifkan akun ---
    Route::get('/pelanggan', [PelangganController::class, 'index'])->name('pelanggan.index');
    Route::get('/pelanggan/{pelanggan}', [PelangganController::class, 'detail'])->name('pelanggan.detail');
    Route::patch('/pelanggan/{pelanggan}/status', [PelangganController::class, 'ubahStatus'])->name('pelanggan.status');

    // --- Laporan ---
    // Setiap laporan punya halaman tampilan + tombol ekspor (unduh CSV/Excel).
    Route::get('/laporan/reservasi', [LaporanController::class, 'reservasi'])->name('laporan.reservasi');
    Route::get('/laporan/reservasi/ekspor', [LaporanController::class, 'eksporReservasi'])->name('laporan.reservasi.ekspor');
    Route::get('/laporan/transaksi', [LaporanController::class, 'transaksi'])->name('laporan.transaksi');
    Route::get('/laporan/transaksi/ekspor', [LaporanController::class, 'eksporTransaksi'])->name('laporan.transaksi.ekspor');
    Route::get('/laporan/pendapatan', [LaporanController::class, 'pendapatan'])->name('laporan.pendapatan');
    Route::get('/laporan/pendapatan/ekspor', [LaporanController::class, 'eksporPendapatan'])->name('laporan.pendapatan.ekspor');
});

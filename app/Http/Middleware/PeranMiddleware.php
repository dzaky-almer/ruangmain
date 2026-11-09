<?php

// File ini adalah "penjaga pintu" yang membatasi halaman berdasarkan peran user.
// Fungsi: menolak user yang belum login atau perannya tidak sesuai.
// Cara pakai di routes/web.php:
//   ->middleware(['auth', 'peran:pelanggan'])   // hanya pelanggan
//   ->middleware(['auth', 'peran:admin'])       // hanya admin
// Nama pendek `peran` didaftarkan di bootstrap/app.php (alias middleware).
//
// Untuk mencoba:
//   php artisan serve   → login sebagai pelanggan, lalu buka /admin
//   Hasilnya: muncul halaman 403 "Anda tidak memiliki akses ke halaman ini."

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PeranMiddleware
{
    // Dijalankan otomatis sebelum halaman dibuka.
    // Cek dua hal:
    //   1. Auth::check()        → user sudah login atau belum.
    //   2. in_array(..., $peran) → peran user ada di daftar peran yang diizinkan.
    // Kalau gagal salah satu, halaman dibatalkan dengan kode 403 (dilarang).
    // Kalau lolos, $next($request) meneruskan ke halaman yang diminta.
    // Parameter `string ...$peran` bisa menerima lebih dari satu peran, contoh `peran:admin,staf`.
    /** @param  array<int, string>  $peran */
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        if (! Auth::check() || ! in_array(Auth::user()->peran, $peran, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}

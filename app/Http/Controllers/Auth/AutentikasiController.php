<?php

// File ini mengurus pendaftaran, login, dan logout pengguna.
// Fungsi: memeriksa data form, membuat akun baru, memeriksa password,
//         membatasi percobaan login, lalu mengarahkan sesuai peran.
// Dipakai oleh: routes/web.php baris 24-31 (register, login, logout).
// Mengambil data dari model: Pengguna.php.
// Validasi input ditangani oleh: DaftarPenggunaRequest.php dan LoginRequest.php.
// Mengubah tabel: `pengguna`.
//
// Untuk mencoba manual, jalankan server lalu buka:
//   php artisan serve
// Pendaftaran: http://localhost:8000/register
// Login:       http://localhost:8000/login

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\DaftarPenggunaRequest;
use App\Http\Requests\LoginRequest;
use App\Models\Pengguna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AutentikasiController extends Controller
{
    // GET /register — menampilkan halaman form pendaftaran.
    public function formDaftar(): View
    {
        return view('auth.daftar');
    }

    // POST /register — membuat akun pelanggan baru lalu langsung login.
    // Untuk mengujinya:
    //   php artisan test --filter=Daftar
    public function daftar(DaftarPenggunaRequest $request): RedirectResponse
    {
        // Data sudah lolos validasi di DaftarPenggunaRequest.
        $data = $request->validated();

        $pengguna = Pengguna::query()->create([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'no_hp' => $data['no_hp'],
            // Password di-hash supaya tidak tersimpan sebagai teks biasa.
            'password' => Hash::make($data['password']),
        ]);

        // Pengguna baru langsung masuk tanpa perlu login ulang.
        Auth::login($pengguna);
        // ID sesi dibuat ulang untuk mencegah session fixation.
        $request->session()->regenerate();

        return redirect()->route('pelanggan.katalog')
            ->with('sukses', 'Pendaftaran berhasil. Selamat datang di RUANGMAIN.');
    }

    // GET /login — menampilkan halaman form login.
    public function formLogin(): View
    {
        return view('auth.login');
    }

    // POST /login — memeriksa email dan password, lalu menentukan halaman tujuan.
    // Untuk mengujinya:
    //   php artisan test --filter=Login
    public function login(LoginRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Kunci pembatas percobaan: gabungan email dan alamat IP.
        $kunciRateLimit = $this->kunciRateLimit($data['email'], $request->ip());

        // Lebih dari 5 percobaan gagal dalam 1 menit: permintaan ditolak sementara.
        if (RateLimiter::tooManyAttempts($kunciRateLimit, 5)) {
            return back()->withErrors([
                'email' => 'Terlalu banyak percobaan login. Coba lagi dalam '.RateLimiter::availableIn($kunciRateLimit).' detik.',
            ])->onlyInput('email');
        }

        $pengguna = Pengguna::query()->where('email', $data['email'])->first();

        // Email tidak ada atau password tidak cocok: pesannya disamakan demi keamanan,
        // supaya penyerang tidak bisa menebak email mana yang terdaftar.
        if (! $pengguna || ! Hash::check($data['password'], $pengguna->password)) {
            RateLimiter::hit($kunciRateLimit, 60);

            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        // Akun yang dinonaktifkan admin tidak boleh login.
        if ($pengguna->status_akun !== Pengguna::STATUS_AKTIF) {
            RateLimiter::hit($kunciRateLimit, 60);

            return back()->withErrors(['email' => 'Akun ini tidak dapat digunakan. Hubungi Admin.'])->onlyInput('email');
        }

        // Login berhasil: hitungan percobaan direset.
        RateLimiter::clear($kunciRateLimit);
        Auth::login($pengguna);
        $request->session()->regenerate();

        // Admin diarahkan ke dashboard, pelanggan ke katalog room.
        return $pengguna->peran === Pengguna::PERAN_ADMIN
            ? redirect()->route('admin.dashboard')
            : redirect()->route('pelanggan.katalog');
    }

    // POST /logout — keluar dari akun dan menghapus sesi.
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        // Sesi dimusnahkan dan token CSRF dibuat ulang.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('sukses', 'Anda berhasil keluar.');
    }

    // Membentuk kunci pembatas: email huruf kecil + alamat IP.
    // Str::transliterate membuat email tetap aman dipakai sebagai kunci.
    private function kunciRateLimit(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}

<?php

// File ini mengurus profil pelanggan yang sedang login.
// Fungsi: menampilkan form profil, menyimpan perubahan data diri, dan mengganti password.
// Dipakai oleh: routes/web.php baris 51-53 (profil.edit, profil.update, profil.password.update).
// Mengubah tabel: `pengguna` (nama, email, no_hp, password).
// Validasi input ditangani oleh: PerbaruiProfilRequest.php dan UbahPasswordRequest.php.
//
// Untuk mencoba manual, jalankan server lalu login sebagai pelanggan:
//   php artisan serve
// Lalu buka http://localhost:8000/profil

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Http\Requests\PerbaruiProfilRequest;
use App\Http\Requests\UbahPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfilController extends Controller
{
    // GET /profil — menampilkan form profil.
    public function edit(): View
    {
        return view('pelanggan.profil.edit');
    }

    // PUT /profil — menyimpan perubahan data diri.
    // Untuk mengujinya:
    //   php artisan test --filter=Profil
    public function perbarui(PerbaruiProfilRequest $request): RedirectResponse
    {
        // user() berasal dari sesi login, jadi pelanggan hanya bisa mengubah datanya sendiri.
        $request->user()->update($request->validated());

        return redirect()->route('profil.edit')->with('sukses', 'Profil berhasil diperbarui.');
    }

    // PUT /profil/password — mengganti password.
    // Password lama wajib benar; pemeriksaannya ada di UbahPasswordRequest.
    public function ubahPassword(UbahPasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            // Password baru di-hash sebelum disimpan.
            'password' => Hash::make($request->validated('password')),
        ]);

        return redirect()->route('profil.edit')->with('sukses', 'Password berhasil diubah.');
    }
}

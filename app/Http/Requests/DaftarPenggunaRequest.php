<?php

// File ini mengatur pengecekan input form pendaftaran akun pelanggan.
// Fungsi: memastikan nama, email, no HP, dan password terisi dan formatnya benar,
//         sekaligus menolak email yang sudah dipakai akun lain.
// Dipakai oleh: route `POST /register` (routes/web.php baris 26) lewat
//               AutentikasiController::daftar() yang menyuntikkan DaftarPenggunaRequest ini.
// Berhubungan dengan tabel: `pengguna` (lewat aturan Rule::unique).
//
// Untuk menguji validasi pendaftaran:
//   php artisan test --filter=Autentikasi
// Untuk mencoba manual: buka http://localhost:8000/register

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DaftarPenggunaRequest extends FormRequest
{
    // Selalu true karena halaman daftar memang untuk semua orang (belum punya akun).
    public function authorize(): bool
    {
        return true;
    }

    // Aturan input form daftar.
    // `email` memakai Rule::unique('pengguna', 'email') → Laravel mengecek langsung
    // ke tabel `pengguna`, kolom `email`. Kalau sudah ada, request ditolak.
    // `password` harus `confirmed` → ada input pembanding `password_confirmation`
    // yang harus isinya sama persis.
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('pengguna', 'email')],
            'no_hp' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    // Nama tampilan tiap kolom. Dipakai di dalam pesan galat lewat :attribute.
    // Contoh: ':attribute wajib diisi.' + no_hp => 'nomor HP' → "nomor HP wajib diisi."
    public function attributes(): array
    {
        return ['nama' => 'nama', 'email' => 'email', 'no_hp' => 'nomor HP', 'password' => 'password'];
    }

    // Pesan galat berbahasa Indonesia.
    // Pesan tanpa awalan nama kolom (seperti 'required') berlaku untuk SEMUA kolom
    // yang aturannya gagal — jadi tidak perlu tulis satu-satu.
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'max' => ':attribute tidak boleh lebih dari :max karakter.',
            'password.min' => 'Password minimal :min karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ];
    }
}

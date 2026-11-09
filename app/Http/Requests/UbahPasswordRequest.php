<?php

// File ini mengatur pengecekan input form ubah password.
// Fungsi: memastikan password lama benar dan password baru memenuhi syarat.
// Dipakai oleh: route `PUT /profil/password` (routes/web.php baris 53) lewat
//               ProfilController::ubahPassword() yang menyuntikkan UbahPasswordRequest ini.
//
// Untuk menguji validasi ubah password:
//   php artisan test --filter=Autentikasi

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UbahPasswordRequest extends FormRequest
{
    // Selalu true karena route-nya sudah dilindungi middleware `auth` di routes/web.php.
    public function authorize(): bool
    {
        return true;
    }

    // Aturan input form ubah password.
    // `current_password` adalah aturan bawaan Laravel: dia mencocokkan input ini
    // dengan password user yang sedang login. Kalau beda, request ditolak.
    // Jadi tidak perlu kode manual untuk mengecek password lama.
    // `confirmed` pada `password` mencari input `password_confirmation` sebagai pembanding.
    public function rules(): array
    {
        return [
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    // Nama tampilan kolom, dipakai mengganti :attribute di pesan galat.
    // Di sini dipakai untuk memperjelas: `password` disebut "password baru".
    public function attributes(): array
    {
        return ['password_lama' => 'password lama', 'password' => 'password baru'];
    }

    // Pesan galat berbahasa Indonesia.
    // `password_lama.current_password` khusus menangani kasus password lama salah.
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'password_lama.current_password' => 'Password lama tidak sesuai.',
            'password.min' => 'Password baru minimal :min karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
        ];
    }
}

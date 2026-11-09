<?php

// File ini mengatur pengecekan input form login.
// Fungsi: memastikan email dan password terisi dengan format yang benar sebelum diproses.
// Dipakai oleh: route `POST /login` (routes/web.php baris 28) lewat
//               AutentikasiController::login() yang menyuntikkan LoginRequest ini.
// Hasil: kalau ada yang salah, user dikembalikan ke form login dengan pesan galat.
//
// Untuk menguji validasi login:
//   php artisan test --filter=Autentikasi

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    // Selalu true karena halaman login memang untuk semua orang (belum punya akun).
    // Kalau false, Laravel akan menolak request sebelum aturan rules() dijalankan.
    public function authorize(): bool
    {
        return true;
    }

    // Aturan yang harus dipenuhi input dari form.
    // `email` harus format email betulan dan maksimal 100 huruf (menyesuaikan kolom di DB).
    // `password` cukup dicek terisi — panjangnya tidak dibatasi di sini karena password
    // lama bisa punya aturan berbeda dari password baru.
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string'],
        ];
    }

    // Pesan galat berbahasa Indonesia supaya user tahu apa yang salah.
    // Kunci seperti `email.required` artinya: pesan ini muncul saat aturan `required`
    // pada kolom `email` gagal.
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ];
    }
}

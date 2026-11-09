<?php

// File ini mengatur pengecekan input form ubah profil pelanggan.
// Fungsi: memastikan nama, email, dan no HP valid saat pelanggan memperbarui datanya.
// Dipakai oleh: route `PUT /profil` (routes/web.php baris 52) lewat
//               ProfilController::perbarui() yang menyuntikkan PerbaruiProfilRequest ini.
// Berhubungan dengan tabel: `pengguna` (lewat aturan Rule::unique).
//
// Untuk menguji validasi profil:
//   php artisan test --filter=Autentikasi
// Untuk mencoba manual: login sebagai pelanggan lalu buka http://localhost:8000/profil

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerbaruiProfilRequest extends FormRequest
{
    // Selalu true karena route-nya sudah dilindungi middleware `auth` di routes/web.php.
    public function authorize(): bool
    {
        return true;
    }

    // Aturan input form profil.
    // Bedanya dengan DaftarPenggunaRequest: aturan unique di sini memakai
    // ->ignore($this->user()->id_pengguna, 'id_pengguna').
    // Artinya: pengecekan email unik MENGABAIKAN baris milik user yang sedang login.
    // Tanpa ->ignore(), user tidak bisa menyimpan profilnya sendiri karena emailnya
    // dianggap "sudah dipakai" oleh dirinya sendiri.
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('pengguna', 'email')->ignore($this->user()->id_pengguna, 'id_pengguna')],
            'no_hp' => ['required', 'string', 'max:20'],
        ];
    }

    // Nama tampilan kolom, dipakai mengganti :attribute di pesan galat.
    public function attributes(): array
    {
        return ['nama' => 'nama', 'email' => 'email', 'no_hp' => 'nomor HP'];
    }

    // Pesan galat berbahasa Indonesia.
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'max' => ':attribute tidak boleh lebih dari :max karakter.',
        ];
    }
}

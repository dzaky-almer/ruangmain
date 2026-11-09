<?php

// File ini mengatur pengecekan input form tambah/ubah room.
// Fungsi: memastikan fasilitasnya ada, nama room wajar, kapasitas minimal 1,
//         harga masuk akal, dan statusnya salah satu pilihan yang sah.
// Dipakai oleh: route `resource('room')` (routes/web.php baris 61-62) lewat
//               Admin\RoomController::store() dan ::update().
// Berhubungan dengan tabel: `fasilitas` (lewat Rule::exists).
//
// Untuk menguji validasi room:
//   php artisan test --filter=Room
// Untuk mencoba manual: buka http://localhost:8000/admin/room

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanRoomRequest extends FormRequest
{
    // Selalu true karena route-nya sudah dilindungi middleware `auth` + `peran:admin`.
    public function authorize(): bool
    {
        return true;
    }

    // Aturan input form room.
    // `Rule::exists('fasilitas', 'id_fasilitas')` → Laravel mengecek langsung ke tabel
    // `fasilitas`. Kalau id yang dikirim tidak ada di tabel itu, request ditolak.
    // Ini mencegah room nyangkut ke fasilitas yang tidak pernah ada.
    // `gt:0` artinya harga harus lebih besar dari 0.
    // `Rule::in([...])` membatasi status hanya boleh salah satu dari empat nilai itu.
    public function rules(): array
    {
        return [
            'id_fasilitas' => ['required', 'integer', Rule::exists('fasilitas', 'id_fasilitas')],
            'nama_room' => ['required', 'string', 'max:50'],
            'kapasitas' => ['required', 'integer', 'min:1'],
            'harga_per_jam' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'status_room' => ['required', Rule::in(['tersedia', 'dibooking', 'digunakan', 'tidak_aktif'])],
        ];
    }

    // Nama tampilan kolom, dipakai mengganti :attribute di pesan galat bawaan Laravel.
    public function attributes(): array
    {
        return ['id_fasilitas' => 'fasilitas', 'nama_room' => 'nama room', 'kapasitas' => 'kapasitas', 'harga_per_jam' => 'harga per jam', 'status_room' => 'status operasional'];
    }
}

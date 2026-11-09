<?php

// File ini mengatur pengecekan input form tambah/ubah fasilitas.
// Fungsi: memastikan nama, tagline, deskripsi, status, dan foto fasilitas benar.
//         Foto boleh dari file yang diunggah ATAU dari URL, tapi tidak keduanya wajib.
// Dipakai oleh: route `resource('fasilitas')` (routes/web.php baris 58-59) lewat
//               Admin\FasilitasController::store() dan ::update().
//
// Untuk menguji validasi fasilitas:
//   php artisan test --filter=Fasilitas
// Untuk mencoba manual: buka http://localhost:8000/admin/fasilitas

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SimpanFasilitasRequest extends FormRequest
{
    // Selalu true karena route-nya sudah dilindungi middleware `auth` + `peran:admin`.
    public function authorize(): bool
    {
        return true;
    }

    // Aturan input form fasilitas.
    // `tagline` boleh kosong (`nullable`) karena cuma hiasan text di kartu katalog.
    // `foto` memakai aturan File bawaan Laravel: wajib gambar, formatnya salah satu
    // dari webp/png/jpg/jpeg, ukuran maksimal 2 MB.
    // `foto_url` alternatif kalau admin mau pakai gambar dari internet, bukan upload.
    // Keduanya `nullable` — maksudnya admin boleh isi salah satu saja, atau tidak sama sekali
    // (nanti dipakai gambar bawaan lewat Fasilitas::gambarBawaan()).
    public function rules(): array
    {
        return [
            'nama_fasilitas' => ['required', 'string', 'max:50'],
            'tagline' => ['nullable', 'string', 'max:100'],
            'deskripsi' => ['required', 'string'],
            'status_fasilitas' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'foto' => ['nullable', File::image()->types(['webp', 'png', 'jpg', 'jpeg'])->max('2mb')],
            'foto_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    // Pengecekan TAMBAHAN yang jalan SETELAH rules() selesai.
    // `getimagesize()` membaca isi file betulan, bukan cuma namanya.
    // Jadi file yang cuma di-rename jadi .jpg tapi isinya bukan gambar tetap tertolak.
    // Tanda @ di depan fungsi sengaja: PHP suka mengeluarkan warning kalau file bukan
    // gambar; warning itu kita matikan karena hasilnya sudah ditangani sendiri.
    public function after(): array
    {
        return [
            function ($validator): void {
                $foto = $this->file('foto');

                if ($foto && @getimagesize($foto->getRealPath()) === false) {
                    $validator->errors()->add('foto', 'Berkas foto harus berupa gambar yang valid.');
                }
            },
        ];
    }

    // Nama tampilan kolom, dipakai mengganti :attribute di pesan galat.
    public function attributes(): array
    {
        return [
            'nama_fasilitas' => 'nama fasilitas',
            'tagline' => 'tagline',
            'deskripsi' => 'deskripsi',
            'status_fasilitas' => 'status fasilitas',
            'foto' => 'foto fasilitas',
            'foto_url' => 'URL foto',
        ];
    }

    // Pesan galat berbahasa Indonesia.
    // `foto.image`, `foto.types`, dan `foto.max` menimpa pesan bawaan Laravel
    // supaya user tahu pasti batasnya berapa (contoh: "maksimal 2 MB").
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute tidak boleh lebih dari :max karakter.',
            'foto.image' => 'Foto fasilitas harus berupa gambar yang valid.',
            'foto.types' => 'Foto fasilitas hanya boleh berformat PNG, JPG, atau WebP.',
            'foto.max' => 'Ukuran foto fasilitas maksimal 2 MB.',
            'foto_url.url' => 'URL foto harus berupa alamat yang valid, contoh https://contoh.com/foto.jpg.',
        ];
    }
}

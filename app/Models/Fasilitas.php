<?php

// Dipakai oleh: DatabaseSeeder, KatalogController, AdminController (CRUD fasilitas),
//               dan view katalog/detail room untuk menampilkan gambar & deskripsi.
// Fungsi: model kategori fasilitas (PlayStation / Nintendo / Netflix Room).
// Relasi: 1 fasilitas -> banyak Room (Room.php).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Fasilitas extends Model
{
    use HasFactory;

    protected $table = 'fasilitas';

    protected $primaryKey = 'id_fasilitas';

    public $timestamps = false;

    // `tagline` dan `foto` boleh diisi lewat create()/update() dari form admin.
    protected $fillable = ['nama_fasilitas', 'tagline', 'deskripsi', 'foto', 'status_fasilitas'];

    // Mengubah nilai kolom `foto` (path storage atau URL penuh) jadi URL siap dipakai <img>.
    // Dipakai view katalog; null artinya belum ada foto -> pakai gambarBawaan().
    public function fotoUrl(): ?string
    {
        if (! $this->foto) {
            return null;
        }

        // URL eksternal dibiarkan; path lokal diubah jadi URL disk `public` (butuh storage:link).
        return str_starts_with($this->foto, 'http') ? $this->foto : Storage::disk('public')->url($this->foto);
    }

    /**
     * Gambar bawaan dari public/img, dipakai kalau fasilitas belum punya foto.
     */
    // Dicari dari nama fasilitas: "PlayStation" -> img/playstationroom.png.
    public function gambarBawaan(): string
    {
        $slug = str_replace('-', '', Str::slug($this->nama_fasilitas));
        // Kalau slug sudah berakhiran "room" tidak ditambah lagi (Netflix Room -> netflixroom.png).
        $nama = 'img/' . (str_ends_with($slug, 'room') ? $slug : $slug . 'room') . '.png';

        // Fallback terakhir: logo consoleicon.webp supaya <img> tidak pernah kosong.
        return file_exists(public_path($nama)) ? asset($nama) : asset('img/consoleicon.webp');
    }

    // Satu-satunya method yang dipanggil view: pilih foto upload, kalau tidak ada pakai gambar bawaan.
    public function gambar(): string
    {
        return $this->fotoUrl() ?? $this->gambarBawaan();
    }

    // Relasi 1 fasilitas -> banyak room; dipakai KatalogController untuk memuat room per kategori.
    public function room(): HasMany
    {
        return $this->hasMany(Room::class, 'id_fasilitas', 'id_fasilitas');
    }
}

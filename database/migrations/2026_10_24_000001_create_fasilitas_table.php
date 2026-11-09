<?php

// Jalankan: php artisan migrate        (dari folder project, setelah tabel `pengguna` ada)
// Fungsi: membuat tabel `fasilitas` (jenis room: PlayStation / Nintendo / Netflix).
//         Tabel ini sengaja TIDAK punya kolom harga — harga ditentukan per room
//         di tabel `room` (lihat `create_room_table`), karena tiap room bisa beda harga.
//         Kolom `tagline` ada di sini karena hanya dipakai card katalog (bukan data terpisah).
// Hasil: tabel `fasilitas` terbentuk.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // php artisan migrate
    public function up(): void
    {
        Schema::create('fasilitas', function (Blueprint $table): void {
            $table->integer('id_fasilitas', true);   // primary key auto increment
            $table->string('nama_fasilitas', 50);    // contoh: "PlayStation", "Nintendo", "Netflix"
            $table->string('tagline', 100)->nullable(); // teks pendek gaya tulisan tangan di card katalog, boleh kosong
            $table->text('deskripsi')->nullable();   // teks panjang untuk halaman katalog
            $table->string('foto', 255)->nullable(); // path file di storage/app/public, null = pakai gambar bawaan
            $table->enum('status_fasilitas', ['aktif', 'nonaktif'])->default('aktif'); // fasilitas nonaktif tidak tampil di katalog
        });
    }

    // php artisan migrate:rollback   (DROP TABLE fasilitas)
    public function down(): void
    {
        Schema::dropIfExists('fasilitas');
    }
};

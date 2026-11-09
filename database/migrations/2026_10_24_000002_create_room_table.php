<?php

// Jalankan: php artisan migrate        (dari folder project, setelah tabel `fasilitas` ada)
// Fungsi: membuat tabel `room` (unit fisik per fasilitas) + foreign key ke `fasilitas`.
//         `harga_per_jam` disimpan di sini karena harga ditentukan per room (Reguler/VIP/VVIP),
//         bukan per fasilitas. Tabel `fasilitas` sengaja tidak punya kolom harga.
// Hasil: tabel `room` terbentuk, index `idx_room_fasilitas`, FK `fk_room_fasilitas`,
//        CHECK kapasitas >= 1 dan CHECK harga_per_jam >= 0.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // php artisan migrate
    public function up(): void
    {
        Schema::create('room', function (Blueprint $table): void {
            $table->integer('id_room', true);        // primary key
            $table->integer('id_fasilitas');         // FK ke fasilitas.id_fasilitas
            $table->string('nama_room', 50);         // contoh: "PlayStation Reguler 1"
            $table->integer('kapasitas');            // jumlah orang
            // Harga sewa per jam untuk room ini. Satu-satunya sumber harga di sistem,
            // dibaca lewat Room::hargaEfektif(). Tiap room bisa beda harga (Reguler/VIP/VVIP).
            $table->decimal('harga_per_jam', 10, 2);
            // Status operasional room, diubah oleh CheckinService saat check-in/check-out.
            $table->enum('status_room', ['tersedia', 'dibooking', 'digunakan', 'tidak_aktif'])->default('tersedia');
            $table->index('id_fasilitas', 'idx_room_fasilitas'); // mempercepat query "room milik fasilitas X"
            // restrictOnDelete: fasilitas tidak bisa dihapus selama masih punya room,
            // mencegah data room menggantung tanpa induk.
            $table->foreign('id_fasilitas', 'fk_room_fasilitas')
                ->references('id_fasilitas')->on('fasilitas')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        // Room kapasitas 0 atau minus tidak masuk akal, jadi ditolak database.
        DB::statement('ALTER TABLE room ADD CONSTRAINT chk_room_kapasitas CHECK (kapasitas >= 1)');

        // Sama seperti kapasitas: harga negatif ditolak di level database,
        // supaya tetap aman walau ada kode yang lupa memvalidasi.
        DB::statement('ALTER TABLE room ADD CONSTRAINT chk_room_harga CHECK (harga_per_jam >= 0)');
    }

    // php artisan migrate:rollback   (DROP TABLE room)
    public function down(): void
    {
        Schema::dropIfExists('room');
    }
};

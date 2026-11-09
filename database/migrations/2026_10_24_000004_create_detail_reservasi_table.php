<?php

// Jalankan: php artisan migrate        (dari folder project, setelah `reservasi` dan `room` ada)
// Fungsi: membuat tabel `detail_reservasi` — satu baris = satu room pada satu slot jam.
// Hasil: tabel terbentuk + 2 FK + index `idx_detail_ketersediaan` (kunci cek bentrok jadwal).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // php artisan migrate
    public function up(): void
    {
        Schema::create('detail_reservasi', function (Blueprint $table): void {
            $table->integer('id_detail', true);   // primary key
            $table->integer('id_reservasi');      // FK ke reservasi (header)
            $table->integer('id_room');           // FK ke room yang dipesan
            $table->date('tanggal');              // tanggal main
            $table->time('jam_mulai');            // contoh: 13:00:00
            $table->integer('durasi_jam');        // 1..6 (ReservasiService::MAKS_DURASI)
            $table->time('jam_selesai');          // jam_mulai + durasi
            // Harga dikunci di sini supaya riwayat tidak berubah kalau harga room naik.
            $table->decimal('harga_per_jam', 10, 2);
            $table->decimal('subtotal', 12, 2);   // = durasi_jam * harga_per_jam
            // Diisi CheckinService saat admin melakukan check-in / check-out.
            $table->dateTime('waktu_checkin')->nullable();
            $table->dateTime('waktu_checkout')->nullable();
            $table->index('id_reservasi', 'idx_detail_reservasi'); // ambil semua baris milik 1 reservasi
            // Index ini yang membuat KetersediaanService cepat: cari slot terpakai
            // per room per tanggal tanpa full table scan.
            $table->index(['id_room', 'tanggal', 'jam_mulai', 'jam_selesai'], 'idx_detail_ketersediaan');
            $table->foreign('id_reservasi', 'fk_detail_reservasi')
                ->references('id_reservasi')->on('reservasi')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('id_room', 'fk_detail_room')
                ->references('id_room')->on('room')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        // Tiga aturan bisnis dijaga di level database:
        DB::statement('ALTER TABLE detail_reservasi ADD CONSTRAINT chk_detail_durasi CHECK (durasi_jam >= 1)');
        // Jam operasional 10:00-21:00, sama dengan KetersediaanService::JAM_BUKA / JAM_TUTUP.
        DB::statement("ALTER TABLE detail_reservasi ADD CONSTRAINT chk_detail_jam CHECK (jam_mulai >= '10:00:00' AND jam_selesai <= '21:00:00' AND jam_selesai > jam_mulai)");
        // Subtotal harus konsisten dengan harga x durasi, mencegah salah hitung tersimpan.
        DB::statement('ALTER TABLE detail_reservasi ADD CONSTRAINT chk_detail_subtotal CHECK (subtotal = durasi_jam * harga_per_jam)');
    }

    // php artisan migrate:rollback   (DROP TABLE detail_reservasi)
    public function down(): void
    {
        Schema::dropIfExists('detail_reservasi');
    }
};

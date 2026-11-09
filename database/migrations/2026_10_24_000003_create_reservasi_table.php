<?php

// Jalankan: php artisan migrate        (dari folder project, setelah tabel `pengguna` ada)
// Fungsi: membuat tabel `reservasi` (header transaksi booking) + FK ke `pengguna`.
// Hasil: tabel `reservasi` terbentuk, unique `uq_reservasi_kode`, FK `fk_reservasi_pengguna`, CHECK total >= 0.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // php artisan migrate
    public function up(): void
    {
        Schema::create('reservasi', function (Blueprint $table): void {
            $table->integer('id_reservasi', true);   // primary key
            $table->integer('id_pengguna');          // FK ke pengguna.id_pengguna (pemesan)
            // Kode yang ditunjukkan ke pelanggan, format RM-YYYYMMDD-NNN.
            // Dibuat ReservasiService::buatKodeBooking(); unique mencegah duplikat.
            $table->string('kode_booking', 30)->unique('uq_reservasi_kode');
            $table->dateTime('tanggal_dibuat')->useCurrent(); // otomatis diisi waktu insert
            // Batas bayar = tanggal_dibuat + 60 menit (ReservasiService::MENIT_BATAS_PEMBAYARAN).
            // Lewat dari ini, command reservasi:batalkan-kedaluwarsa akan membatalkan reservasi.
            $table->dateTime('batas_pembayaran');
            $table->decimal('total_reservasi', 12, 2)->default(0); // diisi ulang setelah detail dihitung
            // Alur status normal:
            // menunggu_pembayaran -> dikonfirmasi -> check_in -> selesai
            // (atau ke `dibatalkan` dari mana saja)
            $table->enum('status_reservasi', ['menunggu_pembayaran', 'dikonfirmasi', 'check_in', 'selesai', 'dibatalkan'])
                ->default('menunggu_pembayaran');
            $table->index('id_pengguna', 'idx_reservasi_pengguna'); // untuk halaman "reservasi saya"
            // Index gabungan: dipakai query pembatalan otomatis
            // (status = menunggu_pembayaran AND batas_pembayaran < sekarang).
            $table->index(['status_reservasi', 'batas_pembayaran'], 'idx_reservasi_status');
            // restrictOnDelete: user tidak bisa dihapus selama masih punya reservasi.
            $table->foreign('id_pengguna', 'fk_reservasi_pengguna')
                ->references('id_pengguna')->on('pengguna')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        // Total reservasi tidak boleh negatif walau ada bug di kode perhitungan.
        DB::statement('ALTER TABLE reservasi ADD CONSTRAINT chk_reservasi_total CHECK (total_reservasi >= 0)');
    }

    // php artisan migrate:rollback   (DROP TABLE reservasi)
    public function down(): void
    {
        Schema::dropIfExists('reservasi');
    }
};

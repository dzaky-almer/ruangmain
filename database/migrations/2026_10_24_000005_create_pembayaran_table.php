<?php

// Jalankan: php artisan migrate        (dari folder project, setelah `reservasi` dan `pengguna` ada)
// Fungsi: membuat tabel `pembayaran` (riwayat DP / pelunasan, QRIS / cash).
// Hasil: tabel terbentuk + 2 FK + index `idx_pembayaran_reservasi` + CHECK jumlah > 0.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // php artisan migrate
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table): void {
            $table->integer('id_pembayaran', true);  // primary key
            $table->integer('id_reservasi');         // FK ke reservasi yang dibayar
            // Diisi hanya untuk pembayaran cash yang diverifikasi admin (null untuk QRIS).
            $table->integer('diverifikasi_oleh')->nullable();
            // dp = uang muka 50% (PembayaranService::PERSEN_DP), pelunasan = sisa/full.
            $table->enum('jenis_pembayaran', ['dp', 'pelunasan']);
            $table->enum('metode_pembayaran', ['qris', 'cash']);
            $table->decimal('jumlah', 12, 2);
            // pending -> sukses (via callback Midtrans atau verifikasi admin)
            //         -> gagal
            //         -> dibatalkan (reservasi kedaluwarsa)
            $table->enum('status_pembayaran', ['pending', 'sukses', 'gagal', 'dibatalkan'])->default('pending');
            // Kunci pencocokan webhook: MidtransGateway mengembalikan
            // "{kode_booking}-{id_pembayaran}", dan PembayaranService::terimaCallback()
            // mencari baris pending lewat kolom ini.
            $table->string('id_transaksi_gateway', 100)->nullable();
            $table->dateTime('waktu_bayar')->nullable();      // diisi saat status jadi sukses
            $table->dateTime('waktu_verifikasi')->nullable(); // diisi saat admin verifikasi
            // Index gabungan: dipakai untuk hitung total dibayar & cari pembayaran pending.
            $table->index(['id_reservasi', 'status_pembayaran'], 'idx_pembayaran_reservasi');
            $table->index('diverifikasi_oleh', 'idx_pembayaran_verifikator');
            $table->foreign('id_reservasi', 'fk_pembayaran_reservasi')
                ->references('id_reservasi')->on('reservasi')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('diverifikasi_oleh', 'fk_pembayaran_verifikator')
                ->references('id_pengguna')->on('pengguna')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        // Pembayaran nol atau negatif tidak valid.
        DB::statement('ALTER TABLE pembayaran ADD CONSTRAINT chk_pembayaran_jumlah CHECK (jumlah > 0)');
    }

    // php artisan migrate:rollback   (DROP TABLE pembayaran)
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};

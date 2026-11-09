<?php

// Jalankan: php artisan migrate        (dari folder project, butuh database `ruangmain` sudah dibuat)
// Fungsi: membuat tabel `pengguna` (akun admin & pelanggan) + unique index `uq_pengguna_email`.
// Hasil: tabel `pengguna` terbentuk, isinya masih kosong.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // php artisan migrate
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $table): void {
            $table->integer('id_pengguna', true); // primary key auto increment
            $table->string('nama', 100);
            // Login pakai email ini. Unique index `uq_pengguna_email` mencegah email ganda.
            $table->string('email', 100)->unique('uq_pengguna_email');
            $table->string('no_hp', 20);
            // Disimpan sebagai hash (cast `password => hashed` di Pengguna.php).
            $table->string('password', 255);
            // `peran` dipakai PeranMiddleware untuk guard route `peran:admin` / `peran:pelanggan`,
            // dan tidak masuk $fillable Pengguna (seeder pakai forceCreate).
            $table->enum('peran', ['pelanggan', 'admin'])->default('pelanggan');
            // `nonaktif` = tidak boleh login (dicek AuthController::login()).
            $table->enum('status_akun', ['aktif', 'nonaktif'])->default('aktif');
        });
    }

    // php artisan migrate:rollback   (batalkan 1 batch terakhir)
    public function down(): void
    {
        Schema::dropIfExists('pengguna');
    }
};

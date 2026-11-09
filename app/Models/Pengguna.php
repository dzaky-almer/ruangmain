<?php

// File ini mengatur data akun: admin dan pelanggan. Datanya tersimpan di tabel `pengguna`.
// Dipakai di: proses login dan daftar, pengecekan peran, serta halaman pesanan.
// Relasinya ke dua file model lain: Reservasi.php dan Pembayaran.php.

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

// Mewarisi Authenticatable supaya bisa dipakai sistem login bawaan Laravel (config/auth.php).
class Pengguna extends Authenticatable
{
    // HasFactory untuk membuat data contoh saat pengujian. Notifiable untuk mengirim notifikasi.
    use HasFactory, Notifiable;

    // Daftar nama peran. Dipakai untuk mengecek apakah akun ini admin atau pelanggan.
    // Nilainya harus sama dengan pilihan enum di file migration.
    public const PERAN_PELANGGAN = 'pelanggan';

    public const PERAN_ADMIN = 'admin';

    // Daftar status akun. Kalau bukan 'aktif', pemilik akun tidak boleh login.
    public const STATUS_AKTIF = 'aktif';

    public const STATUS_NONAKTIF = 'nonaktif';

    // Nama tabel aslinya `pengguna`, bukan bentuk jamak otomatis Laravel.
    protected $table = 'pengguna';

    // Nama kolom kunci utama tabel ini.
    protected $primaryKey = 'id_pengguna';

    // Tabel ini tidak punya kolom created_at dan updated_at, jadi dimatikan.
    public $timestamps = false;

    // Hanya 4 kolom ini yang boleh diisi sekaligus lewat create().
    // Kolom `peran` dan `status_akun` sengaja tidak termasuk, sehingga seeder harus pakai forceCreate().
    protected $fillable = ['nama', 'email', 'no_hp', 'password'];

    // Kolom password disembunyikan saat data diubah menjadi array atau JSON.
    protected $hidden = ['password'];

    // casts() adalah cara Laravel versi baru menggantikan properti $casts.
    protected function casts(): array
    {
        return [
            // Password otomatis di-hash saat disimpan, jadi controller tidak perlu Hash::make().
            'password' => 'hashed',
        ];
    }

    // Relasi satu pengguna ke banyak reservasi, datanya dari file Reservasi.php.
    // Dipakai admin untuk melihat riwayat pesanan pelanggan.
    public function reservasi(): HasMany
    {
        return $this->hasMany(Reservasi::class, 'id_pengguna', 'id_pengguna');
    }

    // Relasi satu admin ke banyak pembayaran yang dia verifikasi, datanya dari file Pembayaran.php.
    // Penghubungnya kolom pembayaran.diverifikasi_oleh.
    public function pembayaranDiverifikasi(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'diverifikasi_oleh', 'id_pengguna');
    }
}

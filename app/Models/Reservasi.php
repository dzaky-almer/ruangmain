<?php

// Dipakai oleh: ReservasiService (buat kode booking), CheckoutController, PembayaranController,
//               AdminController, LaporanService, ReservasiPembatalan command, dan seeder.
// Fungsi: model header reservasi — 1 baris = 1 transaksi pemesanan milik 1 pelanggan.
// Relasi: milik 1 Pengguna; punya banyak DetailReservasi dan banyak Pembayaran.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservasi extends Model
{
    use HasFactory;

    protected $table = 'reservasi';

    protected $primaryKey = 'id_reservasi';

    public $timestamps = false;

    protected $fillable = ['id_pengguna', 'kode_booking', 'tanggal_dibuat', 'batas_pembayaran', 'total_reservasi', 'status_reservasi'];

    protected function casts(): array
    {
        return [
            // Dua kolom ini jadi objek Carbon, jadi bisa ->format() / ->diffInMinutes().
            'tanggal_dibuat' => 'datetime',
            'batas_pembayaran' => 'datetime',
            'total_reservasi' => 'decimal:2',
        ];
    }

    // Relasi ke Pengguna.php: pemilik reservasi ($reservasi->pengguna->nama).
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'id_pengguna', 'id_pengguna');
    }

    // Relasi ke DetailReservasi.php: daftar room + jam yang dipesan pada reservasi ini.
    public function detailReservasi(): HasMany
    {
        return $this->hasMany(DetailReservasi::class, 'id_reservasi', 'id_reservasi');
    }

    // Relasi ke Pembayaran.php: DP / pelunasan milik reservasi ini.
    // PembayaranService menjumlahkan baris berstatus 'sukses' dari relasi ini.
    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'id_reservasi', 'id_reservasi');
    }
}

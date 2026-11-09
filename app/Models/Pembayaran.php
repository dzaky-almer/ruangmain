<?php

// Dipakai oleh: PembayaranService (buat & ubah status), PembayaranCallbackController
//               (callback Midtrans), CheckoutController, AdminController (verifikasi cash),
//               dan LaporanService (hitung pendapatan).
// Fungsi: model pembayaran — 1 baris = 1 transaksi DP atau pelunasan (qris/cash).
// Relasi: milik 1 Reservasi; opsional milik 1 Pengguna (admin verifikator).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran';

    protected $primaryKey = 'id_pembayaran';

    public $timestamps = false;

    protected $fillable = ['id_reservasi', 'diverifikasi_oleh', 'jenis_pembayaran', 'metode_pembayaran', 'jumlah', 'status_pembayaran', 'id_transaksi_gateway', 'waktu_bayar', 'waktu_verifikasi'];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            // Null = pembayaran masih pending.
            'waktu_bayar' => 'datetime',
            'waktu_verifikasi' => 'datetime',
        ];
    }

    // Relasi ke Reservasi.php: PembayaranService memeriksa status reservasi sebelum
    // menandai pembayaran sukses.
    public function reservasi(): BelongsTo
    {
        return $this->belongsTo(Reservasi::class, 'id_reservasi', 'id_reservasi');
    }

    // Relasi ke Pengguna.php lewat kolom `diverifikasi_oleh`; null kalau metode qris
    // (verifikasi otomatis dari gateway, bukan admin).
    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diverifikasi_oleh', 'id_pengguna');
    }
}

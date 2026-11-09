<?php

// Dipakai oleh: ReservasiService, KetersediaanService (cek bentrok), CheckinService
//               (isi waktu_checkin/waktu_checkout), dan tampilan detail pesanan.
// Fungsi: model baris pesanan — 1 baris = 1 room pada 1 slot jam tertentu.
// Relasi: milik 1 Reservasi; milik 1 Room.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailReservasi extends Model
{
    use HasFactory;

    protected $table = 'detail_reservasi';

    protected $primaryKey = 'id_detail';

    public $timestamps = false;

    protected $fillable = ['id_reservasi', 'id_room', 'tanggal', 'jam_mulai', 'durasi_jam', 'jam_selesai', 'harga_per_jam', 'subtotal', 'waktu_checkin', 'waktu_checkout'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            // Null berarti belum check-in / belum check-out.
            'waktu_checkin' => 'datetime',
            'waktu_checkout' => 'datetime',
            'harga_per_jam' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    // Relasi ke Reservasi.php: header pemilik baris ini (status, kode booking, pemesan).
    public function reservasi(): BelongsTo
    {
        return $this->belongsTo(Reservasi::class, 'id_reservasi', 'id_reservasi');
    }

    // Relasi ke Room.php: room yang dipesan; KetersediaanService juga menelusuri arah ini
    // untuk mencari slot terpakai per room per tanggal.
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'id_room', 'id_room');
    }
}

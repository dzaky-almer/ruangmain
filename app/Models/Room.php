<?php

// Dipakai oleh: KatalogController, ReservasiService, PembayaranService, CheckinService,
//               AdminController, dan semua seeder (DatabaseSeeder/DemoSeeder/RiwayatSeeder).
// Fungsi: model room — unit yang benar-benar disewa per jam (Reguler/VIP/VVIP).
// Relasi: milik 1 Fasilitas; punya banyak DetailReservasi.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $table = 'room';

    protected $primaryKey = 'id_room';

    public $timestamps = false;

    protected $fillable = ['id_fasilitas', 'nama_room', 'kapasitas', 'harga_per_jam', 'status_room'];

    // casts() menggantikan properti $casts: hasil query tetap string desimal.
    protected function casts(): array
    {
        return [
            'harga_per_jam' => 'decimal:2',
        ];
    }

    /** Harga per jam room, satu-satunya sumber harga. */
    // Harga hanya ada di room (bukan di fasilitas), karena tiap room bisa beda harga.
    // ReservasiService & seeder selalu memanggil method ini, bukan kolom lain.
    public function hargaEfektif(): float
    {
        return (float) $this->harga_per_jam;
    }

    // Relasi ke Fasilitas.php: nama kategori ("PlayStation") diambil lewat $room->fasilitas->nama_fasilitas.
    public function fasilitas(): BelongsTo
    {
        return $this->belongsTo(Fasilitas::class, 'id_fasilitas', 'id_fasilitas');
    }

    // Relasi ke DetailReservasi.php: KetersediaanService memakainya untuk mencari slot bentrok.
    public function detailReservasi(): HasMany
    {
        return $this->hasMany(DetailReservasi::class, 'id_room', 'id_room');
    }
}

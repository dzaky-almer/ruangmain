<?php

namespace Database\Factories;

use App\Models\DetailReservasi;
use App\Models\Reservasi;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DetailReservasi> */
class DetailReservasiFactory extends Factory
{
    protected $model = DetailReservasi::class;

    public function definition(): array
    {
        return [
            'id_reservasi' => Reservasi::factory(),
            'id_room' => Room::factory(),
            'tanggal' => now()->addDay()->toDateString(),
            'jam_mulai' => '10:00:00',
            'durasi_jam' => 2,
            'jam_selesai' => '12:00:00',
            'harga_per_jam' => 25000,
            'subtotal' => 50000,
            'waktu_checkin' => null,
            'waktu_checkout' => null,
        ];
    }
}

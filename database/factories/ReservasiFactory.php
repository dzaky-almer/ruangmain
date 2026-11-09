<?php

namespace Database\Factories;

use App\Models\Pengguna;
use App\Models\Reservasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reservasi> */
class ReservasiFactory extends Factory
{
    protected $model = Reservasi::class;

    public function definition(): array
    {
        $dibuat = now();

        return [
            'id_pengguna' => Pengguna::factory(),
            'kode_booking' => 'RM-'.$dibuat->format('Ymd').'-'.fake()->unique()->numerify('###'),
            'tanggal_dibuat' => $dibuat,
            'batas_pembayaran' => $dibuat->copy()->addHour(),
            'total_reservasi' => 50000,
            'status_reservasi' => 'menunggu_pembayaran',
        ];
    }
}

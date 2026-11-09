<?php

namespace Database\Factories;

use App\Models\Fasilitas;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'id_fasilitas' => Fasilitas::factory(),
            'nama_room' => 'Room '.fake()->unique()->numerify('###'),
            'kapasitas' => fake()->numberBetween(1, 10),
            'harga_per_jam' => fake()->numberBetween(10000, 50000),
            'status_room' => 'tersedia',
        ];
    }
}

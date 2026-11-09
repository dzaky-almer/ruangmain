<?php

namespace Database\Factories;

use App\Models\Fasilitas;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Fasilitas> */
class FasilitasFactory extends Factory
{
    protected $model = Fasilitas::class;

    public function definition(): array
    {
        return [
            'nama_fasilitas' => fake()->unique()->words(2, true),
            'deskripsi' => fake()->sentence(),
            'foto' => null,
            'status_fasilitas' => 'aktif',
        ];
    }
}

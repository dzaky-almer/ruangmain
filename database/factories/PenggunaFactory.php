<?php

namespace Database\Factories;

use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Pengguna> */
class PenggunaFactory extends Factory
{
    protected $model = Pengguna::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'no_hp' => '08'.fake()->numerify('##########'),
            'password' => Hash::make('password'),
            'peran' => 'pelanggan',
            'status_akun' => 'aktif',
        ];
    }
}

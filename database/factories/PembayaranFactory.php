<?php

namespace Database\Factories;

use App\Models\Pembayaran;
use App\Models\Reservasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pembayaran> */
class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    public function definition(): array
    {
        return [
            'id_reservasi' => Reservasi::factory(),
            'diverifikasi_oleh' => null,
            'jenis_pembayaran' => 'pelunasan',
            'metode_pembayaran' => 'qris',
            'jumlah' => 50000,
            'status_pembayaran' => 'pending',
            'id_transaksi_gateway' => 'MOCK-'.fake()->unique()->uuid(),
            'waktu_bayar' => null,
            'waktu_verifikasi' => null,
        ];
    }
}

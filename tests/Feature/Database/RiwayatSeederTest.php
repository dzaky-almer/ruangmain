<?php

namespace Tests\Feature\Database;

use App\Models\DetailReservasi;
use App\Models\Pengguna;
use App\Models\Reservasi;
use Database\Seeders\RiwayatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RiwayatSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_idempoten_dan_tanggal_riwayat_selalu_lampau(): void
    {
        $this->seed();
        $this->seed(RiwayatSeeder::class);
        $this->seed(RiwayatSeeder::class);

        $this->assertSame(3 + RiwayatSeeder::JUMLAH_PELANGGAN, Pengguna::query()->count());
        $this->assertSame(
            ['dibatalkan', 'selesai'],
            Reservasi::query()->where('kode_booking', 'like', 'RM-%')->distinct()
                ->pluck('status_reservasi')->filter(fn ($s) => in_array($s, ['dibatalkan', 'selesai'], true))
                ->unique()->sort()->values()->all(),
        );

        $batas = Carbon::today()->subDays(8);

        foreach (DetailReservasi::query()->get() as $baris) {
            $this->assertTrue($baris->tanggal->lessThanOrEqualTo($batas));
        }
    }
}
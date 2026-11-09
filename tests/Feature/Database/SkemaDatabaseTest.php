<?php

namespace Tests\Feature\Database;

use App\Models\Pengguna;
use App\Models\Room;
use Database\Seeders\RiwayatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkemaDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_skema_dan_data_awal_ruangmain_tersedia(): void
    {
        foreach (['pengguna', 'fasilitas', 'room', 'reservasi', 'detail_reservasi', 'pembayaran'] as $tabel) {
            $this->assertTrue(Schema::hasTable($tabel));
        }

        $this->assertDatabaseCount('pengguna', 3 + RiwayatSeeder::JUMLAH_PELANGGAN);
        $this->assertDatabaseCount('fasilitas', 3);
        $this->assertDatabaseCount('room', 13);
        $admin = Pengguna::query()->where('email', 'admin@ruangmain.test')->firstOrFail();

        $this->assertTrue(Hash::check('Admin123!', $admin->password));
    }

    public function test_relasi_room_ke_fasilitas_berfungsi(): void
    {
        $room = Room::query()->with('fasilitas')->firstOrFail();

        $this->assertNotNull($room->fasilitas);
        $this->assertSame('PlayStation', $room->fasilitas->nama_fasilitas);
    }
}

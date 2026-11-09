<?php

namespace Tests\Feature\Admin;

use App\Models\DetailReservasi;
use App\Models\Fasilitas;
use App\Models\Pengguna;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dapat_menambah_dan_memperbarui_room(): void
    {
        $admin = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
        $fasilitas = Fasilitas::factory()->create();

        $this->actingAs($admin)->post(route('admin.room.store'), [
            'id_fasilitas' => $fasilitas->id_fasilitas,
            'nama_room' => 'PS-04',
            'kapasitas' => 4,
            'harga_per_jam' => 35000,
            'status_room' => 'tersedia',
        ])->assertRedirect();

        $room = Room::query()->where('nama_room', 'PS-04')->firstOrFail();

        $this->assertDatabaseHas('room', ['id_room' => $room->id_room, 'harga_per_jam' => 35000]);

        $this->actingAs($admin)->put(route('admin.room.update', $room), [
            'id_fasilitas' => $fasilitas->id_fasilitas,
            'nama_room' => 'PS-04 VIP',
            'kapasitas' => 5,
            'harga_per_jam' => 40000,
            'status_room' => 'tidak_aktif',
        ])->assertRedirect(route('admin.room.index'));

        $this->assertDatabaseHas('room', ['id_room' => $room->id_room, 'nama_room' => 'PS-04 VIP', 'kapasitas' => 5, 'status_room' => 'tidak_aktif', 'harga_per_jam' => 40000]);
    }

    public function test_harga_room_wajib_diisi(): void
    {
        $admin = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
        $fasilitas = Fasilitas::factory()->create();

        $this->actingAs($admin)->post(route('admin.room.store'), [
            'id_fasilitas' => $fasilitas->id_fasilitas,
            'nama_room' => 'Tanpa Harga',
            'kapasitas' => 4,
            'harga_per_jam' => '',
            'status_room' => 'tersedia',
        ])->assertSessionHasErrors('harga_per_jam');

        $this->assertDatabaseMissing('room', ['nama_room' => 'Tanpa Harga']);
    }

    public function test_room_dengan_reservasi_tidak_dapat_dihapus(): void
    {
        $admin = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
        $room = Room::factory()->create();
        DetailReservasi::factory()->create(['id_room' => $room->id_room]);

        $this->actingAs($admin)->delete(route('admin.room.destroy', $room))->assertSessionHas('galat');

        $this->assertDatabaseHas('room', ['id_room' => $room->id_room]);
    }

    public function test_kapasitas_room_harus_minimal_satu(): void
    {
        $admin = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
        $fasilitas = Fasilitas::factory()->create();

        $this->actingAs($admin)->from(route('admin.room.create'))->post(route('admin.room.store'), [
            'id_fasilitas' => $fasilitas->id_fasilitas,
            'nama_room' => 'PS-04',
            'kapasitas' => 0,
            'status_room' => 'tersedia',
        ])->assertSessionHasErrors('kapasitas');
    }
}

<?php

namespace Tests\Feature\Pelanggan;

use App\Models\DetailReservasi;
use App\Models\Fasilitas;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\Room;
use App\Services\KetersediaanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_pelanggan_melihat_daftar_fasilitas_aktif_dan_fasilitas_nonaktif_tersembunyi(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $aktif = Fasilitas::factory()->create(['nama_fasilitas' => 'PlayStation', 'status_fasilitas' => 'aktif']);
        Fasilitas::factory()->create(['nama_fasilitas' => 'Fasilitas Lama', 'status_fasilitas' => 'nonaktif']);
        Room::factory()->create(['id_fasilitas' => $aktif->id_fasilitas, 'nama_room' => 'PS Room 01']);

        $respons = $this->actingAs($pelanggan)->get(route('pelanggan.katalog'))->assertOk();

        $respons->assertSee('PlayStation');
        $respons->assertDontSee('Fasilitas Lama');
    }

    public function test_room_tidak_aktif_tidak_muncul_sebagai_pilihan(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $fasilitas = Fasilitas::factory()->create();
        Room::factory()->create(['id_fasilitas' => $fasilitas->id_fasilitas, 'nama_room' => 'PS Room 01']);
        $roomMati = Room::factory()->create([
            'id_fasilitas' => $fasilitas->id_fasilitas,
            'nama_room' => 'PS Room 99',
            'status_room' => 'tidak_aktif',
        ]);

        $this->actingAs($pelanggan)->get(route('pelanggan.katalog'))->assertOk()->assertDontSee('PS Room 99');

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.room.detail', $roomMati))
            ->assertOk()
            ->assertSee('PS Room 01')
            ->assertSee('Tidak aktif');

        $this->actingAs($pelanggan)->postJson(route('pelanggan.ketersediaan'), [
            'id_room' => $roomMati->id_room,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:00',
        ])->assertOk()->assertJson(['bentrok' => true, 'status_slot' => []]);
    }

    public function test_katalog_menampilkan_rentang_harga_kalau_harga_room_beda(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $fasilitas = Fasilitas::factory()->create(['nama_fasilitas' => 'PlayStation']);
                Room::factory()->create(['id_fasilitas' => $fasilitas->id_fasilitas, 'nama_room' => 'PS Reguler', 'harga_per_jam' => 20000]);
                Room::factory()->create(['id_fasilitas' => $fasilitas->id_fasilitas, 'nama_room' => 'PS VIP', 'harga_per_jam' => 35000]);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.katalog'))
            ->assertOk()
            ->assertSee('Rp20.000')
            ->assertSee('Rp35.000');
    }

    public function test_jadwal_menempel_tepat_tidak_dianggap_bentrok(): void
    {
        $room = Room::factory()->create();
        $tanggal = now()->toDateString();
        $this->buatJadwal($room, $tanggal, '12:00', '14:00');

        $service = app(KetersediaanService::class);

        $this->assertFalse($service->bentrok($room, $tanggal, '14:00', '16:00'), '14.00 selesai dan 14.00 mulai bukan bentrok.');
        $this->assertTrue($service->bentrok($room, $tanggal, '13:00', '15:00'), 'Interval yang beririsan harus dianggap bentrok.');
        $this->assertSame('dipesan', $service->statusSlot($room, $tanggal)['13:00']);
        $this->assertSame('tersedia', $service->statusSlot($room, $tanggal)['14:00']);
    }

    public function test_reservasi_dibatalkan_tidak_menahan_slot(): void
    {
        $room = Room::factory()->create();
        $tanggal = now()->toDateString();
        $this->buatJadwal($room, $tanggal, '10:00', '12:00', 'dibatalkan');

        $service = app(KetersediaanService::class);

        $this->assertFalse($service->bentrok($room, $tanggal, '10:00', '12:00'));
        $this->assertSame('tersedia', $service->statusSlot($room, $tanggal)['10:00']);
    }

    public function test_endpoint_cek_ketersediaan_menolak_jam_di_luar_operasional(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $room = Room::factory()->create();

        $this->actingAs($pelanggan)->postJson(route('pelanggan.ketersediaan'), [
            'id_room' => $room->id_room,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '21:00',
            'jam_selesai' => '22:00',
        ])->assertStatus(422)->assertJsonValidationErrors('jam_mulai');

        $this->actingAs($pelanggan)->postJson(route('pelanggan.ketersediaan'), [
            'id_room' => $room->id_room,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '10:30',
            'jam_selesai' => '11:30',
        ])->assertStatus(422)->assertJsonValidationErrors('jam_mulai');
    }

    public function test_detail_room_menyediakan_tombol_pilih_room_ke_halaman_jadwal(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $room = Room::factory()->create();
        $tanggal = now()->addDay()->toDateString();

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.room.detail', ['room' => $room, 'tanggal' => $tanggal]))
            ->assertOk()
            ->assertSee('Pilih Room')
            ->assertSee(e(route('pelanggan.room.jadwal', [
                'room' => $room,
                'tanggal' => $tanggal,
            ])), false);
    }

    public function test_kartu_room_menampilkan_status_dan_tautan_jadwal_per_room(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $fasilitas = Fasilitas::factory()->create();
        $room = Room::factory()->create(['id_fasilitas' => $fasilitas->id_fasilitas, 'nama_room' => 'PS Room 01']);
        $roomLain = Room::factory()->create(['id_fasilitas' => $fasilitas->id_fasilitas, 'nama_room' => 'PS Room 02']);

        $this->actingAs($pelanggan)
                    ->get(route('pelanggan.room.detail', ['room' => $room, 'tanggal' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee('Room yang tersedia')
            ->assertSee(e(route('pelanggan.room.jadwal', ['room' => $room])), false)
            ->assertSee(e(route('pelanggan.room.jadwal', ['room' => $roomLain])), false)
            ->assertSee('data-tombol-pilih-room', false);
    }

    public function test_halaman_jadwal_room_dapat_dibuka_dan_menampilkan_slot(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $room = Room::factory()->create(['nama_room' => 'PS Room 01']);
        $tanggal = now()->addDay()->toDateString();

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.room.jadwal', ['room' => $room, 'tanggal' => $tanggal]))
            ->assertOk()
            ->assertSee('PS Room 01')
            ->assertSee('Slot jam')
            ->assertSee('Reservasi Room Ini')
            ->assertSee(e(route('pelanggan.reservasi.baru', [
                'id_room' => $room->id_room,
                'tanggal' => $tanggal,
                'jam_mulai' => '10:00',
                'durasi' => 2,
            ])), false);
    }

    public function test_halaman_jadwal_room_tidak_aktif_tidak_menawarkan_reservasi(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $room = Room::factory()->create(['status_room' => 'tidak_aktif']);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.room.jadwal', $room))
            ->assertOk()
            ->assertSee('data-tombol-room-mati', false)
            ->assertDontSee('Reservasi Room Ini</a>');
    }

    public function test_tamu_diarahkan_ke_login_saat_membuka_halaman_jadwal(): void
    {
        $room = Room::factory()->create();

        $this->get(route('pelanggan.room.jadwal', $room))->assertRedirect(route('login'));
    }

    public function test_detail_room_tidak_aktif_tidak_menawarkan_reservasi(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $room = Room::factory()->create(['status_room' => 'tidak_aktif']);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.room.detail', $room))
            ->assertOk()
            ->assertDontSee('Reservasi Room Ini');
    }

    public function test_room_penuh_pada_tanggal_tertentu_tidak_bisa_dipilih(): void
    {
        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $fasilitas = Fasilitas::factory()->create();
        $room = Room::factory()->create(['id_fasilitas' => $fasilitas->id_fasilitas, 'nama_room' => 'PS Room 01']);
        $roomKosong = Room::factory()->create(['id_fasilitas' => $fasilitas->id_fasilitas, 'nama_room' => 'PS Room 02']);
        $tanggal = now()->addDay()->toDateString();

        // Room 01 terisi penuh 10.00–21.00, jadi tidak ada slot kosong.
        $this->buatJadwal($room, $tanggal, '10', '21');

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.room.detail', ['room' => $room, 'tanggal' => $tanggal]))
            ->assertOk()
            ->assertSee('Room penuh')
            ->assertDontSee(e(route('pelanggan.room.jadwal', ['room' => $room, 'tanggal' => $tanggal])), false)
            ->assertSee(e(route('pelanggan.room.jadwal', ['room' => $roomKosong, 'tanggal' => $tanggal])), false);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.reservasi.baru', ['tanggal' => $tanggal]))
            ->assertOk()
            ->assertSee('PS Room 01', false)
            ->assertSee('· penuh', false);
    }

    public function test_route_pelanggan_hanya_untuk_peran_pelanggan(): void
    {
        $admin = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);

        $this->actingAs($admin)->get(route('pelanggan.katalog'))->assertForbidden();
    }

    public function test_jam_hari_ini_yang_sudah_lewat_tampil_tapi_tidak_bisa_dipilih(): void
    {
        Carbon::setTestNow(now()->setTime(19, 30));

        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $room = Room::factory()->create(['nama_room' => 'PS Room 01']);
        $hariIni = now()->toDateString();

        $service = app(KetersediaanService::class);
        $this->assertSame(20, $service->jamMulaiTerdekat($hariIni));
        $this->assertSame(KetersediaanService::JAM_BUKA, $service->jamMulaiTerdekat(now()->addDay()->toDateString()));

        // Slot lewat tetap dirender, tapi hanya 1 jam tersisa (20.00–21.00).
        $this->actingAs($pelanggan)
            ->get(route('pelanggan.room.jadwal', ['room' => $room, 'tanggal' => $hariIni]))
            ->assertOk()
            ->assertSee('10:00')  // slot jam lewat tetap tampil
            ->assertSee('19:00')
            ->assertSee('20:00')
            ->assertSee(e(route('pelanggan.reservasi.baru', [
                'id_room' => $room->id_room,
                'tanggal' => $hariIni,
                'jam_mulai' => '20:00',
                'durasi' => 1,
            ])), false);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.reservasi.baru', ['tanggal' => $hariIni]))
            ->assertOk()
            ->assertSee('value="20:00"', false);
    }

    public function test_jam_mulai_terdekat_dibatasi_jam_tutup_saat_malam(): void
    {
        $service = app(KetersediaanService::class);

        foreach ([21, 22, 23] as $jam) {
            Carbon::setTestNow(now()->setTime($jam, 15));

            $hariIni = now()->toDateString();

            $this->assertSame(KetersediaanService::JAM_TUTUP, $service->jamMulaiTerdekat($hariIni));
            $this->assertFalse($service->adaSisaJam($hariIni));
        }

        Carbon::setTestNow(now()->setTime(19, 15));
        $this->assertTrue($service->adaSisaJam(now()->toDateString()));
    }

    public function test_reservasi_dengan_jam_yang_sudah_lewat_ditolak(): void
    {
        Carbon::setTestNow(now()->setTime(19, 30));

        $pelanggan = Pengguna::factory()->create(['peran' => Pengguna::PERAN_PELANGGAN]);
        $room = Room::factory()->create();

        $this->actingAs($pelanggan)
            ->from(route('pelanggan.reservasi.baru'))
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [[
                    'id_room' => $room->id_room,
                    'tanggal' => now()->toDateString(),
                    'jam_mulai' => '10:00',
                    'durasi' => 1,
                ]],
            ])
            ->assertRedirect(route('pelanggan.reservasi.baru'))
            ->assertSessionHasErrors('items.0.jam_mulai');

        $this->assertSame(0, Reservasi::query()->count());
    }

    private function buatJadwal(Room $room, string $tanggal, string $mulai, string $selesai, string $status = 'dikonfirmasi'): void
    {
        $reservasi = Reservasi::factory()->create(['status_reservasi' => $status]);

        DetailReservasi::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'id_room' => $room->id_room,
            'tanggal' => $tanggal,
            'jam_mulai' => $mulai.':00',
            'jam_selesai' => $selesai.':00',
            'durasi_jam' => (int) substr($selesai, 0, 2) - (int) substr($mulai, 0, 2),
            'harga_per_jam' => 25000,
                        'subtotal' => 25000 * ((int) substr($selesai, 0, 2) - (int) substr($mulai, 0, 2)),
        ]);
    }
}

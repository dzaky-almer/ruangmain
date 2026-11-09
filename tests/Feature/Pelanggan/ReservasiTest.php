<?php

namespace Tests\Feature\Pelanggan;

use App\Models\DetailReservasi;
use App\Models\Fasilitas;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservasiTest extends TestCase
{
    use RefreshDatabase;

    private function pelanggan(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => 'pelanggan']);
    }

    private function room(float $harga = 20000, string $statusRoom = 'tersedia', string $statusFasilitas = 'aktif'): Room
        {
            $fasilitas = Fasilitas::factory()->create(['status_fasilitas' => $statusFasilitas]);

            return Room::factory()->create([
                'id_fasilitas' => $fasilitas->id_fasilitas,
                'harga_per_jam' => $harga,
                'status_room' => $statusRoom,
            ]);
        }

    public function test_pelanggan_dapat_membuat_reservasi_dan_nominal_dihitung_server(): void
    {
        $pelanggan = $this->pelanggan();
        $room = $this->room(25000);
        $tanggal = now()->addDay()->toDateString();

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [[
                    'id_room' => $room->id_room,
                    'tanggal' => $tanggal,
                    'jam_mulai' => '10:00',
                    'durasi' => 3,
                    'harga_per_jam' => 1,
                    'subtotal' => 1,
                ]],
                'total_reservasi' => 1,
            ])
            ->assertRedirect();

        $reservasi = Reservasi::firstOrFail();
        $detail = DetailReservasi::firstOrFail();

        $this->assertSame('menunggu_pembayaran', $reservasi->status_reservasi);
        $this->assertSame($pelanggan->id_pengguna, $reservasi->id_pengguna);
        $this->assertEquals(75000, (float) $reservasi->total_reservasi);
        $this->assertEquals(25000, (float) $detail->harga_per_jam);
        $this->assertEquals(75000, (float) $detail->subtotal);
        $this->assertSame('13:00:00', $detail->jam_selesai);
    }

    public function test_harga_reservasi_memakai_harga_room(): void
    {
        $pelanggan = $this->pelanggan();
            $fasilitas = Fasilitas::factory()->create(['status_fasilitas' => 'aktif']);
        $room = Room::factory()->create([
            'id_fasilitas' => $fasilitas->id_fasilitas,
            'harga_per_jam' => 35000,
            'status_room' => 'tersedia',
        ]);
        $tanggal = now()->addDay()->toDateString();

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [[
                    'id_room' => $room->id_room,
                    'tanggal' => $tanggal,
                    'jam_mulai' => '10:00',
                    'durasi' => 2,
                ]],
                'total_reservasi' => 1,
            ])
            ->assertRedirect();

        $detail = DetailReservasi::firstOrFail();

        $this->assertEquals(35000, (float) $detail->harga_per_jam);
        $this->assertEquals(70000, (float) $detail->subtotal);
    }

    public function test_bentrok_jadwal_ditolak(): void
    {
        $room = $this->room();
        $tanggal = now()->addDay()->toDateString();
                $lama = Reservasi::factory()->create([
            'id_pengguna' => $this->pelanggan()->id_pengguna,
            'status_reservasi' => 'dikonfirmasi',
        ]);

        DetailReservasi::factory()->create([
                    'id_reservasi' => $lama->id_reservasi,
                    'id_room' => $room->id_room,
                    'tanggal' => $tanggal,
                    'jam_mulai' => '10:00:00',
                    'jam_selesai' => '12:00:00',
                    'durasi_jam' => 2,
                ]);

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [['id_room' => $room->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '11:00', 'durasi' => 1]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(1, Reservasi::count());
    }

    public function test_jam_yang_masih_kosong_tapi_bentrok_durasi_ditolak(): void
    {
        $room = $this->room();
        $tanggal = now()->addDay()->toDateString();
        $lama = Reservasi::factory()->create([
            'id_pengguna' => $this->pelanggan()->id_pengguna,
            'status_reservasi' => 'dikonfirmasi',
        ]);

        DetailReservasi::factory()->create([
            'id_reservasi' => $lama->id_reservasi,
            'id_room' => $room->id_room,
            'tanggal' => $tanggal,
            'jam_mulai' => '19:00:00',
            'jam_selesai' => '21:00:00',
            'durasi_jam' => 2,
        ]);

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [['id_room' => $room->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '18:00', 'durasi' => 2]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(1, Reservasi::count());

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [['id_room' => $room->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '18:00', 'durasi' => 1]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(2, Reservasi::count());
    }

    public function test_slot_dibatalkan_dapat_dipesan_kembali(): void
    {
        $room = $this->room();
        $tanggal = now()->addDay()->toDateString();
        $lama = Reservasi::factory()->create([
            'id_pengguna' => $this->pelanggan()->id_pengguna,
            'status_reservasi' => 'dibatalkan',
        ]);

        DetailReservasi::factory()->create([
            'id_reservasi' => $lama->id_reservasi,
            'id_room' => $room->id_room,
            'tanggal' => $tanggal,
            'jam_mulai' => '10:00:00',
            'jam_selesai' => '12:00:00',
            'durasi_jam' => 2,
        ]);

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [['id_room' => $room->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '10:00', 'durasi' => 2]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Reservasi::count());
    }

    public function test_jam_di_luar_operasional_ditolak(): void
    {
        $room = $this->room();
        $tanggal = now()->addDay()->toDateString();
        $pelanggan = $this->pelanggan();

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [['id_room' => $room->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '09:00', 'durasi' => 1]],
            ])
            ->assertSessionHasErrors('items.*.jam_mulai');

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [['id_room' => $room->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '20:00', 'durasi' => 2]],
            ])
                            ->assertSessionHasErrors('items.*.durasi');

        $this->assertSame(0, Reservasi::count());
    }

    public function test_jam_tidak_penuh_dan_tanggal_lampau_ditolak(): void
    {
        $room = $this->room();

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [['id_room' => $room->id_room, 'tanggal' => now()->addDay()->toDateString(), 'jam_mulai' => '10:30', 'durasi' => 1]],
            ])
            ->assertSessionHasErrors('items.*.jam_mulai');

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [['id_room' => $room->id_room, 'tanggal' => now()->subDay()->toDateString(), 'jam_mulai' => '10:00', 'durasi' => 1]],
            ])
            ->assertSessionHasErrors('items.*.tanggal');
    }

    public function test_room_tidak_aktif_dan_fasilitas_nonaktif_ditolak(): void
    {
        $roomNonaktif = $this->room(20000, 'tidak_aktif');
        $roomFasilitasNonaktif = $this->room(20000, 'tersedia', 'nonaktif');
        $tanggal = now()->addDay()->toDateString();

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [['id_room' => $roomNonaktif->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '10:00', 'durasi' => 1]],
            ])
            ->assertSessionHasErrors('items');

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [['id_room' => $roomFasilitasNonaktif->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '10:00', 'durasi' => 1]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Reservasi::count());
    }

    public function test_dua_baris_room_sama_yang_bertabrakan_ditolak(): void
    {
        $room = $this->room();
        $tanggal = now()->addDay()->toDateString();

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [
                    ['id_room' => $room->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '10:00', 'durasi' => 2],
                    ['id_room' => $room->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '11:00', 'durasi' => 2],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Reservasi::count());
    }

    public function test_kode_booking_berformat_rm_dan_bertambah_per_hari(): void
    {
        $room = $this->room();
        $tanggal = now()->addDay()->toDateString();
        $pelanggan = $this->pelanggan();

        foreach ([['10:00', 1], ['12:00', 1]] as [$jam, $durasi]) {
            $this->actingAs($pelanggan)
                ->post(route('pelanggan.reservasi.simpan'), [
                    'items' => [['id_room' => $room->id_room, 'tanggal' => $tanggal, 'jam_mulai' => $jam, 'durasi' => $durasi]],
                ])
                ->assertSessionHasNoErrors();
        }

        $kode = Reservasi::orderBy('id_reservasi')->pluck('kode_booking')->all();
        $prefiks = 'RM-'.now()->format('Ymd').'-';

        $this->assertSame($prefiks.'001', $kode[0]);
        $this->assertSame($prefiks.'002', $kode[1]);
    }

    public function test_dua_room_berbeda_jam_tersimpan_sebagai_dua_baris_dan_satu_total(): void
    {
        $pelanggan = $this->pelanggan();
        $roomA = $this->room(20000);
        $roomB = $this->room(35000);
        $tanggal = now()->addDay()->toDateString();

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.reservasi.simpan'), [
                'items' => [
                    ['id_room' => $roomA->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '10:00', 'durasi' => 2],
                    ['id_room' => $roomB->id_room, 'tanggal' => $tanggal, 'jam_mulai' => '15:00', 'durasi' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $reservasi = Reservasi::sole();

        $this->assertCount(2, $reservasi->detailReservasi);
        $this->assertEquals(75000, (float) $reservasi->total_reservasi);
        $this->assertEquals(
            [40000.0, 35000.0],
            $reservasi->detailReservasi->sortBy('jam_mulai')->pluck('subtotal')->map(fn ($n): float => (float) $n)->all(),
        );
    }

    public function test_pelanggan_lain_tidak_boleh_melihat_detail_reservasi(): void
    {
        $reservasi = Reservasi::factory()->create(['id_pengguna' => $this->pelanggan()->id_pengguna]);

        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.reservasi.detail', $reservasi))
            ->assertNotFound();
    }

    public function test_riwayat_reservasi_hanya_menampilkan_milik_sendiri(): void
    {
        $pelanggan = $this->pelanggan();
        $milikSendiri = Reservasi::factory()->create([
            'id_pengguna' => $pelanggan->id_pengguna,
            'kode_booking' => 'RM-20261020-777',
        ]);
        $milikOrangLain = Reservasi::factory()->create([
            'id_pengguna' => $this->pelanggan()->id_pengguna,
            'kode_booking' => 'RM-20261020-888',
        ]);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.reservasi.index'))
            ->assertOk()
            ->assertSee($milikSendiri->kode_booking)
            ->assertDontSee($milikOrangLain->kode_booking);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.reservasi.index', ['cari' => '777']))
            ->assertOk()
            ->assertSee('RM-20261020-777')
            ->assertDontSee('RM-20261020-888');
    }

    public function test_detail_reservasi_menampilkan_kode_booking_dan_qr(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = Reservasi::factory()->create([
            'id_pengguna' => $pelanggan->id_pengguna,
            'kode_booking' => 'RM-20261020-123',
        ]);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.reservasi.detail', $reservasi))
            ->assertOk()
            ->assertSee('RM-20261020-123')
            ->assertSee('<svg', false);
    }

    public function test_form_reservasi_baru_dapat_dibuka_dan_memuat_daftar_room(): void
    {
        $room = $this->room(25000);

        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.reservasi.baru'))
            ->assertOk()
            ->assertSee($room->nama_room)
            ->assertSee('data-form-reservasi', false)
            ->assertSee('items[0][id_room]', false);
    }

    public function test_form_reservasi_baru_memilih_room_dari_query(): void
    {
        $room = $this->room();

        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.reservasi.baru', ['id_room' => $room->id_room]))
            ->assertOk()
            ->assertSee('data-konfigurasi-reservasi', false)
            ->assertSee('"awal"', false)
            ->assertSee('"kunci":true', false)
            ->assertSee('"mulaiLangkah":2', false);
    }

        public function test_form_reservasi_baru_memakai_durasi_dari_query(): void
        {
            $room = $this->room();

            $this->actingAs($this->pelanggan())
                ->get(route('pelanggan.reservasi.baru', ['id_room' => $room->id_room, 'jam_mulai' => '14:00', 'durasi' => 4]))
                ->assertOk()
                ->assertSee('"jam_mulai":"14:00"', false)
                ->assertSee('"durasi":4', false);
        }

        public function test_form_reservasi_baru_membatasi_durasi_dari_query(): void
        {
            $room = $this->room();

            $this->actingAs($this->pelanggan())
                ->get(route('pelanggan.reservasi.baru', ['durasi' => 99]))
                ->assertOk()
                ->assertSee('"durasi":'.\App\Services\ReservasiService::MAKS_DURASI, false);
        }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('pelanggan.reservasi.baru'))->assertRedirect(route('login'));
        $this->post(route('pelanggan.reservasi.simpan'))->assertRedirect(route('login'));
    }
}

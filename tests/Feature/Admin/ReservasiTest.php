<?php

namespace Tests\Feature\Admin;

use App\Models\DetailReservasi;
use App\Models\Pengguna;
use App\Models\Pembayaran;
use App\Models\Reservasi;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservasiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
    }

    private function reservasi(string $kode = 'RM-20261020-001', string $status = 'menunggu_pembayaran'): Reservasi
    {
        return Reservasi::factory()->create(['kode_booking' => $kode, 'status_reservasi' => $status]);
    }

    public function test_admin_dapat_mencari_reservasi_dengan_kode_booking_dan_nama_pelanggan(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan', 'nama' => 'Budi Santoso']);
        $sari = Pengguna::factory()->create(['peran' => 'pelanggan', 'nama' => 'Sari Dewi']);

        $milikBudi = Reservasi::factory()->create(['id_pengguna' => $budi->id_pengguna, 'kode_booking' => 'RM-20261020-101']);
        $milikSari = Reservasi::factory()->create(['id_pengguna' => $sari->id_pengguna, 'kode_booking' => 'RM-20261020-202']);

        $this->actingAs($this->admin())
            ->get(route('admin.reservasi.index', ['cari' => '101']))
            ->assertOk()
            ->assertSee('RM-20261020-101')
            ->assertDontSee('RM-20261020-202');

        $this->actingAs($this->admin())
            ->get(route('admin.reservasi.index', ['cari' => 'Sari']))
            ->assertOk()
            ->assertSee('RM-20261020-202')
            ->assertDontSee('RM-20261020-101');

        $this->assertNotNull($milikBudi->id_reservasi);
        $this->assertNotNull($milikSari->id_reservasi);
    }

    public function test_admin_dapat_memfilter_status_fasilitas_dan_tanggal(): void
    {
        $tanggal = now()->addDay()->toDateString();
        $admin = $this->admin();

        $sesuai = Reservasi::factory()->create(['kode_booking' => 'RM-20261020-301']);
        DetailReservasi::factory()->create([
            'id_reservasi' => $sesuai->id_reservasi,
            'tanggal' => $tanggal,
        ]);

        $bedaStatus = Reservasi::factory()->create(['kode_booking' => 'RM-20261020-302', 'status_reservasi' => 'dibatalkan']);
        DetailReservasi::factory()->create([
            'id_reservasi' => $bedaStatus->id_reservasi,
            'tanggal' => $tanggal,
        ]);

        $bedaTanggal = Reservasi::factory()->create(['kode_booking' => 'RM-20261020-303']);
        DetailReservasi::factory()->create([
            'id_reservasi' => $bedaTanggal->id_reservasi,
            'tanggal' => now()->addDays(9)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reservasi.index', ['status' => 'menunggu_pembayaran', 'tanggal' => $tanggal]))
            ->assertOk()
            ->assertSee('RM-20261020-301')
            ->assertDontSee('RM-20261020-302')
            ->assertDontSee('RM-20261020-303');

        $fasilitas = $sesuai->detailReservasi()->firstOrFail()->room->fasilitas;

        $this->actingAs($admin)
            ->get(route('admin.reservasi.index', ['fasilitas' => $fasilitas->id_fasilitas]))
            ->assertOk()
            ->assertSee('RM-20261020-301')
            ->assertDontSee('RM-20261020-303');
    }

    public function test_admin_dapat_mengubah_status_ke_dibatalkan(): void
    {
        $reservasi = $this->reservasi();

        $this->actingAs($this->admin())
            ->patch(route('admin.reservasi.status', $reservasi), ['status_reservasi' => 'dibatalkan'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertSame('dibatalkan', $reservasi->fresh()->status_reservasi);
    }

    public function test_konfirmasi_tanpa_pembayaran_sukses_ditolak(): void
    {
        $reservasi = $this->reservasi();
        Pembayaran::factory()->create(['id_reservasi' => $reservasi->id_reservasi, 'status_pembayaran' => 'pending']);

        $this->actingAs($this->admin())
            ->patch(route('admin.reservasi.status', $reservasi), ['status_reservasi' => 'dikonfirmasi'])
            ->assertSessionHasErrors('status_reservasi');

        $this->assertSame('menunggu_pembayaran', $reservasi->fresh()->status_reservasi);
    }

    public function test_konfirmasi_setelah_pembayaran_sukses_berhasil(): void
    {
        $reservasi = $this->reservasi();
        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'jenis_pembayaran' => 'dp',
            'jumlah' => 25000,
            'status_pembayaran' => 'sukses',
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.reservasi.status', $reservasi), ['status_reservasi' => 'dikonfirmasi'])
            ->assertSessionHas('sukses');

        $this->assertSame('dikonfirmasi', $reservasi->fresh()->status_reservasi);
    }

    public function test_status_manual_terbatas_pada_dua_nilai(): void
    {
        $reservasi = $this->reservasi();

        foreach (['check_in', 'selesai', 'menunggu_pembayaran'] as $terlarang) {
            $this->actingAs($this->admin())
                ->patch(route('admin.reservasi.status', $reservasi), ['status_reservasi' => $terlarang])
                ->assertSessionHasErrors('status_reservasi');
        }

        $this->assertSame('menunggu_pembayaran', $reservasi->fresh()->status_reservasi);
    }

    public function test_detail_reservasi_admin_menampilkan_rincian_dan_total(): void
    {
        $reservasi = $this->reservasi('RM-20261020-401');
        $detail = DetailReservasi::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'id_room' => Room::factory(),
            'harga_per_jam' => 20000,
            'durasi_jam' => 3,
            'subtotal' => 60000,
        ]);
        $reservasi->update(['total_reservasi' => 60000]);
        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'jumlah' => 30000,
            'status_pembayaran' => 'sukses',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.reservasi.detail', $reservasi))
            ->assertOk()
            ->assertSee('RM-20261020-401')
            ->assertSee($detail->room->nama_room)
            ->assertSee('Rp60.000')
            ->assertSee('Rp30.000')
            ->assertSee('Rp30.000');
    }

    public function test_pelanggan_tidak_boleh_mengakses_kelola_reservasi(): void
    {
        $reservasi = $this->reservasi();
        $pelanggan = Pengguna::factory()->create(['peran' => 'pelanggan']);

        $this->actingAs($pelanggan)->get(route('admin.reservasi.index'))->assertForbidden();
        $this->actingAs($pelanggan)
            ->patch(route('admin.reservasi.status', $reservasi), ['status_reservasi' => 'dibatalkan'])
            ->assertForbidden();
    }
}
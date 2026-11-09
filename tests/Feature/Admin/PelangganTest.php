<?php

namespace Tests\Feature\Admin;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PelangganTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
    }

    public function test_daftar_pelanggan_hanya_menampilkan_peran_pelanggan(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan', 'nama' => 'Budi Santoso']);
        $sari = Pengguna::factory()->create(['peran' => 'pelanggan', 'nama' => 'Sari Dewi', 'status_akun' => 'nonaktif']);
        $adminLain = Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN, 'nama' => 'Raka Admin']);

        $this->actingAs($this->admin())
            ->get(route('admin.pelanggan.index'))
            ->assertOk()
            ->assertSee('Manajemen Pelanggan')
            ->assertSee($budi->nama)
            ->assertSee($sari->nama)
            ->assertDontSee($adminLain->nama);

        $this->get(route('admin.pelanggan.index', ['status' => 'nonaktif']))
            ->assertOk()
            ->assertSee($sari->nama)
            ->assertDontSee($budi->nama);
    }

    public function test_pencarian_pelanggan_mencakup_nama_email_dan_telepon(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan', 'nama' => 'Budi Santoso', 'email' => 'budi@ruangmain.test', 'no_hp' => '081200000001']);
        Pengguna::factory()->create(['peran' => 'pelanggan', 'nama' => 'Sari Dewi', 'email' => 'sari@ruangmain.test', 'no_hp' => '081200000002']);

        $this->actingAs($this->admin())->get(route('admin.pelanggan.index', ['cari' => '081200000001']))->assertOk()->assertSee($budi->nama)->assertDontSee('Sari Dewi');
        $this->get(route('admin.pelanggan.index', ['cari' => 'budi@']))->assertOk()->assertSee($budi->nama)->assertDontSee('Sari Dewi');
        $this->get(route('admin.pelanggan.index', ['cari' => 'Dewi']))->assertOk()->assertSee('Sari Dewi')->assertDontSee($budi->nama);
    }

    public function test_detail_pelanggan_menampilkan_riwayat_reservasi_dan_pembayaran(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan', 'nama' => 'Budi Santoso']);
        $admin = $this->admin();

        $reservasi = \App\Models\Reservasi::factory()->create([
            'id_pengguna' => $budi->id_pengguna,
            'kode_booking' => 'RM-UJI-001',
            'total_reservasi' => 50000,
            'status_reservasi' => 'dikonfirmasi',
        ]);
        \App\Models\DetailReservasi::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'id_room' => \App\Models\Room::factory()->create(['nama_room' => 'PS-01'])->id_room,
        ]);
        $pembayaran = \App\Models\Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'diverifikasi_oleh' => $admin->id_pengguna,
            'jenis_pembayaran' => 'dp',
            'metode_pembayaran' => 'cash',
            'jumlah' => 25000,
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pelanggan.detail', $budi))
            ->assertOk()
            ->assertSee($budi->nama)
            ->assertSee('RM-UJI-001')
            ->assertSee('PS-01')
            ->assertSee('TX-'.str_pad((string) $pembayaran->id_pembayaran, 4, '0', STR_PAD_LEFT))
            ->assertSee('Rp25.000');
    }

    public function test_detail_pelanggan_menolak_id_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.pelanggan.detail', $this->admin()))
            ->assertNotFound();
    }

    public function test_admin_dapat_menonaktifkan_dan_mengaktifkan_akun_pelanggan(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan', 'status_akun' => 'aktif']);

        $this->actingAs($this->admin())
            ->patch(route('admin.pelanggan.status', $budi), ['status_akun' => 'nonaktif'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertSame('nonaktif', $budi->refresh()->status_akun);

        $this->actingAs($this->admin())
            ->patch(route('admin.pelanggan.status', $budi), ['status_akun' => 'aktif'])
            ->assertRedirect();

        $this->assertSame('aktif', $budi->refresh()->status_akun);
    }

    public function test_status_akun_tidak_sah_ditolak_dan_tidak_mengubah_data(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan', 'status_akun' => 'aktif']);

        $this->actingAs($this->admin())
            ->patch(route('admin.pelanggan.status', $budi), ['status_akun' => 'aktif'])
            ->assertSessionHasErrors('status_akun');

        $this->actingAs($this->admin())
            ->patch(route('admin.pelanggan.status', $budi), ['status_akun' => 'hapus'])
            ->assertSessionHasErrors('status_akun');

        $this->assertSame('aktif', $budi->refresh()->status_akun);
    }

    public function test_status_admin_tidak_dapat_diubah_lewat_route_pelanggan(): void
    {
        $adminLain = $this->admin();

        $this->actingAs($this->admin())
            ->patch(route('admin.pelanggan.status', $adminLain), ['status_akun' => 'nonaktif'])
            ->assertNotFound();

        $this->assertSame('aktif', $adminLain->refresh()->status_akun);
    }

    public function test_pelanggan_tidak_dapat_membuka_manajemen_pelanggan(): void
    {
        $this->actingAs(Pengguna::factory()->create(['peran' => 'pelanggan']))
            ->get(route('admin.pelanggan.index'))
            ->assertForbidden();
    }
}

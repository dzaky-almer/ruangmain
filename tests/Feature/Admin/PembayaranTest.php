<?php

namespace Tests\Feature\Admin;

use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Reservasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembayaranTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
    }

    private function pelanggan(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => 'pelanggan']);
    }

    private function reservasi(Pengguna $pelanggan, float $total = 100000, string $status = 'menunggu_pembayaran'): Reservasi
    {
        return Reservasi::factory()->create([
            'id_pengguna' => $pelanggan->id_pengguna,
            'total_reservasi' => $total,
            'status_reservasi' => $status,
        ]);
    }

    public function test_admin_dapat_melihat_daftar_dan_memfilter_pembayaran(): void
    {
        $pelanggan = $this->pelanggan();
        $sari = $this->pelanggan();

        Pembayaran::factory()->create([
            'id_reservasi' => $this->reservasi($pelanggan, 50000, 'dikonfirmasi')->id_reservasi,
            'metode_pembayaran' => 'cash',
            'jenis_pembayaran' => 'dp',
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now(),
        ]);
        Pembayaran::factory()->create([
            'id_reservasi' => $this->reservasi($sari, 50000, 'dikonfirmasi')->id_reservasi,
            'metode_pembayaran' => 'qris',
            'jenis_pembayaran' => 'pelunasan',
            'status_pembayaran' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.pembayaran.index', ['metode' => 'cash']))
            ->assertOk()
            ->assertSee($pelanggan->reservasi()->first()->kode_booking)
            ->assertDontSee($sari->reservasi()->first()->kode_booking);

        $this->actingAs($this->admin())
            ->get(route('admin.pembayaran.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee($sari->reservasi()->first()->kode_booking)
            ->assertDontSee($pelanggan->reservasi()->first()->kode_booking);
    }

    public function test_admin_dapat_mencatat_pembayaran_cash_dengan_nominal_dari_server(): void
    {
        $reservasi = $this->reservasi($this->pelanggan(), 60000);

        $this->actingAs($this->admin())
            ->post(route('admin.pembayaran.cash'), [
                'id_reservasi' => $reservasi->id_reservasi,
                'jenis_pembayaran' => 'dp',
                'jumlah' => 1,
            ])
            ->assertRedirect();

        $pembayaran = Pembayaran::firstOrFail();

        $this->assertSame('cash', $pembayaran->metode_pembayaran);
        $this->assertEquals(30000, (float) $pembayaran->jumlah);
        $this->assertSame('pending', $pembayaran->status_pembayaran);
    }

    public function test_verifikasi_cash_menyetel_verifikator_waktu_dan_mengonfirmasi_reservasi(): void
    {
        $admin = $this->admin();
        $reservasi = $this->reservasi($this->pelanggan(), 60000);
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'cash',
            'jenis_pembayaran' => 'dp',
            'jumlah' => 30000,
            'status_pembayaran' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.pembayaran.verifikasi', $pembayaran), [
                'catatan_verifikasi' => 'cash diterima penuh di kasir.',
                'konfirmasi_terima' => '1',
            ])
            ->assertRedirect();

        $pembayaran->refresh();

        $this->assertSame('sukses', $pembayaran->status_pembayaran);
        $this->assertSame($admin->id_pengguna, $pembayaran->diverifikasi_oleh);
        $this->assertNotNull($pembayaran->waktu_bayar);
        $this->assertNotNull($pembayaran->waktu_verifikasi);
        $this->assertSame('dikonfirmasi', $reservasi->refresh()->status_reservasi);
    }

    public function test_verifikasi_cash_wajib_catatan_dan_centang(): void
    {
        $reservasi = $this->reservasi($this->pelanggan());
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'cash',
            'status_pembayaran' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.pembayaran.verifikasi', $pembayaran), ['catatan_verifikasi' => ''])
            ->assertSessionHasErrors(['catatan_verifikasi', 'konfirmasi_terima']);

        $this->assertSame('pending', $pembayaran->refresh()->status_pembayaran);
    }

    public function test_pembayaran_qris_tidak_dapat_diverifikasi_manual(): void
    {
        $reservasi = $this->reservasi($this->pelanggan());
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.pembayaran.verifikasi', $pembayaran), [
                'catatan_verifikasi' => 'coba akali',
                'konfirmasi_terima' => '1',
            ])
            ->assertSessionHasErrors('konfirmasi_terima');

        $this->assertSame('pending', $pembayaran->refresh()->status_pembayaran);
    }

    public function test_status_sukses_bersifat_final(): void
    {
        $reservasi = $this->reservasi($this->pelanggan(), 60000, 'dikonfirmasi');
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'cash',
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now()->subDay(),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.pembayaran.verifikasi', $pembayaran), [
                'catatan_verifikasi' => 'coba ubah',
                'konfirmasi_terima' => '1',
            ])
            ->assertSessionHasErrors('konfirmasi_terima');

        $this->assertSame('sukses', $pembayaran->refresh()->status_pembayaran);
    }

    public function test_halaman_detail_menampilkan_tiga_varian(): void
    {
        $admin = $this->admin();
        $reservasi = $this->reservasi($this->pelanggan(), 60000);

        $cash = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'cash',
            'status_pembayaran' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.pembayaran.detail', $cash))
            ->assertOk()
            ->assertSee('Verifikasi penerimaan cash')
            ->assertSee('Periksa sebelum memverifikasi');

        $qris = Pembayaran::factory()->create([
            'id_reservasi' => $this->reservasi($this->pelanggan(), 60000)->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.pembayaran.detail', $qris))
            ->assertOk()
            ->assertSee('Menunggu Konfirmasi Gateway')
            ->assertDontSee('Verifikasi penerimaan cash');

        $terkunci = Pembayaran::factory()->create([
            'id_reservasi' => $this->reservasi($this->pelanggan(), 60000, 'dikonfirmasi')->id_reservasi,
            'metode_pembayaran' => 'cash',
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.pembayaran.detail', $terkunci))
            ->assertOk()
            ->assertSee('Jejak verifikasi')
            ->assertDontSee('Transaksi berhasil terkunci');
    }

    public function test_daftar_pembayaran_menyediakan_dialog_detail_per_baris(): void
    {
        $admin = $this->admin();
        $reservasi = $this->reservasi($this->pelanggan(), 60000);

        $cash = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'cash',
            'status_pembayaran' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pembayaran.index'))
            ->assertOk()
            ->assertSee('id="detail-'.$cash->id_pembayaran.'"', false)
            ->assertSee("document.getElementById('detail-".$cash->id_pembayaran."').showModal()", false)
            ->assertSee('Verifikasi penerimaan cash')
            ->assertSee('Periksa sebelum memverifikasi');
    }

    public function test_pelanggan_tidak_dapat_mengakses_menu_pembayaran_admin(): void
    {
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $this->reservasi($this->pelanggan())->id_reservasi,
        ]);

        $this->actingAs($this->pelanggan())->get(route('admin.pembayaran.index'))->assertForbidden();
        $this->actingAs($this->pelanggan())->get(route('admin.pembayaran.detail', $pembayaran))->assertForbidden();
        $this->actingAs($this->pelanggan())
            ->post(route('admin.pembayaran.cash'), ['id_reservasi' => 1, 'jenis_pembayaran' => 'dp'])
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Pelanggan;

use App\Contracts\PaymentGateway;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Services\PembayaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembayaranTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_halaman_bayar_menampilkan_opsi_dp_dan_pelunasan_dari_server(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.pembayaran.form', $reservasi))
            ->assertOk()
            ->assertSee('DP 50%')
            ->assertSee('Bayar lunas')
            ->assertSee('Rp50.000')
            ->assertSee('Rp100.000');
    }

    public function test_halaman_bayar_menyediakan_data_nominal_per_opsi_untuk_sinkronisasi(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.pembayaran.form', $reservasi))
            ->assertOk()
            ->assertSee('data-opsi-jumlah="50000"', false)
            ->assertSee('data-opsi-jumlah="100000"', false)
            ->assertSee('data-nominal-transaksi', false);
    }

    public function test_detail_reservasi_dibatalkan_tidak_menawarkan_tombol_bayar(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan, 100000, 'dibatalkan');

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.reservasi.detail', $reservasi))
            ->assertOk()
            ->assertDontSee(e(route('pelanggan.pembayaran.form', $reservasi)), false);
    }

    public function test_detail_reservasi_menunggu_pembayaran_menawarkan_tombol_bayar(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.reservasi.detail', $reservasi))
            ->assertOk()
            ->assertSee(e(route('pelanggan.pembayaran.form', $reservasi)), false);
    }

    public function test_dp_dihitung_50_persen_dan_pelunasan_sisa_tagihan(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan, 75000);
        $service = app(PembayaranService::class);

        $this->assertSame(37500.0, $service->hitungJumlah($reservasi, 'dp'));
        $this->assertSame(75000.0, $service->hitungJumlah($reservasi, 'pelunasan'));

        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'jenis_pembayaran' => 'dp',
            'jumlah' => 37500,
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now(),
        ]);

        $this->assertSame(37500.0, $service->sisaTagihan($reservasi));
        $this->assertSame(37500.0, $service->hitungJumlah($reservasi, 'pelunasan'));
        $this->assertFalse($service->lunas($reservasi));

        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'jenis_pembayaran' => 'pelunasan',
            'jumlah' => 37500,
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now(),
        ]);

        $this->assertTrue($service->lunas($reservasi));
        $this->assertSame([], $service->opsi($reservasi), 'Reservasi lunas tidak menawarkan pembayaran lagi.');
    }

    public function test_pembayaran_gagal_tidak_pernah_dihitung_sebagai_total_dibayar(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan, 50000);

        foreach (['pending', 'gagal', 'dibatalkan'] as $status) {
            Pembayaran::factory()->create([
                'id_reservasi' => $reservasi->id_reservasi,
                'status_pembayaran' => $status,
                'jumlah' => 50000,
            ]);
        }

        $this->assertSame(0.0, app(PembayaranService::class)->totalDibayar($reservasi));
        $this->assertSame(50000.0, app(PembayaranService::class)->sisaTagihan($reservasi));
    }

    public function test_simpan_pembayaran_qris_membuat_pending_dan_id_transaksi_gateway(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan, 80000);

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.pembayaran.simpan', $reservasi), [
                'jenis_pembayaran' => 'dp',
                'metode_pembayaran' => 'qris',
            ])
            ->assertRedirect();

        $pembayaran = Pembayaran::firstOrFail();

        $this->assertSame('pending', $pembayaran->status_pembayaran);
        $this->assertSame('dp', $pembayaran->jenis_pembayaran);
        $this->assertEquals(40000, (float) $pembayaran->jumlah);
        $this->assertStringStartsWith('MOCK-', (string) $pembayaran->id_transaksi_gateway);
        $this->assertNull($pembayaran->waktu_bayar);
    }

    public function test_nominal_dikirim_pelanggan_diabaikan_server(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan, 90000);

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.pembayaran.simpan', $reservasi), [
                'jenis_pembayaran' => 'pelunasan',
                'metode_pembayaran' => 'cash',
                'jumlah' => 1,
                'status_pembayaran' => 'sukses',
            ])
            ->assertRedirect();

        $pembayaran = Pembayaran::firstOrFail();

        $this->assertEquals(90000, (float) $pembayaran->jumlah);
        $this->assertSame('pending', $pembayaran->status_pembayaran);
    }

    public function test_hanya_satu_pembayaran_pending_per_reservasi(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);

        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'status_pembayaran' => 'pending',
            'jenis_pembayaran' => 'dp',
            'jumlah' => 50000,
        ]);

        $this->actingAs($pelanggan)
            ->post(route('pelanggan.pembayaran.simpan', $reservasi), [
                'jenis_pembayaran' => 'dp',
                'metode_pembayaran' => 'qris',
            ])
            ->assertSessionHasErrors('jenis_pembayaran');

        $this->assertSame(1, Pembayaran::count());
    }

    public function test_halaman_bayar_mengalihkan_ke_status_saat_masih_ada_pending(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);
        $pending = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'status_pembayaran' => 'pending',
        ]);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.pembayaran.form', $reservasi))
            ->assertRedirect(route('pelanggan.pembayaran.status', $pending));
    }

    public function test_callback_qris_valid_mengonfirmasi_reservasi(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
            'jenis_pembayaran' => 'dp',
            'jumlah' => 50000,
            'waktu_bayar' => null,
        ]);

        $tandaTangan = app(PaymentGateway::class)
            ->tandaTanganSimulasi((string) $pembayaran->id_transaksi_gateway, 'sukses');

        $this->postJson(route('pembayaran.callback'), [
            'id_transaksi' => $pembayaran->id_transaksi_gateway,
            'status' => 'sukses',
            'tanda_tangan' => $tandaTangan,
        ])->assertOk()->assertJson(['diterima' => true, 'diproses' => true]);

        $pembayaran->refresh();
        $reservasi->refresh();

        $this->assertSame('sukses', $pembayaran->status_pembayaran);
        $this->assertNotNull($pembayaran->waktu_bayar);
        $this->assertNull($pembayaran->diverifikasi_oleh, 'QRIS tidak diverifikasi admin.');
        $this->assertSame('dikonfirmasi', $reservasi->status_reservasi);
    }

    public function test_callback_tanda_tangan_salah_ditolak_dan_tidak_mengubah_status(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
        ]);

        $this->postJson(route('pembayaran.callback'), [
            'id_transaksi' => $pembayaran->id_transaksi_gateway,
            'status' => 'sukses',
            'tanda_tangan' => 'tanda-tangan-palsu',
        ])->assertOk()->assertJson(['diterima' => true, 'diproses' => false]);

        $this->assertSame('pending', $pembayaran->refresh()->status_pembayaran);
        $this->assertSame('menunggu_pembayaran', $reservasi->refresh()->status_reservasi);
    }

    public function test_callback_status_tidak_dikenal_dijawab_200_tanpa_mengubah_status(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
        ]);

        $this->postJson(route('pembayaran.callback'), [
            'id_transaksi' => $pembayaran->id_transaksi_gateway,
            'status' => 'entah-apa',
            'tanda_tangan' => 'apa-saja',
        ])->assertOk()->assertJson(['diterima' => true, 'diproses' => false]);

        $this->assertSame('pending', $pembayaran->refresh()->status_pembayaran);
    }

    public function test_callback_ganda_tidak_diproses_dua_kali(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
            'jenis_pembayaran' => 'dp',
            'jumlah' => 50000,
        ]);

        $gateway = app(PaymentGateway::class);
        $payload = [
            'id_transaksi' => $pembayaran->id_transaksi_gateway,
            'status' => 'sukses',
            'tanda_tangan' => $gateway->tandaTanganSimulasi((string) $pembayaran->id_transaksi_gateway, 'sukses'),
        ];

        $this->postJson(route('pembayaran.callback'), $payload)
            ->assertOk()
            ->assertJson(['diproses' => true]);

        $this->postJson(route('pembayaran.callback'), $payload)
            ->assertOk()
            ->assertJson(['diproses' => false]);

        $this->assertSame(1, Pembayaran::where('status_pembayaran', 'sukses')->count());
    }

    public function test_callback_tidak_memakai_csrf_dan_tanpa_login(): void
    {
        $this->postJson(route('pembayaran.callback'), [
            'id_transaksi' => 'MOCK-tidak-ada',
            'status' => 'sukses',
            'tanda_tangan' => app(PaymentGateway::class)->tandaTanganSimulasi('MOCK-tidak-ada', 'sukses'),
        ])->assertOk()->assertJson(['diterima' => true, 'diproses' => false]);
    }

    public function test_pelanggan_tidak_dapat_mengakses_pembayaran_milik_orang_lain(): void
    {
        $pemilik = $this->pelanggan();
        $penyusup = $this->pelanggan();
        $reservasi = $this->reservasi($pemilik);
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'status_pembayaran' => 'pending',
        ]);

        $this->actingAs($penyusup)->get(route('pelanggan.pembayaran.form', $reservasi))->assertNotFound();
        $this->actingAs($penyusup)->get(route('pelanggan.pembayaran.status', $pembayaran))->assertNotFound();
        $this->actingAs($penyusup)->post(route('pelanggan.pembayaran.simulasi', $pembayaran), ['status_simulasi' => 'sukses'])->assertNotFound();
    }

    public function test_riwayat_pembayaran_hanya_menampilkan_milik_sendiri(): void
    {
        $pemilik = $this->pelanggan();
        $lain = $this->pelanggan();

        Pembayaran::factory()->create([
            'id_reservasi' => $this->reservasi($pemilik, 50000, 'dikonfirmasi')->id_reservasi,
            'status_pembayaran' => 'sukses',
        ]);
        Pembayaran::factory()->create([
            'id_reservasi' => $this->reservasi($lain, 50000, 'dikonfirmasi')->id_reservasi,
            'status_pembayaran' => 'sukses',
        ]);

        $this->actingAs($pemilik)
            ->get(route('pelanggan.pembayaran.index'))
            ->assertOk()
            ->assertSee($pemilik->reservasi()->first()->kode_booking)
            ->assertDontSee($lain->reservasi()->first()->kode_booking);
    }

    public function test_simulator_hanya_aktif_di_luar_produksi(): void
    {
        $pelanggan = $this->pelanggan();
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $this->reservasi($pelanggan)->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
        ]);

        $this->app['env'] = 'production';
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);

        try {
            $this->actingAs($pelanggan)
                ->post(route('pelanggan.pembayaran.simulasi', $pembayaran), ['status_simulasi' => 'sukses'])
                ->assertNotFound();
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame('pending', $pembayaran->refresh()->status_pembayaran);
    }

    public function test_status_pembayaran_dibatalkan_menampilkan_varian_dibatalkan(): void
    {
        $pelanggan = $this->pelanggan();
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $this->reservasi($pelanggan)->id_reservasi,
            'status_pembayaran' => 'dibatalkan',
        ]);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.pembayaran.status', $pembayaran))
            ->assertOk()
            ->assertSee('Pembayaran Dibatalkan')
            ->assertSee('Transaksi: Dibatalkan');
    }

    public function test_status_pembayaran_kedaluwarsa_menampilkan_varian_kedaluwarsa(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);
        $reservasi->forceFill(['batas_pembayaran' => now()->subMinute()])->save();
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'status_pembayaran' => 'gagal',
        ]);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.pembayaran.status', $pembayaran))
            ->assertOk()
            ->assertSee('Pembayaran Kedaluwarsa')
            ->assertSee('Transaksi: Kedaluwarsa');
    }

    public function test_status_pembayaran_final_tidak_menawarkan_bayar_lagi_saat_reservasi_dibatalkan(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan, 100000, 'dibatalkan');
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'status_pembayaran' => 'gagal',
        ]);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.pembayaran.status', $pembayaran))
            ->assertOk()
            ->assertDontSee(e(route('pelanggan.pembayaran.form', $reservasi)), false);
    }

    public function test_halaman_bayar_metode_cash_menyediakan_tautan_lihat_status(): void
    {
        $pelanggan = $this->pelanggan();
        $reservasi = $this->reservasi($pelanggan);

        $this->actingAs($pelanggan)
            ->get(route('pelanggan.pembayaran.form', $reservasi))
            ->assertOk()
            ->assertSee(e(route('pelanggan.pembayaran.index')), false);
    }

        public function test_pemilihan_metode_dilakukan_di_halaman_tanpa_memuat_ulang(): void
        {
            $pelanggan = $this->pelanggan();
            $reservasi = $this->reservasi($pelanggan);

            $this->actingAs($pelanggan)
                ->get(route('pelanggan.pembayaran.form', $reservasi))
                ->assertOk()
                ->assertSee('name="metode_pembayaran"', false)
                ->assertSee('value="qris"', false)
                ->assertSee('value="cash"', false)
                ->assertSee('data-catatan-metode="qris"', false)
                ->assertSee('data-catatan-metode="cash"', false)
                ->assertDontSee(route('pelanggan.pembayaran.form', ['reservasi' => $reservasi, 'metode' => 'cash']), false);
        }
    }

<?php

namespace Tests\Feature\Admin;

use App\Models\DetailReservasi;
use App\Models\Fasilitas;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\Room;
use App\Services\LaporanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
    }

    /** Satu reservasi + detail (room milik fasilitas) + opsional pembayaran. */
    private function buat(Pengguna $pelanggan, string $kode, Fasilitas $fasilitas, string $namaRoom, float $total, string $status, string $tanggal, array $pembayaran = []): Reservasi
    {
        $reservasi = Reservasi::factory()->create([
            'id_pengguna' => $pelanggan->id_pengguna,
            'kode_booking' => $kode,
            'total_reservasi' => $total,
            'status_reservasi' => $status,
        ]);

        DetailReservasi::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'id_room' => Room::factory()->create(['id_fasilitas' => $fasilitas->id_fasilitas, 'nama_room' => $namaRoom])->id_room,
            'tanggal' => $tanggal,
        ]);

        if ($pembayaran !== []) {
            Pembayaran::factory()->create(array_merge(['id_reservasi' => $reservasi->id_reservasi], $pembayaran));
        }

        return $reservasi;
    }

    public function test_laporan_pendapatan_hanya_menjumlahkan_pembayaran_sukses(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create(['nama_fasilitas' => 'PlayStation Room']);

        $a = $this->buat($budi, 'RM-LAP-001', $ps, 'PS-01', 100000, 'dikonfirmasi', now()->toDateString());
        $b = $this->buat($budi, 'RM-LAP-002', $ps, 'PS-02', 100000, 'dikonfirmasi', now()->toDateString());
        $c = $this->buat($budi, 'RM-LAP-003', $ps, 'PS-03', 100000, 'dikonfirmasi', now()->toDateString());

        Pembayaran::factory()->create(['id_reservasi' => $a->id_reservasi, 'jumlah' => 25000, 'status_pembayaran' => 'sukses', 'waktu_bayar' => now()]);
        Pembayaran::factory()->create(['id_reservasi' => $a->id_reservasi, 'jumlah' => 75000, 'status_pembayaran' => 'sukses', 'waktu_bayar' => now()]);
        Pembayaran::factory()->create(['id_reservasi' => $b->id_reservasi, 'jumlah' => 50000, 'status_pembayaran' => 'pending']);
        Pembayaran::factory()->create(['id_reservasi' => $c->id_reservasi, 'jumlah' => 50000, 'status_pembayaran' => 'gagal', 'waktu_bayar' => now()]);

        $periode = app(LaporanService::class)->laporanPendapatan('harian', now()->startOfDay(), now()->endOfDay());

        $this->assertSame(100000.0, $periode['total'], 'hanya sukses yang dijumlahkan');
        $this->assertSame(2, $periode['transaksi'], 'DP dan pelunasan dihitung transaksi terpisah');
    }

    public function test_periode_harian_mengisi_semua_tanggal_dalam_rentang(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create();
        $reservasi = $this->buat($budi, 'RM-LAP-010', $ps, 'PS-01', 50000, 'dikonfirmasi', now()->toDateString());

        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'jumlah' => 40000,
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now()->subDays(2),
        ]);

        $hasil = app(LaporanService::class)->laporanPendapatan('harian', now()->subDays(6)->startOfDay(), now()->endOfDay());

        $this->assertCount(7, $hasil['baris'], 'tujuh hari tetap muncul walau kosong');
        $this->assertSame(40000.0, $hasil['total']);
        $this->assertSame(1, $hasil['transaksi']);
    }

    public function test_periode_mingguan_dan_bulanan_mengelompokkan_tanpa_error_sql(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create();
        $reservasi = $this->buat($budi, 'RM-LAP-020', $ps, 'PS-01', 50000, 'dikonfirmasi', now()->toDateString());

        Pembayaran::factory()->create(['id_reservasi' => $reservasi->id_reservasi, 'jumlah' => 30000, 'status_pembayaran' => 'sukses', 'waktu_bayar' => now()]);
        Pembayaran::factory()->create(['id_reservasi' => $reservasi->id_reservasi, 'jumlah' => 20000, 'status_pembayaran' => 'sukses', 'waktu_bayar' => now()->subMonth()]);

        $mingguan = app(LaporanService::class)->laporanPendapatan('mingguan', now()->subWeeks(7)->startOfWeek(), now()->endOfDay());
        $this->assertSame(50000.0, $mingguan['total']);

        $bulanan = app(LaporanService::class)->laporanPendapatan('bulanan', now()->subMonths(11)->startOfMonth(), now()->endOfDay());
        $this->assertSame(50000.0, $bulanan['total']);
        $this->assertGreaterThanOrEqual(2, $bulanan['baris']->count(), 'dua bulan berbeda dikelompokkan terpisah');
    }

    public function test_rentang_menukar_tanggal_bila_dibalik(): void
    {
        $laporan = app(LaporanService::class);
        [$mulai, $akhir] = $laporan->rentang('rentang', now(), now()->subDays(5));

        $this->assertTrue($mulai->lte($akhir));
        $this->assertSame(now()->subDays(5)->toDateString(), $mulai->toDateString());
    }

    public function test_halaman_pendapatan_merender_grafik_dan_rincian(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create();
        $reservasi = $this->buat($budi, 'RM-LAP-030', $ps, 'PS-01', 50000, 'dikonfirmasi', now()->toDateString());

        Pembayaran::factory()->create(['id_reservasi' => $reservasi->id_reservasi, 'jumlah' => 45000, 'status_pembayaran' => 'sukses', 'waktu_bayar' => now()]);

        $this->actingAs($this->admin())
            ->get(route('admin.laporan.pendapatan', ['periode' => 'harian']))
            ->assertOk()
            ->assertSee('Laporan')
            ->assertSee('Rp45.000');
    }

    public function test_ekspor_csv_pendapatan_mengikuti_filter_periode(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create();
        $reservasi = $this->buat($budi, 'RM-LAP-031', $ps, 'PS-01', 50000, 'dikonfirmasi', now()->toDateString());

        Pembayaran::factory()->create(['id_reservasi' => $reservasi->id_reservasi, 'jumlah' => 45000, 'status_pembayaran' => 'sukses', 'waktu_bayar' => now()]);

        $respons = $this->actingAs($this->admin())
            ->get(route('admin.laporan.pendapatan.ekspor', ['periode' => 'harian']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('attachment;', (string) $respons->headers->get('content-disposition'));
        $this->assertStringContainsString('laporan-pendapatan-harian-', (string) $respons->headers->get('content-disposition'));

        $isi = $respons->streamedContent();

        $this->assertStringContainsString('Periode,"Jumlah transaksi berhasil",Pendapatan', $isi);
        $this->assertStringContainsString('45000', $isi);
    }

    public function test_pelanggan_tidak_dapat_mengekspor_laporan_pendapatan(): void
    {
        $this->actingAs(Pengguna::factory()->create(['peran' => 'pelanggan']))
            ->get(route('admin.laporan.pendapatan.ekspor'))
            ->assertForbidden();
    }

    public function test_ekspor_csv_reservasi_dan_transaksi_mengikuti_filter(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create();
        $a = $this->buat($budi, 'RM-LAP-035', $ps, 'PS-01', 50000, 'dikonfirmasi', now()->toDateString());

        Pembayaran::factory()->create(['id_reservasi' => $a->id_reservasi, 'jumlah' => 25000, 'status_pembayaran' => 'sukses', 'waktu_bayar' => now()]);

        $reservasi = $this->actingAs($this->admin())->get(route('admin.laporan.reservasi.ekspor'))->assertOk();
        $this->assertStringContainsString('attachment;', (string) $reservasi->headers->get('content-disposition'));

        $isiReservasi = $reservasi->streamedContent();
        $this->assertStringContainsString('Kode booking', $isiReservasi);
        $this->assertStringContainsString('RM-LAP-035', $isiReservasi);

        $transaksi = $this->actingAs($this->admin())->get(route('admin.laporan.transaksi.ekspor'))->assertOk();
        $this->assertStringContainsString('attachment;', (string) $transaksi->headers->get('content-disposition'));

        $isiTransaksi = $transaksi->streamedContent();
        $this->assertStringContainsString('Referensi', $isiTransaksi);
        $this->assertStringContainsString('RM-LAP-035', $isiTransaksi);
        $this->assertStringContainsString('25000', $isiTransaksi);
    }

    public function test_filter_laporan_reservasi_bekerja_untuk_status_fasilitas_dan_room(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create(['nama_fasilitas' => 'PlayStation Room']);
        $nintendo = Fasilitas::factory()->create(['nama_fasilitas' => 'Nintendo Switch Room']);

        $this->buat($budi, 'RM-LAP-041', $ps, 'PS-01', 50000, 'dikonfirmasi', now()->toDateString());
        $this->buat($budi, 'RM-LAP-042', $nintendo, 'NS-01', 40000, 'selesai', now()->subDays(3)->toDateString());

        $this->actingAs($this->admin())
            ->get(route('admin.laporan.reservasi', ['status' => 'dikonfirmasi']))
            ->assertOk()
            ->assertSee('RM-LAP-041')
            ->assertDontSee('RM-LAP-042');

        $this->get(route('admin.laporan.reservasi', ['fasilitas' => $nintendo->id_fasilitas]))
            ->assertOk()
            ->assertSee('RM-LAP-042')
            ->assertDontSee('RM-LAP-041');

        $this->get(route('admin.laporan.reservasi', ['room' => Room::query()->where('nama_room', 'PS-01')->value('id_room')]))
            ->assertOk()
            ->assertSee('RM-LAP-041')
            ->assertDontSee('RM-LAP-042');

        $this->get(route('admin.laporan.reservasi', ['mulai' => now()->subDay()->toDateString(), 'akhir' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('RM-LAP-041')
            ->assertDontSee('RM-LAP-042');
    }

    public function test_filter_laporan_transaksi_bekerja_untuk_metode_jenis_dan_status(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create();
        $a = $this->buat($budi, 'RM-LAP-051', $ps, 'PS-01', 50000, 'dikonfirmasi', now()->toDateString());
        $b = $this->buat($budi, 'RM-LAP-052', $ps, 'PS-02', 50000, 'dikonfirmasi', now()->toDateString());

        Pembayaran::factory()->create(['id_reservasi' => $a->id_reservasi, 'jenis_pembayaran' => 'dp', 'metode_pembayaran' => 'cash', 'jumlah' => 25000, 'status_pembayaran' => 'sukses', 'waktu_bayar' => now()]);
        Pembayaran::factory()->create(['id_reservasi' => $b->id_reservasi, 'jenis_pembayaran' => 'pelunasan', 'metode_pembayaran' => 'qris', 'jumlah' => 25000, 'status_pembayaran' => 'pending']);

        $this->actingAs($this->admin())
            ->get(route('admin.laporan.transaksi', ['metode' => 'cash']))
            ->assertOk()
            ->assertSee('Laporan Transaksi')
            ->assertSee('RM-LAP-051')
            ->assertDontSee('RM-LAP-052');

        $this->get(route('admin.laporan.transaksi', ['jenis' => 'pelunasan', 'status' => 'pending']))
            ->assertOk()
            ->assertSee('RM-LAP-052')
            ->assertDontSee('RM-LAP-051');
    }

    public function test_filter_transaksi_hanya_menjumlahkan_yang_sukses(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create();
        $a = $this->buat($budi, 'RM-LAP-061', $ps, 'PS-01', 50000, 'dikonfirmasi', now()->toDateString());

        Pembayaran::factory()->create(['id_reservasi' => $a->id_reservasi, 'jumlah' => 25000, 'status_pembayaran' => 'sukses', 'waktu_bayar' => now()]);
        Pembayaran::factory()->create(['id_reservasi' => $a->id_reservasi, 'jumlah' => 25000, 'status_pembayaran' => 'pending']);

        $hasil = app(LaporanService::class)->laporanTransaksi(['mulai' => null, 'akhir' => null, 'metode' => null, 'jenis' => null, 'status' => null]);

        $this->assertSame(2, $hasil['baris']->count());
        $this->assertSame(25000.0, $hasil['berhasil']);
    }

    public function test_tab_dan_chip_periode_mempertahankan_filter(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.laporan.pendapatan', ['periode' => 'rentang', 'mulai' => '2026-10-01', 'akhir' => '2026-10-31']))
            ->assertOk()
            ->assertSee(route('admin.laporan.reservasi', ['mulai' => '2026-10-01', 'akhir' => '2026-10-31']))
                        ->assertSee(route('admin.laporan.transaksi', ['mulai' => '2026-10-01', 'akhir' => '2026-10-31']))
                        ->assertSee(route('admin.laporan.pendapatan', ['periode' => 'bulanan', 'mulai' => '2026-10-01', 'akhir' => '2026-10-31']));
    }

    public function test_grafik_reservasi_menghitung_jumlah_per_hari_dan_sebaran_status(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create();

        $a = $this->buat($budi, 'RM-LAP-071', $ps, 'PS-01', 50000, 'dikonfirmasi', '2026-10-29');
        DetailReservasi::factory()->create([
            'id_reservasi' => $a->id_reservasi,
            'id_room' => Room::factory()->create(['id_fasilitas' => $ps->id_fasilitas, 'nama_room' => 'PS-02'])->id_room,
            'tanggal' => '2026-10-29',
        ]);
        $this->buat($budi, 'RM-LAP-072', $ps, 'PS-03', 50000, 'dibatalkan', '2026-10-30');

        $hasil = app(LaporanService::class)->laporanReservasi([
            'mulai' => null, 'akhir' => null, 'status' => null, 'fasilitas' => null, 'room' => null,
        ]);

        $this->assertCount(2, $hasil['perHari'], 'dua tanggal jadwal berbeda');
        $this->assertSame('2026-10-29', $hasil['perHari'][0]['tanggal']);
        $this->assertSame(1, $hasil['perHari'][0]['jumlah'], 'dua detail di hari sama dihitung sekali per reservasi');
        $this->assertSame(50000.0, $hasil['perHari'][0]['total']);
        $this->assertSame(1, $hasil['perHari'][1]['jumlah']);

        $this->assertSame(2, $hasil['perStatus']->sum('jumlah'));
        $this->assertSame(['dibatalkan', 'dikonfirmasi'], $hasil['perStatus']->pluck('status')->sort()->values()->all());
    }

    public function test_grafik_transaksi_melewati_baris_tanpa_waktu_bayar_dan_rentang_panjang(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $ps = Fasilitas::factory()->create();
        $a = $this->buat($budi, 'RM-LAP-081', $ps, 'PS-01', 50000, 'dikonfirmasi', '2026-10-29');

        Pembayaran::factory()->create([
            'id_reservasi' => $a->id_reservasi, 'jumlah' => 25000, 'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'sukses', 'waktu_bayar' => '2026-10-29 10:00:00',
        ]);
        Pembayaran::factory()->create([
            'id_reservasi' => $a->id_reservasi, 'jumlah' => 25000, 'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending', 'waktu_bayar' => null,
        ]);

        $filter = ['mulai' => null, 'akhir' => null, 'metode' => null, 'jenis' => null, 'status' => null];
        $hasil = app(LaporanService::class)->laporanTransaksi($filter);

        $this->assertCount(1, $hasil['perHari']);
        $this->assertSame(1, $hasil['perHari'][0]['jumlah'], 'pending tanpa waktu_bayar tidak masuk grafik');
        $this->assertSame(25000.0, $hasil['perHari'][0]['total']);
        $this->assertSame(2, $hasil['perStatus']->sum('jumlah'));
        $this->assertSame(2, $hasil['perMetode'][0]['jumlah']);
        $this->assertSame(25000.0, $hasil['perMetode'][0]['total'], 'nominal metode hanya dari yang sukses');

        $panjang = app(LaporanService::class)->laporanTransaksi(array_merge($filter, [
            'mulai' => Carbon::parse('2026-01-01'), 'akhir' => Carbon::parse('2026-10-29'),
        ]));
        $this->assertTrue($panjang['perHari']->isEmpty(), 'rentang di atas 92 hari tidak merender grafik');
    }

    public function test_pelanggan_tidak_dapat_membuka_laporan(): void
    {
        $this->actingAs(Pengguna::factory()->create(['peran' => 'pelanggan']))
            ->get(route('admin.laporan.pendapatan'))
            ->assertForbidden();
    }
}

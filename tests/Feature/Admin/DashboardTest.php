<?php

namespace Tests\Feature\Admin;

use App\Models\DetailReservasi;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\Room;
use App\Services\LaporanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
    }

    private function reservasi(Pengguna $pelanggan, float $total, string $status, string $tanggal, ?string $checkin = null): Reservasi
    {
        $reservasi = Reservasi::factory()->create([
            'id_pengguna' => $pelanggan->id_pengguna,
            'total_reservasi' => $total,
            'status_reservasi' => $status,
        ]);

        DetailReservasi::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'id_room' => Room::factory()->create(['status_room' => 'digunakan'])->id_room,
            'tanggal' => $tanggal,
            'waktu_checkin' => $checkin,
        ]);

        return $reservasi;
    }

    public function test_ringkasan_dashboard_sesuai_data(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        Pengguna::factory()->create(['peran' => 'pelanggan', 'status_akun' => 'nonaktif']);
        Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);

        Room::factory()->count(2)->create(['status_room' => 'tersedia']);

        $hariIni = $this->reservasi($budi, 50000, 'dikonfirmasi', now()->toDateString());
        $this->reservasi($budi, 30000, 'dibatalkan', now()->toDateString());
        $this->reservasi($budi, 30000, 'dikonfirmasi', now()->addDay()->toDateString());
        $this->reservasi($budi, 30000, 'selesai', now()->addDay()->toDateString());

        Pembayaran::factory()->create([
            'id_reservasi' => $hariIni->id_reservasi,
            'jumlah' => 50000,
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now(),
        ]);

        $ringkasan = app(LaporanService::class)->ringkasanDashboard();

        $this->assertSame(1, $ringkasan['reservasiHariIni'], 'reservasi dibatalkan tidak dihitung');
        $this->assertSame(1, $ringkasan['reservasiMendatang'], 'hanya menunggu_pembayaran/dikonfirmasi');
        $this->assertSame(2, $ringkasan['roomTersedia']);
        $this->assertSame(4, $ringkasan['roomDigunakan']);
        $this->assertSame(1, $ringkasan['jumlahPelanggan'], 'admin dan akun nonaktif tidak dihitung');
        $this->assertSame(50000.0, $ringkasan['pendapatanHariIni']);
    }

    public function test_halaman_dashboard_merender_angka_dan_tabel(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $reservasi = $this->reservasi($budi, 75000, 'dikonfirmasi', now()->toDateString());

        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'jumlah' => 75000,
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Rp75.000')
            ->assertSee($reservasi->kode_booking)
            ->assertSee($budi->nama);
    }

    public function test_perlu_diproses_menghitung_checkin_menunggu_dan_cash_pending(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);

        $hariIni = $this->reservasi($budi, 40000, 'dikonfirmasi', now()->toDateString());
        $this->reservasi($budi, 40000, 'dikonfirmasi', now()->toDateString(), now()->toDateTimeString());

        $cash = Pembayaran::factory()->create([
            'id_reservasi' => $hariIni->id_reservasi,
            'jenis_pembayaran' => 'pelunasan',
            'metode_pembayaran' => 'cash',
            'jumlah' => 40000,
            'status_pembayaran' => 'pending',
        ]);
        Pembayaran::factory()->create([
            'id_reservasi' => $hariIni->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
        ]);

        $perlu = app(LaporanService::class)->perluDiproses();
        $this->assertCount(1, $perlu['checkinMenunggu']);
        $this->assertSame(1, $perlu['cashMenunggu']->count());

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Check-in perlu diproses')
            ->assertSee($hariIni->detailReservasi->first()->room->nama_room)
            ->assertSee('Proses')
            ->assertSee('Pembayaran perlu diverifikasi')
            ->assertSee('Cash TX-'.str_pad((string) $cash->id_pembayaran, 4, '0', STR_PAD_LEFT).' menunggu.');
    }

    public function test_dashboard_tidak_menghitung_pendapatan_yang_belum_sukses(): void
    {
        $budi = Pengguna::factory()->create(['peran' => 'pelanggan']);
        $reservasi = $this->reservasi($budi, 90000, 'menunggu_pembayaran', now()->toDateString());

        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'jumlah' => 90000,
            'status_pembayaran' => 'gagal',
            'waktu_bayar' => now(),
        ]);

        $this->assertSame(0.0, app(LaporanService::class)->ringkasanDashboard()['pendapatanHariIni']);

                $grafik = app(LaporanService::class)->grafikDashboard();
                $this->assertSame(0.0, $grafik['total'], 'grafik pendapatan juga mengabaikan pembayaran gagal');

                // Nominal reservasi tetap tampil di tabel, jadi cukup pastikan KPI pendapatan nol.
                $this->actingAs($this->admin())
                    ->get(route('admin.dashboard'))
                    ->assertOk()
                    ->assertSee('Pembayaran berhasil')
                    ->assertSee('Sumbu Y maksimum: Rp1');
            }

    public function test_pelanggan_tidak_boleh_membuka_dashboard_admin(): void
    {
        $this->actingAs(Pengguna::factory()->create(['peran' => 'pelanggan']))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}

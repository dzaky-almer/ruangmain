<?php

namespace Tests\Feature\Admin;

use App\Models\DetailReservasi;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\Room;
use App\Services\CheckinService;
use App\Services\PembayaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CheckinTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
    }

    private function pelanggan(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => 'pelanggan', 'nama' => 'Sari Dewi']);
    }

    /** Reservasi hari ini 13.00–15.00 dengan waktu sistem dibekukan pada 14.00. */
    private function reservasiHariIni(string $status = 'dikonfirmasi', bool $lunas = true, string $kode = 'RM-20261026-900'): array
    {
        Carbon::setTestNow(now()->setTime(14, 0));

        $reservasi = Reservasi::factory()->create([
            'id_pengguna' => $this->pelanggan()->id_pengguna,
            'kode_booking' => $kode,
            'status_reservasi' => $status,
            'total_reservasi' => 50000,
        ]);

        $detail = DetailReservasi::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '13:00:00',
            'jam_selesai' => '15:00:00',
        ]);

        if ($lunas) {
            Pembayaran::factory()->create([
                'id_reservasi' => $reservasi->id_reservasi,
                'jumlah' => 50000,
                'status_pembayaran' => 'sukses',
            ]);
        }

        return [$reservasi->refresh(), $detail->refresh()];
    }

    public function test_halaman_checkin_tanpa_pencarian_tetap_tampil(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.checkin.index'))
            ->assertOk()
            ->assertSee('Cari dengan kode booking dari booking pass.');
    }

    public function test_admin_dapat_mencari_reservasi_lewat_kode_booking(): void
    {
        [$reservasi] = $this->reservasiHariIni();

        $this->actingAs($this->admin())
            ->get(route('admin.checkin.index', ['kode_booking' => $reservasi->kode_booking]))
            ->assertOk()
            ->assertSee($reservasi->kode_booking)
            ->assertSee('Sari Dewi')
            ->assertSee('Syarat check-in terpenuhi.');
    }

    public function test_kode_booking_tidak_ditemukan_memberi_pesan(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.checkin.index', ['kode_booking' => 'RM-TIDAK-ADA']))
            ->assertOk()
            ->assertSee('Booking tidak ditemukan.');
    }

    public function test_halaman_checkin_menyediakan_pemindai_qr(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.checkin.index'))
            ->assertOk()
            ->assertSee('html5-qrcode', false)
            ->assertSee('data-mulai-scan', false);
    }

    public function test_checkin_ditolak_bila_belum_lunas(): void
    {
        [$reservasi, $detail] = $this->reservasiHariIni('dikonfirmasi', lunas: false);

        $this->actingAs($this->admin())
            ->get(route('admin.checkin.index', ['kode_booking' => $reservasi->kode_booking]))
            ->assertOk()
            ->assertSee('Check-in ditolak.')
            ->assertSee('reservasi belum lunas');

        $this->actingAs($this->admin())
            ->post(route('admin.checkin.simpan'), ['kode_booking' => $reservasi->kode_booking, 'id_detail' => $detail->id_detail])
            ->assertSessionHasErrors('checkin');

        $this->assertNull($detail->refresh()->waktu_checkin);
        $this->assertSame('dikonfirmasi', $reservasi->refresh()->status_reservasi);
        $this->assertSame('tersedia', Room::query()->findOrFail($detail->id_room)->status_room);
    }

    public function test_checkin_ditolak_sebelum_jam_mulai(): void
    {
        [$reservasi, $detail] = $this->reservasiHariIni();
        Carbon::setTestNow(now()->setTime(12, 30));

        $alasan = app(CheckinService::class)->alasanTolakCheckin($detail);

        $this->assertNotEmpty($alasan);
        $this->assertStringContainsString('Belum jam mulai', implode(' ', $alasan));

        $this->actingAs($this->admin())
            ->post(route('admin.checkin.simpan'), ['kode_booking' => $reservasi->kode_booking, 'id_detail' => $detail->id_detail])
            ->assertSessionHasErrors('checkin');

        $this->assertNull($detail->refresh()->waktu_checkin);
    }

    public function test_checkin_ditolak_bila_bukan_hari_ini(): void
    {
        [$reservasi, $detail] = $this->reservasiHariIni();
        $detail->update(['tanggal' => now()->addDay()->toDateString()]);

        $alasan = app(CheckinService::class)->alasanTolakCheckin($detail->refresh());

        $this->assertStringContainsString('bukan tanggal jadwal', implode(' ', $alasan));
        $this->assertNotNull($reservasi->id_reservasi);
    }

    public function test_checkin_ditolak_bila_status_reservasi_menunggu_pembayaran(): void
    {
        [, $detail] = $this->reservasiHariIni('menunggu_pembayaran');

        $alasan = app(CheckinService::class)->alasanTolakCheckin($detail);

        $this->assertStringContainsString('menunggu_pembayaran', implode(' ', $alasan));
    }

    public function test_checkin_ditolak_bila_room_tidak_tersedia(): void
    {
        [, $detail] = $this->reservasiHariIni();
        $detail->room->update(['status_room' => 'tidak_aktif']);

        $alasan = app(CheckinService::class)->alasanTolakCheckin($detail->refresh());

        $this->assertStringContainsString('tidak dapat dipakai', implode(' ', $alasan));
    }

    public function test_checkin_berhasil_mencatat_waktu_dan_mengubah_status(): void
    {
        [$reservasi, $detail] = $this->reservasiHariIni();

        $this->actingAs($this->admin())
            ->post(route('admin.checkin.simpan'), ['kode_booking' => $reservasi->kode_booking, 'id_detail' => $detail->id_detail])
            ->assertRedirect(route('admin.checkin.detail', $reservasi->kode_booking))
            ->assertSessionHas('sukses');

        $detail->refresh();
        $this->assertNotNull($detail->waktu_checkin);
        $this->assertSame('15:00:00', $detail->jam_selesai);
        $this->assertSame('digunakan', Room::query()->findOrFail($detail->id_room)->status_room);
        $this->assertSame('check_in', $reservasi->refresh()->status_reservasi);
    }

    public function test_checkin_terlambat_tidak_menggeser_jam_selesai_dan_tidak_menambah_biaya(): void
    {
        [$reservasi, $detail] = $this->reservasiHariIni();
        $totalAwal = (float) $reservasi->total_reservasi;
        Carbon::setTestNow(now()->setTime(13, 25));

        $this->actingAs($this->admin())
            ->post(route('admin.checkin.simpan'), ['kode_booking' => $reservasi->kode_booking, 'id_detail' => $detail->id_detail])
            ->assertSessionHasNoErrors();

        $detail->refresh();
        $layanan = app(CheckinService::class);

        $this->assertSame(25, $layanan->menitTerlambat($detail));
        $this->assertSame('15:00:00', $detail->jam_selesai);
        $this->assertSame($totalAwal, (float) $reservasi->refresh()->total_reservasi);
        $this->assertSame(50000.0, app(PembayaranService::class)->totalDibayar($reservasi));
    }

    public function test_checkin_kedua_kali_ditolak(): void
    {
        [$reservasi, $detail] = $this->reservasiHariIni();

        $this->actingAs($this->admin())
            ->post(route('admin.checkin.simpan'), ['kode_booking' => $reservasi->kode_booking, 'id_detail' => $detail->id_detail])
            ->assertSessionHasNoErrors();

        $alasan = app(CheckinService::class)->alasanTolakCheckin($detail->refresh());

        $this->assertStringContainsString('sudah check-in', implode(' ', $alasan));
    }

    public function test_halaman_detail_tidak_menampilkan_tolakan_setelah_checkin_berhasil(): void
    {
        [$reservasi, $detail] = $this->reservasiHariIni();

        $this->actingAs($this->admin())
            ->post(route('admin.checkin.simpan'), ['kode_booking' => $reservasi->kode_booking, 'id_detail' => $detail->id_detail])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->get(route('admin.checkin.detail', $reservasi->kode_booking))
            ->assertOk()
            ->assertSee('Room sudah check-in.')
            ->assertDontSee('Check-in ditolak.')
            ->assertDontSee('sehingga tidak dapat dipakai');
    }

    public function test_detail_room_milik_reservasi_lain_ditolak(): void
    {
        [$reservasi] = $this->reservasiHariIni();
        [, $asing] = $this->reservasiHariIni(kode: 'RM-20261026-901');

        $this->actingAs($this->admin())
            ->post(route('admin.checkin.simpan'), ['kode_booking' => $reservasi->kode_booking, 'id_detail' => $asing->id_detail])
            ->assertRedirect()
            ->assertSessionHas('galat');

        $this->assertNull($asing->refresh()->waktu_checkin);
    }

    public function test_pelanggan_tidak_dapat_membuka_checkin(): void
    {
        $this->actingAs($this->pelanggan())
            ->get(route('admin.checkin.index'))
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Console;

use App\Models\DetailReservasi;
use App\Models\Pembayaran;
use App\Models\Reservasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatalkanReservasiKedaluwarsaTest extends TestCase
{
    use RefreshDatabase;

    private function reservasi(string $status = 'menunggu_pembayaran', mixed $batas = null): Reservasi
    {
        return Reservasi::factory()->create([
            'status_reservasi' => $status,
            'batas_pembayaran' => $batas ?? now()->subMinute(),
        ]);
    }

    public function test_reservasi_lewat_batas_tanpa_pembayaran_sukses_dibatalkan(): void
    {
        $reservasi = $this->reservasi();
        $pending = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'status_pembayaran' => 'pending',
        ]);

        $this->artisan('reservasi:batalkan-kedaluwarsa')
            ->expectsOutputToContain('1 reservasi kedaluwarsa dibatalkan.')
            ->assertSuccessful();

        $this->assertSame('dibatalkan', $reservasi->refresh()->status_reservasi);
        $this->assertSame('dibatalkan', $pending->refresh()->status_pembayaran);
    }

    public function test_reservasi_sudah_dikonfirmasi_tidak_dibatalkan(): void
    {
        $reservasi = $this->reservasi('dikonfirmasi');
        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now()->subMinutes(5),
        ]);

        $this->artisan('reservasi:batalkan-kedaluwarsa')->assertSuccessful();

        $this->assertSame('dikonfirmasi', $reservasi->refresh()->status_reservasi);
    }

    public function test_reservasi_menunggu_pembayaran_tapi_sudah_ada_yang_sukses_tidak_dibatalkan(): void
    {
        $reservasi = $this->reservasi();
        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'status_pembayaran' => 'sukses',
            'jumlah' => 25000,
            'waktu_bayar' => now()->subMinutes(5),
        ]);

        $this->artisan('reservasi:batalkan-kedaluwarsa')->assertSuccessful();

        $this->assertSame('menunggu_pembayaran', $reservasi->refresh()->status_reservasi);
    }

    public function test_reservasi_belum_lewat_batas_tidak_disentuh(): void
    {
        $reservasi = $this->reservasi('menunggu_pembayaran', now()->addHour());

        $this->artisan('reservasi:batalkan-kedaluwarsa')->assertSuccessful();

        $this->assertSame('menunggu_pembayaran', $reservasi->refresh()->status_reservasi);
    }

        /** Reservasi + satu baris room dengan jam selesai tertentu. */
    private function reservasiBerjadwal(string $status, string $tanggal, string $jamSelesai, string $jamMulai = '10:00:00'): Reservasi
    {
        $reservasi = $this->reservasi($status, now()->addHour());

        DetailReservasi::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'tanggal' => $tanggal,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
        ]);

        return $reservasi;
    }

    public function test_jadwal_sudah_lewat_status_menunggu_pembayaran_dibatalkan(): void
    {
        $reservasi = $this->reservasiBerjadwal('menunggu_pembayaran', now()->subDay()->toDateString(), '12:00:00');
        $pending = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'status_pembayaran' => 'pending',
        ]);

        $this->artisan('reservasi:batalkan-kedaluwarsa')
            ->expectsOutputToContain('1 reservasi lewat jadwal dibatalkan.')
            ->assertSuccessful();

        $this->assertSame('dibatalkan', $reservasi->refresh()->status_reservasi);
        $this->assertSame('dibatalkan', $pending->refresh()->status_pembayaran);
    }

    public function test_jadwal_sudah_lewat_status_dikonfirmasi_tetap_dibatalkan_walau_sudah_dp(): void
    {
        $reservasi = $this->reservasiBerjadwal('dikonfirmasi', now()->subDay()->toDateString(), '12:00:00');
        $sukses = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'status_pembayaran' => 'sukses',
            'waktu_bayar' => now()->subMinutes(5),
        ]);

        $this->artisan('reservasi:batalkan-kedaluwarsa')->assertSuccessful();

        $this->assertSame('dibatalkan', $reservasi->refresh()->status_reservasi);
        // Uang yang sudah masuk tidak diubah, hanya dicatat tetap 'sukses'.
        $this->assertSame('sukses', $sukses->refresh()->status_pembayaran);
    }

    public function test_jadwal_sudah_lewat_status_check_in_dibatalkan_dan_room_dilepas(): void
    {
        $reservasi = $this->reservasiBerjadwal('check_in', now()->subDay()->toDateString(), '12:00:00');
        $detail = $reservasi->detailReservasi()->firstOrFail();
        $detail->room->update(['status_room' => 'digunakan']);

        $this->artisan('reservasi:batalkan-kedaluwarsa')->assertSuccessful();

        $this->assertSame('dibatalkan', $reservasi->refresh()->status_reservasi);
        $this->assertSame('tersedia', $detail->room->refresh()->status_room);
    }

    public function test_jadwal_besok_tidak_dibatalkan(): void
    {
        $reservasi = $this->reservasiBerjadwal('dikonfirmasi', now()->addDay()->toDateString(), '12:00:00');

        $this->artisan('reservasi:batalkan-kedaluwarsa')->assertSuccessful();

        $this->assertSame('dikonfirmasi', $reservasi->refresh()->status_reservasi);
    }

    public function test_jadwal_hari_ini_tapi_belum_selesai_tidak_dibatalkan(): void
    {
        // Waktu dibekukan jam 14.00 supaya slot 15.00–17.00 pasti masuk jam operasional (10.00–21.00).
        $this->travelTo(now()->startOfDay()->setTime(14, 0));

        $reservasi = $this->reservasiBerjadwal('dikonfirmasi', now()->toDateString(), '17:00:00', '15:00:00');

        $this->artisan('reservasi:batalkan-kedaluwarsa')->assertSuccessful();

        $this->assertSame('dikonfirmasi', $reservasi->refresh()->status_reservasi);
    }

    public function test_jadwal_sudah_lewat_status_selesai_tidak_disentuh(): void
    {
        $reservasi = $this->reservasiBerjadwal('selesai', now()->subDay()->toDateString(), '12:00:00');

        $this->artisan('reservasi:batalkan-kedaluwarsa')->assertSuccessful();

        $this->assertSame('selesai', $reservasi->refresh()->status_reservasi);
    }
    }

<?php

namespace Tests\Feature\Database;

use App\Models\DetailReservasi;
use App\Models\Pembayaran;
use App\Models\Reservasi;
use App\Models\Room;
use App\Services\PembayaranService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RiwayatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);
    }

    public function test_data_demo_mencakup_semua_status_reservasi_dan_pembayaran(): void
    {
        $this->assertSame(
            ['check_in', 'dibatalkan', 'dikonfirmasi', 'menunggu_pembayaran', 'selesai'],
            Reservasi::query()->distinct()->pluck('status_reservasi')->sort()->values()->all(),
        );

        $this->assertSame(
            ['cash', 'qris'],
            Pembayaran::query()->distinct()->pluck('metode_pembayaran')->sort()->values()->all(),
        );

        $statusPembayaran = Pembayaran::query()->distinct()->pluck('status_pembayaran')->all();

        foreach (['gagal', 'pending', 'sukses'] as $status) {
            $this->assertContains($status, $statusPembayaran);
        }

        $this->assertSame(
            ['dp', 'pelunasan'],
            Pembayaran::query()->distinct()->pluck('jenis_pembayaran')->sort()->values()->all(),
        );
    }

    public function test_detail_demo_menghormati_batasan_durasi_jam_dan_subtotal(): void
    {
        $detail = DetailReservasi::query()->with('room')->get();

        $this->assertNotEmpty($detail);

        foreach ($detail as $baris) {
            $this->assertSame($baris->room->hargaEfektif(), (float) $baris->harga_per_jam);
            $this->assertEquals($baris->harga_per_jam * $baris->durasi_jam, (float) $baris->subtotal);
            $this->assertGreaterThanOrEqual('10:00:00', $baris->jam_mulai);
            $this->assertLessThanOrEqual('21:00:00', $baris->jam_selesai);
            $this->assertGreaterThan($baris->jam_mulai, $baris->jam_selesai);
        }
    }

    public function test_total_reservasi_demo_sama_dengan_jumlah_subtotal_detail(): void
    {
        $daftar = Reservasi::query()->with('detailReservasi')->get();

        $this->assertCount(10 + RiwayatSeeder::JUMLAH_RESERVASI, $daftar);

        foreach ($daftar as $reservasi) {
            $this->assertEquals(
                $reservasi->detailReservasi->sum(fn (DetailReservasi $baris): float => (float) $baris->subtotal),
                (float) $reservasi->total_reservasi,
            );
        }
    }

    public function test_jumlah_pembayaran_demo_mengikuti_aturan_dp_dan_pelunasan(): void
    {
        foreach (Pembayaran::query()->with('reservasi')->get() as $pembayaran) {
            $diharapkan = $pembayaran->jenis_pembayaran === 'dp'
                ? (float) $pembayaran->reservasi->total_reservasi * PembayaranService::PERSEN_DP
                : (float) $pembayaran->reservasi->total_reservasi - Pembayaran::query()
                    ->where('id_reservasi', $pembayaran->id_reservasi)
                    ->where('jenis_pembayaran', 'dp')
                    ->where('status_pembayaran', 'sukses')
                    ->sum('jumlah');

            $this->assertEqualsWithDelta($diharapkan, (float) $pembayaran->jumlah, 0.01);
        }
    }

    public function test_room_yang_sedang_dipakai_ikut_ditandai(): void
    {
        $idRoomAktif = DetailReservasi::query()
            ->whereNotNull('waktu_checkin')
            ->whereNull('waktu_checkout')
            ->pluck('id_room');

        $this->assertNotEmpty($idRoomAktif);
        $this->assertSame(
            $idRoomAktif->sort()->values()->all(),
            Room::query()->where('status_room', 'digunakan')->pluck('id_room')->sort()->values()->all(),
        );
    }
}

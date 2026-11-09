<?php

namespace Tests\Feature\Admin;

use App\Models\DetailReservasi;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Reservasi;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => Pengguna::PERAN_ADMIN]);
    }

    /** Detail reservasi hari ini 13.00???15.00, waktu sistem 14.00. */
    private function detail(string $kode = 'RM-20261026-800'): DetailReservasi
    {
        Carbon::setTestNow(now()->setTime(14, 0));

        $reservasi = Reservasi::factory()->create([
            'id_pengguna' => Pengguna::factory()->create(['peran' => 'pelanggan', 'nama' => 'Sari Dewi'])->id_pengguna,
            'kode_booking' => $kode,
            'status_reservasi' => 'dikonfirmasi',
            'total_reservasi' => 50000,
        ]);

        Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'jumlah' => 50000,
            'status_pembayaran' => 'sukses',
        ]);

        return DetailReservasi::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '13:00:00',
            'jam_selesai' => '15:00:00',
        ]);
    }

    public function test_halaman_checkout_kosong_menampilkan_keadaan_awal(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.checkout.index'))
            ->assertOk()
            ->assertSee('Tidak ada room aktif')
            ->assertSee('Belum ada detail reservasi yang sudah check-in');
    }

    public function test_halaman_checkout_menampilkan_room_yang_sedang_digunakan(): void
    {
        $detail = $this->detail();
        $detail->update(['waktu_checkin' => now()->setTime(13, 10)]);
        $detail->room->update(['status_room' => 'digunakan']);

        $this->actingAs($this->admin())
            ->get(route('admin.checkout.index'))
            ->assertOk()
            ->assertSee($detail->room->nama_room)
            ->assertSee('RM-20261026-800')
            ->assertSee('Sari Dewi')
            ->assertSee('Terlambat 10 menit')
            ->assertSee('Tindak lanjuti keterlambatan.');
    }

    public function test_checkout_ditolak_bila_belum_checkin(): void
    {
        $detail = $this->detail();

        $this->actingAs($this->admin())
            ->post(route('admin.checkout.simpan'), ['kode_booking' => $detail->reservasi->kode_booking, 'id_detail' => $detail->id_detail])
            ->assertSessionHasErrors('checkout');

        $this->assertNull($detail->refresh()->waktu_checkout);
    }

            public function test_checkout_ditolak_bila_kode_booking_bukan_pemilik_detail(): void
            {
                $detail = $this->detail();
                $detail->update(['waktu_checkin' => now()->setTime(13, 5)]);

                $lain = Reservasi::factory()->create(['kode_booking' => 'RM-20261026-999']);

                $this->actingAs($this->admin())
                    ->post(route('admin.checkout.simpan'), ['kode_booking' => $lain->kode_booking, 'id_detail' => $detail->id_detail])
                    ->assertSessionHas('galat');

                $this->assertNull($detail->refresh()->waktu_checkout);
            }

    public function test_checkout_berhasil_mencatat_waktu_dan_mengembalikan_room(): void
    {
        $detail = $this->detail();
        $detail->update(['waktu_checkin' => now()->setTime(13, 5)]);
        $detail->room->update(['status_room' => 'digunakan']);

        $this->actingAs($this->admin())
            ->post(route('admin.checkout.simpan'), ['kode_booking' => $detail->reservasi->kode_booking, 'id_detail' => $detail->id_detail])
            ->assertRedirect(route('admin.checkout.index'))
            ->assertSessionHas('sukses');

        $detail->refresh();
        $this->assertNotNull($detail->waktu_checkout);
        $this->assertSame('15:00:00', $detail->jam_selesai);
        $this->assertSame('tersedia', Room::query()->findOrFail($detail->id_room)->status_room);
        $this->assertSame('selesai', Reservasi::query()->findOrFail($detail->id_reservasi)->status_reservasi);
    }

    public function test_reservasi_belum_selesai_bila_masih_ada_room_lain_aktif(): void
    {
        $detail = $this->detail();
        $detail->update(['waktu_checkin' => now()->setTime(13, 5)]);

        $kedua = DetailReservasi::factory()->create([
            'id_reservasi' => $detail->id_reservasi,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '15:00:00',
            'jam_selesai' => '17:00:00',
            'waktu_checkin' => now()->setTime(15, 0),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.checkout.simpan'), ['kode_booking' => $detail->reservasi->kode_booking, 'id_detail' => $detail->id_detail])
            ->assertSessionHasNoErrors();

        $this->assertSame('dikonfirmasi', Reservasi::query()->findOrFail($detail->id_reservasi)->status_reservasi);
        $this->assertNull($kedua->refresh()->waktu_checkout);
    }

    public function test_checkout_kedua_kali_ditolak(): void
    {
        $detail = $this->detail();
        $detail->update(['waktu_checkin' => now()->setTime(13, 5)]);

        $this->actingAs($this->admin())->post(route('admin.checkout.simpan'), ['kode_booking' => $detail->reservasi->kode_booking, 'id_detail' => $detail->id_detail]);

        $this->actingAs($this->admin())
            ->post(route('admin.checkout.simpan'), ['kode_booking' => $detail->reservasi->kode_booking, 'id_detail' => $detail->id_detail])
            ->assertSessionHasErrors('checkout');
    }

    public function test_checkout_tidak_menambah_biaya(): void
    {
        $detail = $this->detail();
        $detail->update(['waktu_checkin' => now()->setTime(12, 55)]);

        $this->actingAs($this->admin())->post(route('admin.checkout.simpan'), ['kode_booking' => $detail->reservasi->kode_booking, 'id_detail' => $detail->id_detail]);

        $this->assertSame('50000.00', (string) Reservasi::query()->findOrFail($detail->id_reservasi)->total_reservasi);
        $this->assertSame('50000.00', (string) $detail->refresh()->subtotal);
    }

    public function test_navbar_admin_memuat_tautan_checkin_dan_checkout(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(e(route('admin.checkin.index')), false)
            ->assertSee(e(route('admin.checkout.index')), false);
    }
}



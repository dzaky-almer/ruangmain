<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Pembayaran;
use App\Models\Reservasi;
use App\Services\MidtransGateway;
use App\Services\MockQrisGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'SB-Mid-server-uji';

    private function gateway(): MidtransGateway
    {
        return new MidtransGateway(self::KUNCI, false, new MockQrisGateway('rahasia-uji'));
    }

    /** @param array<string, mixed> $ubah */
    private function notifikasi(array $ubah = []): array
    {
        $payload = array_merge([
            'order_id' => 'RM-20261102-001-9',
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'transaction_status' => 'settlement',
        ], $ubah);

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].self::KUNCI,
        );

        return $payload;
    }

    public function test_notifikasi_sah_diterjemahkan_ke_payload_internal(): void
    {
        $hasil = $this->gateway()->terjemahkan($this->notifikasi());

        $this->assertSame('RM-20261102-001-9', $hasil['id_transaksi']);
        $this->assertSame('sukses', $hasil['status']);
        $this->assertNotEmpty($hasil['tanda_tangan']);
    }

    public function test_tanda_tangan_palsu_ditolak(): void
    {
        $payload = $this->notifikasi();
        $payload['signature_key'] = str_repeat('a', 128);

        $this->assertNull($this->gateway()->terjemahkan($payload));
    }

    public function test_status_di_luar_peta_dan_pending_diabaikan(): void
    {
        $gateway = $this->gateway();

        $this->assertNull($gateway->terjemahkan($this->notifikasi(['transaction_status' => 'pending'])));
        $this->assertNull($gateway->terjemahkan($this->notifikasi(['transaction_status' => 'refund'])));
    }

    public function test_status_gagal_dan_sukses_dipetakan(): void
    {
        $gateway = $this->gateway();

        foreach (['expire', 'cancel', 'deny'] as $status) {
            $this->assertSame('gagal', $gateway->terjemahkan($this->notifikasi(['transaction_status' => $status]))['status']);
        }

        foreach (['settlement', 'capture'] as $status) {
            $this->assertSame('sukses', $gateway->terjemahkan($this->notifikasi(['transaction_status' => $status]))['status']);
        }
    }

    public function test_payload_tidak_lengkap_diabaikan(): void
    {
        $payload = $this->notifikasi();
        unset($payload['gross_amount']);

        $this->assertNull($this->gateway()->terjemahkan($payload));
    }

    public function test_tanda_tangan_hasil_terjemahan_diterima_gateway(): void
    {
        $gateway = $this->gateway();
        $hasil = $gateway->terjemahkan($this->notifikasi());

        $this->assertTrue($gateway->tandaTanganSah($hasil['id_transaksi'], $hasil['status'], $hasil['tanda_tangan']));
    }

    public function test_webhook_midtrans_menyelesaikan_pembayaran(): void
    {
        $this->app->instance(PaymentGateway::class, $this->gateway());

        $reservasi = Reservasi::factory()->create(['kode_booking' => 'RM-20261102-001']);
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
            'id_transaksi_gateway' => 'RM-20261102-001-'.$reservasi->id_reservasi,
        ]);

        $this->postJson(route('pembayaran.callback'), $this->notifikasi([
            'order_id' => (string) $pembayaran->id_transaksi_gateway,
        ]))->assertOk()->assertJson(['diterima' => true, 'diproses' => true]);

        $this->assertSame('sukses', $pembayaran->refresh()->status_pembayaran);
    }

    public function test_webhook_midtrans_tanpa_tanda_tangan_sah_tidak_mengubah_status(): void
    {
        $this->app->instance(PaymentGateway::class, $this->gateway());

        $reservasi = Reservasi::factory()->create(['kode_booking' => 'RM-20261102-002']);
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
            'id_transaksi_gateway' => 'RM-20261102-002-'.$reservasi->id_reservasi,
        ]);

        $payload = $this->notifikasi(['order_id' => (string) $pembayaran->id_transaksi_gateway]);
        $payload['signature_key'] = str_repeat('b', 128);

        $this->postJson(route('pembayaran.callback'), $payload)
            ->assertOk()
            ->assertJson(['diterima' => true, 'diproses' => false]);

        $this->assertSame('pending', $pembayaran->refresh()->status_pembayaran);
    }

            public function test_webhook_tanpa_signature_key_tidak_dapat_menyelesaikan_pembayaran(): void
    {
        $this->app->instance(PaymentGateway::class, $this->gateway());

        $reservasi = Reservasi::factory()->create(['kode_booking' => 'RM-20261102-005']);
        $pembayaran = Pembayaran::factory()->create([
            'id_reservasi' => $reservasi->id_reservasi,
            'metode_pembayaran' => 'qris',
            'status_pembayaran' => 'pending',
            'id_transaksi_gateway' => 'RM-20261102-005-'.$reservasi->id_reservasi,
        ]);

        $id = (string) $pembayaran->id_transaksi_gateway;

        // Tanda tangan HMAC simulator tidak boleh diterima saat Midtrans aktif.
        $this->postJson(route('pembayaran.callback'), [
            'id_transaksi' => $id,
            'status' => 'sukses',
                    'tanda_tangan' => (new MockQrisGateway('rahasia-uji'))->tandaTanganSimulasi($id, 'sukses'),
        ])->assertOk()->assertJson(['diterima' => true, 'diproses' => false]);

        $this->assertSame('pending', $pembayaran->refresh()->status_pembayaran);
    }

            public function test_order_id_terkunci_memutar_id_baru_dan_tetap_mendapat_tautan(): void
            {
                $gateway = new class(self::KUNCI, false, new MockQrisGateway('rahasia-uji')) extends MidtransGateway
                {
                    /** @var list<string> */
                    public array $terlihat = [];

                    protected function mintaToken(string $orderId, Pembayaran $pembayaran): string
                    {
                        $this->terlihat[] = $orderId;

                        if (count($this->terlihat) === 1) {
                            throw new \Exception('Midtrans API is returning API error. HTTP status code: 400 ... already been taken');
                        }

                        return 'https://app.sandbox.midtrans.com/snap/v4/redirection/'.$orderId;
                    }
                };

                $reservasi = Reservasi::factory()->create(['kode_booking' => 'RM-20261102-003']);
                $pembayaran = Pembayaran::factory()->create([
                    'id_reservasi' => $reservasi->id_reservasi,
                    'metode_pembayaran' => 'qris',
                    'status_pembayaran' => 'pending',
                    'id_transaksi_gateway' => 'RM-20261102-003-'.$reservasi->id_reservasi,
                ]);

                $lama = (string) $pembayaran->id_transaksi_gateway;
                $tautan = $gateway->tautanBayar($pembayaran);
                $terlihat = $gateway->terlihat;

                $this->assertCount(2, $terlihat);
                $this->assertSame($lama, $terlihat[0]);
                $this->assertStringStartsWith($lama.'-', $terlihat[1]);
                $this->assertSame('https://app.sandbox.midtrans.com/snap/v4/redirection/'.$terlihat[1], $tautan);
                $this->assertSame($terlihat[1], $pembayaran->refresh()->id_transaksi_gateway);
            }

            public function test_snap_gagal_dua_kali_mengembalikan_null_tanpa_exception(): void
            {
                $gateway = new class(self::KUNCI, false, new MockQrisGateway('rahasia-uji')) extends MidtransGateway
                {
                    protected function mintaToken(string $orderId, Pembayaran $pembayaran): string
                    {
                        throw new \Exception('Snap down');
                    }
                };

                $reservasi = Reservasi::factory()->create(['kode_booking' => 'RM-20261102-004']);
                $pembayaran = Pembayaran::factory()->create([
                    'id_reservasi' => $reservasi->id_reservasi,
                    'metode_pembayaran' => 'qris',
                    'status_pembayaran' => 'pending',
                    'id_transaksi_gateway' => 'RM-20261102-004-'.$reservasi->id_reservasi,
                ]);

                $this->assertNull($gateway->tautanBayar($pembayaran));
            }
        }

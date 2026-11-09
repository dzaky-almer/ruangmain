<?php

// Dipakai oleh: AppServiceProvider (dipakai saat MIDTRANS_SERVER_KEY kosong atau
//               environment testing) dan MidtransGateway (sebagai fallback).
// Fungsi: implementasi PaymentGateway tiruan — tidak menghubungi penyedia nyata,
//         tanda tangan pakai HMAC-SHA256 dengan rahasia dari config('services.qris.rahasia').
// Catatan: tidak ada command artisan; aktif otomatis lewat binding container.

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Pembayaran;
use Illuminate\Support\Str;

/**
 * Simulasi gateway QRIS: tidak menghubungi penyedia nyata.
 * Tanda tangan memakai HMAC token rahasia sederhana (SECURITY.md bagian 5).
 */
class MockQrisGateway implements PaymentGateway
{
    // $rahasia di-inject AppServiceProvider dari config('services.qris.rahasia')
    // (env QRIS_MOCK_SECRET, default 'ruangmain-demo-qris-secret').
    public function __construct(private readonly string $rahasia) {}

    // Dipanggil PembayaranService; hasilnya disimpan ke pembayaran.id_transaksi_gateway.
    public function buatTransaksi(Pembayaran $pembayaran): string
    {
        return 'MOCK-'.Str::uuid()->toString();
    }

    // Dipanggil PembayaranCallbackController sebelum menandai pembayaran sukses.
    // hash_equals() dipakai agar perbandingan tidak bisa ditebak lewat timing attack.
    public function tandaTanganSah(string $idTransaksi, string $status, string $tandaTangan): bool
    {
        return hash_equals($this->tandaTangan($idTransaksi, $status), $tandaTangan);
    }

    // Dipakai view status pembayaran untuk membuat tombol simulator klik-otomatis.
    public function tandaTanganSimulasi(string $idTransaksi, string $status): ?string
    {
        return $this->tandaTangan($idTransaksi, $status);
    }

    // Inti keamanan: tanda tangan = HMAC(id transaksi + status) memakai rahasia server.
    private function tandaTangan(string $idTransaksi, string $status): string
    {
        return hash_hmac('sha256', $idTransaksi.'|'.$status, $this->rahasia);
    }
}

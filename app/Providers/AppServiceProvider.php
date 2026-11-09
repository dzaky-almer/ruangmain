<?php

// File ini menyiapkan layanan yang dibutuhkan aplikasi saat pertama kali dijalankan.
// Fungsi utama di sini: menentukan gateway pembayaran mana yang dipakai.
// Ada 2 pilihan:
//   - MockQrisGateway  → gateway tiruan (untuk latihan/pengujian, tanpa uang asli)
//   - MidtransGateway  → gateway asli (butuh server key Midtrans)
// Pemilihannya otomatis: kalau server key kosong atau sedang mode testing,
// pakai yang tiruan.
//
// Cara menyetel kunci asli: isi MIDTRANS_SERVER_KEY di file .env
// Untuk menguji:
//   php artisan test

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\MidtransGateway;
use App\Services\MockQrisGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    // Dijalankan sekali saat aplikasi menyala, sebelum halaman diproses.
    // Di sini Laravel diberi tahu: "kalau ada yang minta PaymentGateway,
    // buatkan objek ini".
    // Karena pakai bind(), objek dibuat ulang setiap kali diminta.
    // Kalau mau dibuat sekali saja lalu dipakai bersama, ganti bind() jadi singleton().
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            $serverKey = (string) config('services.midtrans.server_key');

            // Tanpa kunci, atau saat pengujian, aplikasi tetap memakai gateway tiruan.
            if ($serverKey === '' || $this->app->environment('testing')) {
                return new MockQrisGateway(config('services.qris.rahasia'));
            }

            // Kalau ada kunci asli: pakai Midtrans, tapi tetap menyiapkan
            // MockQrisGateway sebagai cadangan.
            return new MidtransGateway(
                $serverKey,
                (bool) config('services.midtrans.is_production'),
                new MockQrisGateway(config('services.qris.rahasia')),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    // Dijalankan setelah semua provider terdaftar.
    // Saat ini kosong karena tidak ada yang perlu disiapkan lagi.
    public function boot(): void
    {
        //
    }
}

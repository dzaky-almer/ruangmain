<?php

// File ini adalah titik awal aplikasi — dijalankan pertama kali setiap ada permintaan.
// Isinya 3 hal:
//   1. withRouting()     → di mana file route disimpan.
//   2. withMiddleware()  → daftar middleware (penjaga sebelum halaman dibuka).
//   3. withExceptions()  → pengaturan saat terjadi error.
//
// Ini file bawaan Laravel 11+. Sebelumnya, pengaturan ini tersebar di
// app/Http/Kernel.php dan app/Exceptions/Handler.php. Sekarang dikumpulkan di sini.
//
// Untuk menguji: php artisan test

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// dirname(__DIR__) = folder induk dari folder bootstrap, yaitu akar project.
return Application::configure(basePath: dirname(__DIR__))
    // Daftar file route:
    //   web      → routes/web.php (halaman yang dibuka di browser)
    //   commands → routes/console.php (perintah terminal & jadwal otomatis)
    //   health   → alamat /up untuk mengecek aplikasi masih hidup
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias middleware: memberi nama pendek pada class middleware.
        // Dengan ini, di routes/web.php cukup menulis 'peran:admin', bukan
        // nama class lengkapnya.
        $middleware->alias([
            'peran' => \App\Http\Middleware\PeranMiddleware::class,
        ]);

        // Gateway eksternal tidak dapat mengirim token CSRF; keaslian dijamin tanda tangan (SECURITY.md §5).
        // CSRF = token pengaman yang ditempel di setiap form, supaya form tidak bisa
        // dikirim dari situs lain.
        // Alamat webhook pembayaran dikecualikan karena yang memanggilnya server Midtrans,
        // bukan form milik kita — jadi tidak punya token CSRF.
        $middleware->validateCsrfTokens(except: ['pembayaran/callback']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Menentukan kapan error dikirim sebagai JSON, bukan halaman HTML.
        // Contoh: kalau alamatnya diawali /api, atau pemanggilnya minta JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

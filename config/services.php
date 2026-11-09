<?php

// File ini menyimpan data akses (kunci rahasia) untuk layanan luar.
// Isinya diambil dari file .env supaya kunci tidak ikut tersimpan di kode.
// Yang dipakai proyek ini: bagian `qris` dan `midtrans` (pembayaran).
// Bagian postmark/resend/ses/slack adalah bawaan Laravel dan tidak dipakai.
//
// Untuk melihat isi yang terbaca:
//   php artisan config:show services

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway QRIS (simulasi)
    |--------------------------------------------------------------------------
    |
    | `MockQrisGateway` memakai token rahasia sederhana sebagai pengganti tanda
    | tangan gateway asli (SECURITY.md bagian 5).
    |
    */

    'qris' => [
        'rahasia' => env('QRIS_MOCK_SECRET', 'ruangmain-demo-qris-secret'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Midtrans (Snap)
    |--------------------------------------------------------------------------
    |
    | `MidtransGateway` dipakai hanya bila `server_key` terisi. Tanpa kunci,
    | aplikasi tetap memakai `MockQrisGateway` (SECURITY.md bagian 5).
    |
    */

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],
];

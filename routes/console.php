<?php

// File ini berisi pengaturan tugas otomatis (scheduler) dan perintah terminal
// sederhana yang ditulis langsung di sini.
// Bedanya dengan app/Console/Commands/: perintah di sini ditulis langsung di file ini,
// sedangkan yang di folder Commands punya file sendiri.
//
// Untuk melihat semua perintah: php artisan list
// Untuk menjalankan scheduler secara manual: php artisan schedule:list

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Contoh perintah bawaan Laravel: menampilkan kalimat penyemangat.
// Coba: php artisan inspire
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ARCHITECTURE.md §3.5: pembatalan reservasi kedaluwarsa diperiksa tiap menit.
// Artinya: Laravel menjalankan `php artisan reservasi:batalkan-kedaluwarsa`
// secara otomatis tiap menit.
// `withoutOverlapping()` mencegah perintah dijalankan dua kali bersamaan
// kalau proses sebelumnya belum selesai.
// Syarat: scheduler harus hidup, dijalankan dengan `php artisan schedule:work`
// (atau cron di server sungguhan).
Schedule::command('reservasi:batalkan-kedaluwarsa')->everyMinute()->withoutOverlapping();

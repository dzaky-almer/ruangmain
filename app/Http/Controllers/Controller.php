<?php

// File ini adalah induk (abstract class) untuk semua controller di folder ini.
// Fungsi: menyediakan class dasar supaya controller lain bisa memakai `extends Controller`.
// Saat ini belum ada isi, karena semua kebutuhan sudah disediakan Laravel.
// Dipakai oleh: seluruh file di app/Http/Controllers/**.
//
// Cara membuat controller baru:
//   php artisan make:controller NamaController
// File baru langsung muncul di folder app/Http/Controllers.

namespace App\Http\Controllers;

abstract class Controller
{
    // Tempat untuk helper yang dipakai bersama banyak controller.
    // Masih kosong karena belum ada yang dibutuhkan.
}

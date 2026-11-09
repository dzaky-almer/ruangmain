<?php

// Jalankan: php artisan db:seed          (atau `php artisan migrate:fresh --seed` saat DB kosong)
// Fungsi: seeder utama — mengisi akun contoh, 3 fasilitas, 13 room, lalu memanggil
//         RiwayatSeeder untuk data riwayat reservasi.
// Hasil: 3 user, 3 fasilitas, 13 room, 15 pelanggan tambahan, dan 30 reservasi historis.
// Catatan: urutan WAJIB fasilitas dulu, karena room butuh id_fasilitas.

namespace Database\Seeders;

use App\Models\Fasilitas;
use App\Models\Pengguna;
use App\Models\Room;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Dijalankan Laravel saat `db:seed` tanpa `--class`, karena ini seeder default.
class DatabaseSeeder extends Seeder
{
    // Tanpa trait ini, event model (misal observer) akan ikut terpicu saat seeding.
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    // php artisan db:seed
    public function run(): void
    {
        // forceCreate() dipakai karena kolom `peran` & `status_akun` TIDAK ada di $fillable
        // Pengguna — create() biasa akan diam-diam mengabaikan dua kolom itu.
        Pengguna::forceCreate([
            'nama' => 'Admin RUANGMAIN',
            'email' => 'admin@ruangmain.test',
            'no_hp' => '081200000001',
            'password' => Hash::make('Admin123!'),
            'peran' => 'admin',
            'status_akun' => 'aktif',
        ]);

        Pengguna::forceCreate([
            'nama' => 'Budi Santoso',
            'email' => 'budi@ruangmain.test',
            'no_hp' => '081200000002',
            'password' => Hash::make('Pelanggan123!'),
            'peran' => 'pelanggan',
            'status_akun' => 'aktif',
        ]);

        Pengguna::forceCreate([
            'nama' => 'Sari Wulandari',
            'email' => 'sari@ruangmain.test',
            'no_hp' => '081200000003',
            'password' => Hash::make('Pelanggan123!'),
            'peran' => 'pelanggan',
            'status_akun' => 'aktif',
        ]);

        // 3 kategori fasilitas. Kolom `tagline` dipakai view katalog sebagai teks card.
        foreach ([
            ['nama_fasilitas' => 'PlayStation', 'tagline' => 'Couch co-op, no lag.', 'deskripsi' => 'Room privat dengan PlayStation dan TV layar besar.', 'status_fasilitas' => 'aktif'],
            ['nama_fasilitas' => 'Nintendo', 'tagline' => 'Party game, tawa ramai.', 'deskripsi' => 'Room privat dengan Nintendo Switch untuk main bareng.', 'status_fasilitas' => 'aktif'],
            ['nama_fasilitas' => 'Netflix Room', 'tagline' => 'movie night?', 'deskripsi' => 'Room nonton Netflix dengan TV layar besar dan sofa nyaman.', 'status_fasilitas' => 'aktif'],
        ] as $dataFasilitas) {
            Fasilitas::query()->create($dataFasilitas);
        }

        // Peta nama -> id_fasilitas, supaya daftar room di bawah bisa menyebut nama saja.
        $idFasilitas = Fasilitas::query()->pluck('id_fasilitas', 'nama_fasilitas');

        // 13 room. Harga per jam disimpan di room, tiap room bisa beda harga.
        // Jumlah 13 sengaja > 7 karena DemoSeeder membutuhkan minimal 7 room.
        foreach ([
            ['nama_room' => 'PlayStation Reguler', 'kapasitas' => 4, 'harga_per_jam' => 20000, 'fasilitas' => 'PlayStation'],
            ['nama_room' => 'PlayStation VIP', 'kapasitas' => 4, 'harga_per_jam' => 30000, 'fasilitas' => 'PlayStation'],
            ['nama_room' => 'PlayStation VVIP', 'kapasitas' => 4, 'harga_per_jam' => 40000, 'fasilitas' => 'PlayStation'],
            ['nama_room' => 'Nintendo Reguler', 'kapasitas' => 4, 'harga_per_jam' => 20000, 'fasilitas' => 'Nintendo'],
            ['nama_room' => 'Nintendo VIP', 'kapasitas' => 4, 'harga_per_jam' => 30000, 'fasilitas' => 'Nintendo'],
            ['nama_room' => 'Netflix Room Reguler', 'kapasitas' => 6, 'harga_per_jam' => 20000, 'fasilitas' => 'Netflix Room'],
            ['nama_room' => 'Netflix Room VIP', 'kapasitas' => 6, 'harga_per_jam' => 30000, 'fasilitas' => 'Netflix Room'],
            ['nama_room' => 'PlayStation Reguler 2', 'kapasitas' => 4, 'harga_per_jam' => 20000, 'fasilitas' => 'PlayStation'],
            ['nama_room' => 'PlayStation VIP 2', 'kapasitas' => 4, 'harga_per_jam' => 30000, 'fasilitas' => 'PlayStation'],
            ['nama_room' => 'Nintendo Reguler 2', 'kapasitas' => 4, 'harga_per_jam' => 20000, 'fasilitas' => 'Nintendo'],
            ['nama_room' => 'Nintendo VIP 2', 'kapasitas' => 4, 'harga_per_jam' => 30000, 'fasilitas' => 'Nintendo'],
            ['nama_room' => 'Netflix Room Reguler 2', 'kapasitas' => 6, 'harga_per_jam' => 20000, 'fasilitas' => 'Netflix Room'],
            ['nama_room' => 'Netflix Room VIP 2', 'kapasitas' => 6, 'harga_per_jam' => 30000, 'fasilitas' => 'Netflix Room'],
        ] as $dataRoom) {
            Room::query()->create([
                // Terjemahkan nama fasilitas dari daftar di atas jadi id nyata.
                'id_fasilitas' => $idFasilitas[$dataRoom['fasilitas']],
                'nama_room' => $dataRoom['nama_room'],
                'kapasitas' => $dataRoom['kapasitas'],
                'harga_per_jam' => $dataRoom['harga_per_jam'],
                'status_room' => 'tersedia',
            ]);
        }

        // Seeder riwayat reservasi (15 pelanggan + 30 reservasi historis + pembayarannya).
        $this->call(RiwayatSeeder::class);
    }
}

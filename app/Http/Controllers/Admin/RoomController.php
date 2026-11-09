<?php

// File ini mengatur data room dari sisi admin (tambah, lihat, ubah, hapus).
// Fungsi: CRUD room beserta filter pencarian di halaman admin.
// Dipakai oleh: routes/web.php baris 61-62 (Route::resource('room', ...)).
// Mengambil data dari model: Room.php dan Fasilitas.php.
// Validasi input ditangani oleh: app/Http/Requests/SimpanRoomRequest.php.
//
// Untuk menyiapkan data uji room, jalankan seeder:
//   php artisan db:seed --class=DatabaseSeeder
// Untuk menguji fitur CRUD room:
//   php artisan test --filter=Room

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanRoomRequest;
use App\Models\Fasilitas;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomController extends Controller
{
    // GET /admin/room — daftar room dengan pencarian, filter fasilitas, dan filter status.
    // Untuk mengujinya:
    //   php artisan test --filter=RoomTest
    public function index(Request $request): View
    {
        $room = Room::query()
            // Relasi dimuat sekaligus supaya view tidak memicu query berulang (N+1).
            // `detailReservasi.reservasi` dipakai untuk menandai room yang sudah dipesan.
            ->with(['fasilitas', 'detailReservasi.reservasi'])
            // Filter pencarian nama room, hanya aktif kalau kolom cari diisi.
            ->when($request->string('cari')->trim()->value(), fn ($query, string $cari) => $query->where('nama_room', 'like', "%{$cari}%"))
            // Filter berdasarkan id fasilitas.
            ->when($request->integer('fasilitas'), fn ($query, int $fasilitas) => $query->where('id_fasilitas', $fasilitas))
            // Filter status; nilai di luar daftar ini diabaikan.
            ->when(in_array($request->input('status'), ['tersedia', 'dibooking', 'digunakan', 'tidak_aktif'], true), fn ($query, string $status) => $query->where('status_room', $status))
            ->orderBy('nama_room')
            ->paginate(6)
            // withQueryString agar filter tetap terbawa saat pindah halaman.
            ->withQueryString();

        return view('admin.room.index', [
            'room' => $room,
            // Daftar semua fasilitas untuk mengisi dropdown filter dan form.
            'semuaFasilitas' => Fasilitas::query()->orderBy('nama_fasilitas')->get(),
        ]);
    }

    // GET /admin/room/create — halaman form tambah room (versi halaman penuh).
    // Untuk mencoba manual: buka http://localhost:8000/admin/room/create
    public function create(): View
    {
        // Objek Room kosong dipakai supaya form yang sama bisa dipakai untuk tambah dan edit.
        return view('admin.room.form', ['room' => new Room, 'fasilitas' => $this->fasilitasAktif()]);
    }

    // POST /admin/room — menyimpan room baru.
    // Validasi dijalankan otomatis oleh SimpanRoomRequest sebelum method ini berjalan.
    public function store(SimpanRoomRequest $request): RedirectResponse
    {
        // validated() hanya mengembalikan kolom yang lolos aturan validasi.
        Room::query()->create($request->validated());

        return redirect()->route('admin.room.index')->with('sukses', 'Room berhasil ditambahkan.');
    }

    // GET /admin/room/{room}/edit — halaman form ubah room.
    // Route model binding: Room diambil otomatis berdasarkan id di URL.
    public function edit(Room $room): View
    {
        return view('admin.room.form', ['room' => $room, 'fasilitas' => $this->fasilitasAktif()]);
    }

    // PUT/PATCH /admin/room/{room} — menyimpan perubahan room.
    public function update(SimpanRoomRequest $request, Room $room): RedirectResponse
    {
        $room->update($request->validated());

        return redirect()->route('admin.room.index')->with('sukses', 'Room berhasil diperbarui.');
    }

    // DELETE /admin/room/{room} — menghapus room.
    // Untuk menguji penolakan hapus:
    //   php artisan test --filter=RoomTest
    public function destroy(Room $room): RedirectResponse
    {
        // Room yang sudah punya riwayat reservasi tidak boleh dihapus,
        // supaya laporan dan riwayat pelanggan tetap utuh.
        if ($room->detailReservasi()->exists()) {
            return back()->with('galat', 'Room tidak dapat dihapus karena sudah memiliki reservasi.');
        }

        $room->delete();

        return redirect()->route('admin.room.index')->with('sukses', 'Room berhasil dihapus.');
    }

    // Mengambil daftar fasilitas untuk mengisi dropdown pada form tambah/ubah.
    private function fasilitasAktif()
    {
        return Fasilitas::query()->orderBy('nama_fasilitas')->get();
    }
}
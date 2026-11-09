<?php

// File ini mengurus katalog room untuk pelanggan.
// Fungsi: menampilkan daftar fasilitas, detail satu room, halaman pilih room & jadwal,
//         dan mengirim status ketersediaan slot dalam bentuk JSON.
// Dipakai oleh: routes/web.php baris 37-40 (pelanggan.katalog, pelanggan.room.detail,
//               pelanggan.room.jadwal, pelanggan.ketersediaan).
// Mengambil data dari model: Fasilitas.php dan Room.php.
// Memakai service: KetersediaanService.php untuk menghitung slot terpakai dan slot kosong.
// View yang dipakai: pelanggan/katalog.blade.php, pelanggan/room-detail.blade.php, pelanggan/room-jadwal.blade.php.
//
// Untuk mencoba manual:
//   php artisan serve
// Lalu buka http://localhost:8000/rooms

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use App\Models\Room;
use App\Services\KetersediaanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KatalogController extends Controller
{
    // KetersediaanService disuntikkan otomatis oleh Laravel lewat constructor.
    public function __construct(private readonly KetersediaanService $ketersediaan) {}

    /**
     * Daftar fasilitas aktif beserta room yang bisa dipilih (fitur 7 dan 15).
     */
    // GET /rooms — halaman utama katalog, mendukung pencarian dan filter kategori.
    // Untuk mengujinya:
    //   php artisan test --filter=Katalog
    public function index(Request $request): View
    {
        // string()->trim()->value() memastikan input selalu berupa teks tanpa spasi di ujung.
        $cari = $request->string('cari')->trim()->value();
        $kategori = $request->string('kategori')->trim()->value();

        $daftarFasilitas = Fasilitas::query()
            ->where('status_fasilitas', 'aktif')
            // when() hanya menambahkan filter jika kondisinya benar, jadi query tetap ringkas.
            ->when($cari !== '', fn ($query) => $query->where('nama_fasilitas', 'like', "%{$cari}%"))
            ->when(ctype_digit($kategori), fn ($query) => $query->where('id_fasilitas', (int) $kategori))
            // with() memuat relasi room sekaligus, supaya tidak query berulang di dalam perulangan view.
            ->with(['room' => fn ($query) => $query->orderBy('nama_room')])
            ->orderBy('id_fasilitas')
            ->get();

        return view('pelanggan.katalog', [
            'daftarFasilitas' => $daftarFasilitas,
            'cari' => $cari,
            'kategori' => $kategori,
            // chipKategori dipakai untuk tombol filter di atas daftar.
            'chipKategori' => Fasilitas::query()->where('status_fasilitas', 'aktif')->orderBy('id_fasilitas')->get(),
        ]);
    }

    /**
     * Detail satu room beserta fasilitas induk dan status operasional room lain (fitur 8 dan 16).
     */
    // GET /rooms/{room} — data room dibuat oleh dataRoom().
    // Room $room diisi otomatis oleh Laravel dari id di URL (route model binding).
    public function detail(Request $request, Room $room): View
    {
        return view('pelanggan.room-detail', $this->dataRoom($request, $room));
    }

    /**
     * Halaman Pilih Room & Jadwal (fitur 9 dan 16).
     */
    // GET /rooms/{room}/jadwal — memakai data yang sama dengan detail(),
    // hanya view-nya berbeda karena halaman ini fokus ke pemilihan jam.
    public function jadwal(Request $request, Room $room): View
    {
        return view('pelanggan.room-jadwal', $this->dataRoom($request, $room));
    }

    /**
     * Cek ketersediaan interval bermain (fitur 17).
     */
    // POST /reservasi/ketersediaan — dipanggil lewat fetch() dari JavaScript di halaman room.
    // Balasannya JSON, dipakai untuk menandai slot yang bentrok tanpa memuat ulang halaman.
    public function ketersediaan(Request $request): JsonResponse
    {
        // validate() langsung menolak permintaan jika format jam atau tanggal tidak sesuai.
        $data = $request->validate([
            'id_room' => ['required', 'integer', 'exists:room,id_room'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'jam_mulai' => ['required', 'regex:/^\d{2}:\d{2}(:00)?$/'],
            'jam_selesai' => ['required', 'regex:/^\d{2}:\d{2}(:00)?$/'],
        ], attributes: [
            // attributes hanya mengganti nama kolom di pesan error supaya lebih mudah dibaca.
            'id_room' => 'room',
            'jam_mulai' => 'jam mulai',
            'jam_selesai' => 'jam selesai',
        ]);

        [$mulai, $selesai] = $this->normalisasiJam($data['jam_mulai'], $data['jam_selesai']);

        $room = Room::query()->findOrFail($data['id_room']);

        // Room nonaktif selalu dianggap bentrok supaya tidak bisa dipesan.
        $bentrok = $room->status_room === 'tidak_aktif'
            || $this->ketersediaan->bentrok($room, $data['tanggal'], $mulai, $selesai);

        return response()->json([
            'bentrok' => $bentrok,
            'jamAwal' => $this->ketersediaan->jamMulaiTerdekat($data['tanggal']),
            'status_slot' => $this->ketersediaan->statusSlot($room, $data['tanggal']),
            // Semua room aktif, supaya dropdown reservasi bisa menolak room yang penuh di tanggal ini.
            'slot_kosong' => $this->ketersediaan->slotKosongSemua(
                Room::query()->where('status_room', '<>', 'tidak_aktif')->pluck('id_room')->all(),
                $data['tanggal'],
            ),
            'pesan' => $bentrok
                ? 'Slot ini sudah dipesan atau beririsan dengan jadwal lain. Pilih jam lain.'
                : 'Slot tersedia.',
        ]);
    }

    /**
     * Validasi jam penuh 10.00–21.00, lalu kembalikan format "HH:MM".
     *
     * @return array{0: string, 1: string}
     */
    // Dipakai oleh ketersediaan() sebelum mengecek bentrok.
    private function normalisasiJam(string $jamMulai, string $jamSelesai): array
    {
        // substr(0, 5) membuang bagian detik, misal "10:00:00" menjadi "10:00".
        $mulai = substr($jamMulai, 0, 5);
        $selesai = substr($jamSelesai, 0, 5);

        $jamPenuh = str_ends_with($mulai, ':00') && str_ends_with($selesai, ':00');

        // Jam harus bulat, di dalam jam buka, dan jam selesai harus lebih besar.
        if (! $jamPenuh || $mulai < '10:00' || $selesai > '21:00' || $selesai <= $mulai) {
            throw ValidationException::withMessages([
                'jam_mulai' => 'Jam main harus jam penuh antara 10.00 dan 21.00, dengan jam selesai lebih besar dari jam mulai.',
            ]);
        }

        return [$mulai, $selesai];
    }

    /**
     * @return array{fasilitas: Fasilitas, roomTerpilih: Room, tanggal: string, statusSlot: array<string, string>, slotKosong: array<int, int>, jamAwal: int}
     */
    // Menyiapkan semua data yang dibutuhkan halaman room, dipakai oleh detail() dan jadwal().
    private function dataRoom(Request $request, Room $room): array
    {
        // Fasilitas induk diambil dari id_fasilitas milik room, sekaligus memuat daftar room-nya.
        $fasilitas = Fasilitas::query()
            ->where('status_fasilitas', 'aktif')
            ->with(['room' => fn ($query) => $query->orderBy('nama_room')])
            ->findOrFail($room->id_fasilitas);

        // Tanggal diambil dari query string, kalau kosong atau tidak valid dipakai tanggal hari ini.
        $tanggal = $this->tanggalDipilih($request);

        return [
            'fasilitas' => $fasilitas,
            'roomTerpilih' => $room,
            'tanggal' => $tanggal,
            'statusSlot' => $this->ketersediaan->statusSlot($room, $tanggal),
                 // Jam yang sudah lewat tetap tampil, tapi tidak bisa dipilih.
                 'jamAwal' => $this->ketersediaan->jamMulaiTerdekat($tanggal),
                 // Room penuh pada tanggal ini tidak bisa dipilih (0 slot kosong).
                 'slotKosong' => $this->ketersediaan->slotKosongSemua($fasilitas->room->pluck('id_room')->all(), $tanggal),
             ];
         }

    // Memastikan tanggal dari URL berbentuk Y-m-d dan tidak di masa lalu.
    // Kalau tidak lolos, otomatis kembali ke tanggal hari ini.
    private function tanggalDipilih(Request $request): string
    {
        $tanggal = $request->string('tanggal')->trim()->value();
        $valid = \DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal);

        return $valid !== false && $valid->format('Y-m-d') === $tanggal && $tanggal >= now()->toDateString()
            ? $tanggal
            : now()->toDateString();
    }
}

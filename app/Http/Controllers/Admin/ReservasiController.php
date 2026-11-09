<?php

// File ini mengatur reservasi dari sisi admin.
// Fungsi: daftar reservasi + pencarian/filter, detail reservasi, dan ubah status manual.
// Dipakai oleh: routes/web.php baris 64-66 (admin.reservasi.*).
// Mengambil data dari model: Reservasi.php, Fasilitas.php, dan Room.php.
// Aturan pembayaran dipakai lewat: PembayaranService.php.
// Mengubah tabel: `reservasi` (kolom status_reservasi).
//
// Untuk mencoba manual, jalankan server lalu login sebagai admin:
//   php artisan serve
// Lalu buka http://localhost:8000/admin/reservasi

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use App\Models\Reservasi;
use App\Models\Room;
use App\Services\PembayaranService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Kelola reservasi admin: cari kode booking / nama pelanggan, filter, ubah status
 * (fitur 35, 36, 37, 40). Perubahan status manual hanya ke 'dikonfirmasi' atau
 * 'dibatalkan' (ARCHITECTURE.md §3.2).
 */
class ReservasiController extends Controller
{
    private const STATUS = ['menunggu_pembayaran', 'dikonfirmasi', 'check_in', 'selesai', 'dibatalkan'];

    public function __construct(private readonly PembayaranService $pembayaran) {}

    // GET /admin/reservasi — daftar reservasi + pencarian dan filter bertingkat.
    // Untuk mengujinya:
    //   php artisan test --filter=Reservasi
    public function index(Request $request): View
    {
        $cari = $request->string('cari')->trim()->value();
            $status = $request->string('status')->trim()->value();
            $pembayaran = $request->string('pembayaran')->trim()->value();

            $daftarReservasi = Reservasi::query()
                ->with(['pengguna', 'detailReservasi.room.fasilitas', 'pembayaran'])
                // total_sukses = jumlah uang yang sudah masuk, dipakai untuk filter lunas/DP.
                ->withSum(['pembayaran as total_sukses' => fn ($query) => $query->where('status_pembayaran', 'sukses')], 'jumlah')
                // Pencarian dua arah: kode booking atau nama pelanggan.
                ->when($cari !== '', fn ($query) => $query->where(
                    fn ($sub) => $sub->where('kode_booking', 'like', "%{$cari}%")
                        ->orWhereHas('pengguna', fn ($pengguna) => $pengguna->where('nama', 'like', "%{$cari}%")),
                ))
                ->when(in_array($status, self::STATUS, true), fn ($query) => $query->where('status_reservasi', $status))
                // Belum lunas: uang masuk kurang dari total tagihan.
                ->when($pembayaran === 'belum', fn ($query) => $query->whereRaw(
                    'total_reservasi > coalesce((select sum(jumlah) from pembayaran where pembayaran.id_reservasi = reservasi.id_reservasi and status_pembayaran = ?), 0)',
                    ['sukses'],
                ))
                // Lunas: uang masuk minimal sebesar total tagihan.
                ->when($pembayaran === 'lunas', fn ($query) => $query->whereRaw(
                    'total_reservasi <= coalesce((select sum(jumlah) from pembayaran where pembayaran.id_reservasi = reservasi.id_reservasi and status_pembayaran = ?), 0)',
                    ['sukses'],
                ))
                // DP: sudah ada uang masuk tetapi belum menutup total tagihan.
                ->when($pembayaran === 'dp', fn ($query) => $query->whereRaw(
                    'coalesce((select sum(jumlah) from pembayaran where pembayaran.id_reservasi = reservasi.id_reservasi and status_pembayaran = ?), 0) between 0.0001 and total_reservasi - 0.0001',
                    ['sukses'],
                ))
                // Filter berdasarkan fasilitas (lewat room yang dipesan).
                ->when($request->integer('fasilitas'), fn ($query, int $idFasilitas) => $query->whereHas(
                    'detailReservasi.room',
                    fn ($room) => $room->where('id_fasilitas', $idFasilitas),
                ))
                // Filter berdasarkan room tertentu.
                ->when($request->integer('room'), fn ($query, int $idRoom) => $query->whereHas(
                    'detailReservasi',
                    fn ($detail) => $detail->where('id_room', $idRoom),
                ))
                // Filter berdasarkan tanggal bermain.
                ->when($request->date('tanggal'), fn ($query, $tanggal) => $query->whereHas(
                    'detailReservasi',
                    fn ($detail) => $detail->whereDate('tanggal', $tanggal),
                ))
                ->orderByDesc('tanggal_dibuat')
                ->orderByDesc('id_reservasi')
                                ->paginate(10)
                                ->withQueryString();

            return view('admin.reservasi.index', [
                'daftarReservasi' => $daftarReservasi,
                // Data untuk dropdown filter.
                'fasilitas' => Fasilitas::query()->orderBy('nama_fasilitas')->get(),
                'semuaRoom' => Room::query()->orderBy('nama_room')->get(),
                'cari' => $cari,
                'status' => $status,
                'pembayaran' => $pembayaran,
            ]);
        }

    // GET /admin/reservasi/{reservasi} — halaman detail reservasi.
    public function detail(Reservasi $reservasi): View
    {
        $reservasi->load(['pengguna', 'detailReservasi.room.fasilitas', 'pembayaran']);

        // ringkasan() mengembalikan total tagihan, sudah dibayar, dan sisa tagihan.
        return view('admin.reservasi.detail', array_merge(
            ['reservasi' => $reservasi],
            $this->pembayaran->ringkasan($reservasi),
        ));
    }

    // PATCH /admin/reservasi/{reservasi}/status — ubah status reservasi secara manual.
    public function ubahStatus(Request $request, Reservasi $reservasi): RedirectResponse
    {
        // Hanya dua status ini yang boleh diubah manual oleh admin.
        $data = $request->validate([
            'status_reservasi' => ['required', 'in:dikonfirmasi,dibatalkan'],
        ], [
            'status_reservasi.in' => 'Status hanya dapat diubah ke dikonfirmasi atau dibatalkan.',
        ], [
            'status_reservasi' => 'status reservasi',
        ]);

        // Konfirmasi hanya boleh kalau sudah ada uang masuk sama sekali.
        if ($data['status_reservasi'] === 'dikonfirmasi' && $this->pembayaran->totalDibayar($reservasi) <= 0) {
            throw ValidationException::withMessages([
                'status_reservasi' => 'Reservasi hanya dapat dikonfirmasi setelah ada pembayaran sukses (DP minimal 50%).',
            ]);
        }

        $reservasi->update(['status_reservasi' => $data['status_reservasi']]);

        return back()->with('sukses', "Status reservasi {$reservasi->kode_booking} diubah menjadi {$data['status_reservasi']}.");
    }
}

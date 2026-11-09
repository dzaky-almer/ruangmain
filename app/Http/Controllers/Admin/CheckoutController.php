<?php

// File ini mengurus check-out dari sisi admin (sesi bermain selesai).
// Fungsi: menampilkan room yang sedang dipakai, mencatat keterlambatan,
//         lalu menutup sesi room tersebut.
// Dipakai oleh: routes/web.php baris 70-71 (admin.checkout.*).
// Mengambil data lewat: CheckinService.php.
// Mengubah tabel: `detail_reservasi` (waktu_checkout) dan `room` (status_room).
//
// Untuk mencoba manual, jalankan server lalu login sebagai admin:
//   php artisan serve
// Lalu buka http://localhost:8000/admin/checkout

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailReservasi;
use App\Services\CheckinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Check-out sisi admin: daftar room digunakan (fitur 61) dan check-out per room (62–65).
 */
class CheckoutController extends Controller
{
    // CheckinService dipakai bersama dengan check-in karena aturannya satu paket.
    public function __construct(private readonly CheckinService $checkin) {}

    // GET /admin/checkout — daftar room yang sedang dipakai.
    public function index(): View
    {
        $detail = $this->checkin->roomDigunakan();

        return view('admin.checkout.index', [
            'detail' => $detail,
            // Untuk setiap room: jumlah menit terlambat, dipakai untuk menandai baris.
            'terlambat' => $detail->mapWithKeys(fn (DetailReservasi $item): array => [$item->id_detail => $this->checkin->menitTerlambat($item)]),
        ]);
    }

    /** Fitur 62–65. */
    // POST /admin/checkout — menutup sesi satu room.
    // Untuk menguji prosesnya:
    //   php artisan test --filter=Checkout
    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
                    'kode_booking' => ['required', 'string'],
                    'id_detail' => ['required', 'integer', 'exists:detail_reservasi,id_detail'],
                ], [
                    'id_detail.required' => 'Pilih room yang akan di-check-out.',
                    'id_detail.exists' => 'Room yang dipilih tidak ditemukan.',
                ], [
                    'kode_booking' => 'kode booking',
                    'id_detail' => 'room',
                ]);

                $detail = DetailReservasi::query()->findOrFail($data['id_detail']);

                // Pengaman tambahan: kode booking harus cocok dengan room yang dipilih.
                // Tanpa ini, admin bisa menutup room milik reservasi lain.
                if ($detail->reservasi?->kode_booking !== strtoupper(trim($data['kode_booking']))) {
                    return back()->with('galat', 'Room yang dipilih bukan bagian dari reservasi tersebut.');
                }

                // Menutup sesi: waktu_checkout diisi dan status room dikembalikan ke tersedia.
                $this->checkin->checkOut($detail);

        return redirect()
            ->route('admin.checkout.index')
            ->with('sukses', "Check-out {$detail->room->nama_room} ({$detail->reservasi->kode_booking}) dicatat. Tidak ada tambahan tagihan.");
    }
}
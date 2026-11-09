<?php

// File ini mengurus check-in dari sisi admin (pelanggan datang dan mulai bermain).
// Fungsi: mencari reservasi berdasarkan kode booking, memeriksa kelayakan check-in,
//         lalu mencatat check-in per room.
// Dipakai oleh: routes/web.php baris 67-69 (admin.checkin.*).
// Mengambil data lewat: CheckinService.php dan PembayaranService.php.
// Mengubah tabel: `detail_reservasi` (waktu_checkin) dan `room` (status_room).
//
// Untuk mencoba manual, jalankan server lalu login sebagai admin:
//   php artisan serve
// Lalu buka http://localhost:8000/admin/checkin

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailReservasi;
use App\Services\CheckinService;
use App\Services\PembayaranService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Check-in sisi admin: cari booking / scan QR (fitur 55–56), verifikasi (57),
 * proses check-in per room (58–60).
 */
class CheckinController extends Controller
{
    // Dua service disuntikkan otomatis oleh Laravel.
    // CheckinService mengurus aturan check-in, PembayaranService mengurus status bayar.
    public function __construct(
        private readonly CheckinService $checkin,
        private readonly PembayaranService $pembayaran,
    ) {}

    // GET /admin/checkin — halaman pencarian kode booking.
    // Kode booking bisa dikirim lewat query string ?kode_booking=RM-XXXX.
    public function index(Request $request): View
    {
        return view('admin.checkin.index', $this->data($request->string('kode_booking')->value()));
    }

    /** Fitur 57: detail + verifikasi kelayakan check-in. */
    // GET /admin/checkin/{kode_booking} — halaman yang sama, tetapi kode dari URL.
    public function detail(string $kodeBooking): View
    {
        return view('admin.checkin.index', $this->data($kodeBooking));
    }

    /**
     * Menyiapkan semua data yang dibutuhkan halaman check-in.
     * Kalau kode booking tidak ditemukan, hasilnya tetap aman untuk view (reservasi null).
     *
     * @return array<string, mixed>
     */
    private function data(string $kodeBooking): array
    {
        // Kode booking selalu disimpan huruf besar, jadi input dirapikan dulu.
        $kode = strtoupper(trim($kodeBooking));
        $reservasi = $this->checkin->cari($kode);

        // Belum ada kode atau kode tidak dikenal: kirim struktur kosong.
        if ($reservasi === null) {
            return ['kode' => $kode, 'reservasi' => null, 'ringkasan' => null, 'alasan' => [], 'terlambat' => []];
        }

        return [
            'kode' => $kode,
            'reservasi' => $reservasi,
            // Ringkasan pembayaran: total tagihan, sudah dibayar, sisa.
            'ringkasan' => $this->pembayaran->ringkasan($reservasi),
            // Untuk setiap room: alasan kalau belum boleh check-in (kosong = boleh).
            'alasan' => $reservasi->detailReservasi
                ->mapWithKeys(fn (DetailReservasi $detail): array => [$detail->id_detail => $this->checkin->alasanTolakCheckin($detail)]),
            // Untuk setiap room: berapa menit pelanggan terlambat (0 = tepat waktu).
            'terlambat' => $reservasi->detailReservasi
                ->mapWithKeys(fn (DetailReservasi $detail): array => [$detail->id_detail => $this->checkin->menitTerlambat($detail)]),
        ];
    }

    /** Fitur 58–60. */
    // POST /admin/checkin — mencatat check-in satu room.
    // Untuk menguji prosesnya:
    //   php artisan test --filter=Checkin
    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode_booking' => ['required', 'string'],
            'id_detail' => ['required', 'integer', 'exists:detail_reservasi,id_detail'],
        ], [
            'id_detail.required' => 'Pilih room yang akan di-check-in.',
            'id_detail.exists' => 'Room yang dipilih tidak ditemukan.',
        ], [
            'kode_booking' => 'kode booking',
            'id_detail' => 'room',
        ]);

        $reservasi = $this->checkin->cari($data['kode_booking']);
        $detail = DetailReservasi::query()->findOrFail($data['id_detail']);

        // Pengaman: room yang dipilih harus benar-benar bagian dari reservasi tersebut.
        if ($reservasi === null || $detail->id_reservasi !== $reservasi->id_reservasi) {
            return back()->with('galat', 'Room yang dipilih bukan bagian dari reservasi tersebut.');
        }

        // Aturan check-in (lunas, jadwal, status) diperiksa di dalam service.
        $this->checkin->checkIn($detail);

        return redirect()
            ->route('admin.checkin.detail', $reservasi->kode_booking)
            ->with('sukses', "Check-in {$detail->room->nama_room} dicatat pada ".now()->translatedFormat('d M Y, H.i').'.');
    }
}
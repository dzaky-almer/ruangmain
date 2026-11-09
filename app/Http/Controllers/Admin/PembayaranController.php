<?php

// File ini mengatur pembayaran dari sisi admin.
// Fungsi: daftar pembayaran + filter, detail pembayaran, mencatat pembayaran cash,
//         dan memverifikasi penerimaan cash.
// Dipakai oleh: routes/web.php baris 72-75 (admin.pembayaran.*).
// Mengambil data dari model: Pembayaran.php dan Reservasi.php.
// Aturan perubahan status ada di: PembayaranService.php.
// Mengubah tabel: `pembayaran` dan `reservasi` (status_reservasi).
//
// Untuk mencoba manual, jalankan server lalu login sebagai admin:
//   php artisan serve
// Lalu buka http://localhost:8000/admin/pembayaran

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Reservasi;
use App\Services\PembayaranService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pembayaran sisi admin: daftar + filter (fitur 47–52), catat cash (fitur 53),
 * verifikasi cash (fitur 54).
 */
class PembayaranController extends Controller
{
    // Daftar nilai yang sah, dipakai untuk memfilter input dari URL.
    private const STATUS_PEMBAYARAN = ['pending', 'sukses', 'gagal', 'dibatalkan'];

    private const STATUS_RESERVASI = ['menunggu_pembayaran', 'dikonfirmasi', 'check_in', 'selesai', 'dibatalkan'];

    // PembayaranService disuntikkan otomatis oleh Laravel.
    public function __construct(private readonly PembayaranService $pembayaran) {}

    // GET /admin/pembayaran — daftar pembayaran + pencarian + filter.
    // Untuk mengujinya:
    //   php artisan test --filter=Pembayaran
    public function index(Request $request): View
    {
        // Semua nilai filter diambil dari query string, lalu dirapikan.
        $cari = $request->string('cari')->trim()->value();
        $metode = $request->string('metode')->trim()->value();
        $jenis = $request->string('jenis')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $daftarPembayaran = Pembayaran::query()
            // Relasi dimuat sekaligus agar view tidak memicu query berulang.
            ->with(['reservasi.pengguna', 'reservasi.pembayaran', 'diverifikasiOleh'])
            // Pencarian bisa lewat id transaksi, id pembayaran, atau kode booking.
            ->when($cari !== '', fn ($query) => $query->where(
                fn ($sub) => $sub->where('id_transaksi_gateway', 'like', "%{$cari}%")
                    ->orWhere('id_pembayaran', 'like', "%{$cari}%")
                    ->orWhereHas('reservasi', fn ($reservasi) => $reservasi->where('kode_booking', 'like', "%{$cari}%")),
            ))
            ->when(in_array($metode, ['qris', 'cash'], true), fn ($query) => $query->where('metode_pembayaran', $metode))
            ->when(in_array($jenis, ['dp', 'pelunasan'], true), fn ($query) => $query->where('jenis_pembayaran', $jenis))
            ->when(in_array($status, self::STATUS_PEMBAYARAN, true), fn ($query) => $query->where('status_pembayaran', $status))
            // Filter tanggal memakai whereDate supaya jam tidak ikut dibandingkan.
            ->when($request->date('dari'), fn ($query, $tanggal) => $query->whereDate('waktu_bayar', '>=', $tanggal))
            ->when($request->date('sampai'), fn ($query, $tanggal) => $query->whereDate('waktu_bayar', '<=', $tanggal))
            ->orderByDesc('id_pembayaran')
            ->get();

        return view('admin.pembayaran.index', [
            'daftarPembayaran' => $daftarPembayaran,
            'cari' => $cari,
            'metode' => $metode,
            'jenis' => $jenis,
            'status' => $status,
        ]);
    }

    // GET /admin/pembayaran/{pembayaran} — halaman detail satu pembayaran.
    public function detail(Pembayaran $pembayaran): View
    {
        $pembayaran->load(['reservasi.pengguna', 'reservasi.pembayaran', 'diverifikasiOleh']);

        return view('admin.pembayaran.detail', [
            'pembayaran' => $pembayaran,
            'reservasi' => $pembayaran->reservasi,
        ]);
    }

    /** Fitur 53: admin mencatat pembayaran cash pelanggan. Dengan catatan → langsung terverifikasi. */
    // POST /admin/pembayaran/cash — mencatat pembayaran cash.
    public function catatCash(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_reservasi' => ['required', 'integer', 'exists:reservasi,id_reservasi'],
            'jenis_pembayaran' => ['required', 'in:dp,pelunasan'],
                'catatan_verifikasi' => ['nullable', 'string', 'min:3', 'max:255'],
            ], [], [
                'id_reservasi' => 'reservasi',
                'jenis_pembayaran' => 'jenis pembayaran',
                'catatan_verifikasi' => 'catatan verifikasi',
            ]);

            $reservasi = Reservasi::query()->findOrFail($data['id_reservasi']);

            // Baris pembayaran dibuat dengan status pending lewat service.
            $pembayaran = $this->pembayaran->buat($reservasi, $data['jenis_pembayaran'], 'cash');

            // Kalau admin mengisi catatan, uang dianggap sudah diterima → langsung sukses.
            if (! empty($data['catatan_verifikasi'])) {
                $this->pembayaran->tandaiSukses($pembayaran, $request->user()->id_pengguna);

                return redirect()
                    ->route('admin.reservasi.detail', $reservasi)
                    ->with('sukses', "Pembayaran cash #{$pembayaran->id_pembayaran} diverifikasi. Catatan: {$data['catatan_verifikasi']}");
            }

            // Tanpa catatan: pembayaran tetap pending, menunggu verifikasi terpisah.
            return redirect()
                ->route('admin.pembayaran.detail', $pembayaran)
                ->with('sukses', "Pembayaran cash #{$pembayaran->id_pembayaran} dicatat untuk {$reservasi->kode_booking}. Verifikasi setelah uang diterima.");
        }

    /** Fitur 54: verifikasi penerimaan cash → sukses, reservasi dikonfirmasi. */
    // POST /admin/pembayaran/{pembayaran}/verifikasi — memverifikasi pembayaran cash.
    public function verifikasi(Request $request, Pembayaran $pembayaran): RedirectResponse
    {
        $data = $request->validate([
            'catatan_verifikasi' => ['required', 'string', 'min:3', 'max:255'],
            'konfirmasi_terima' => ['accepted'],
        ], [
            'catatan_verifikasi.required' => 'Catatan verifikasi wajib diisi sebagai jejak audit.',
            'konfirmasi_terima.accepted' => 'Centang pernyataan bahwa cash benar-benar diterima.',
        ], [
            'catatan_verifikasi' => 'catatan verifikasi',
            'konfirmasi_terima' => 'pernyataan penerimaan cash',
        ]);

        // Pembayaran QRIS tidak boleh diverifikasi manual; statusnya hanya dari gateway.
        if ($pembayaran->metode_pembayaran !== 'cash') {
            throw ValidationException::withMessages([
                'konfirmasi_terima' => 'Pembayaran QRIS hanya berubah status melalui konfirmasi gateway, bukan verifikasi manual.',
            ]);
        }

        // Pembayaran yang sudah selesai tidak boleh diubah lagi.
        if ($pembayaran->status_pembayaran !== 'pending') {
            throw ValidationException::withMessages([
                'konfirmasi_terima' => "Pembayaran berstatus {$pembayaran->status_pembayaran} dan tidak dapat diubah lagi.",
            ]);
        }

        // Status diubah lewat service, yang sekaligus mengurus status reservasi.
        $this->pembayaran->tandaiSukses($pembayaran, $request->user()->id_pengguna);

        return back()->with('sukses', "Pembayaran cash #{$pembayaran->id_pembayaran} diverifikasi. Catatan: {$data['catatan_verifikasi']}");
    }
}

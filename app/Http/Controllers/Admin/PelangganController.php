<?php

// File ini mengatur data pelanggan dari sisi admin.
// Fungsi: daftar pelanggan, pencarian, detail riwayat, dan aktif/nonaktifkan akun.
// Dipakai oleh: routes/web.php baris 76-78 (admin.pelanggan.*).
// Mengambil data dari model: Pengguna.php, Reservasi.php, dan Pembayaran.php.
// Mengubah tabel: `pengguna` (kolom status_akun).
//
// Untuk mencoba manual, jalankan server lalu login sebagai admin:
//   php artisan serve
// Lalu buka http://localhost:8000/admin/pelanggan

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Manajemen pelanggan sisi admin: daftar + pencarian (fitur 66, 68), detail dengan
 * riwayat reservasi dan pembayaran (fitur 67, 69, 70), aktif/nonaktif akun (71, 72).
 *
 * Akun admin tidak pernah muncul di sini — hanya peran `pelanggan`.
 * Menonaktifkan akun tidak menghapus data (BR-17: riwayat tetap tersimpan).
 */
class PelangganController extends Controller
{
    // GET /admin/pelanggan — daftar pelanggan + pencarian + filter status akun.
    // Untuk mengujinya:
    //   php artisan test --filter=Pelanggan
    public function index(Request $request): View
    {
        $cari = $request->string('cari')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $daftarPelanggan = Pengguna::query()
            // Hanya peran pelanggan; akun admin tidak ditampilkan.
            ->where('peran', Pengguna::PERAN_PELANGGAN)
            // Jumlah reservasi per pelanggan, dipakai di kolom tabel.
            ->withCount('reservasi')
            // ponytail: tabel pengguna demo tak punya created_at; tanggal reservasi
            // paling awal dipakai sebagai proksi "terdaftar". Ganti ke created_at bila kolom ditambah.
            ->withMin('reservasi as reservasi_min_tanggal_dibuat', 'tanggal_dibuat')
            // Pencarian ke tiga kolom sekaligus: nama, email, nomor HP.
            ->when($cari !== '', fn ($query) => $query->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('email', 'like', "%{$cari}%")
                    ->orWhere('no_hp', 'like', "%{$cari}%"),
            ))
            ->when(
                in_array($status, [Pengguna::STATUS_AKTIF, Pengguna::STATUS_NONAKTIF], true),
                fn ($query) => $query->where('status_akun', $status),
            )
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return view('admin.pelanggan.index', [
            'daftarPelanggan' => $daftarPelanggan,
            'cari' => $cari,
            'status' => $status,
        ]);
    }

    // GET /admin/pelanggan/{pelanggan} — halaman detail satu pelanggan.
    public function detail(Pengguna $pelanggan): View
    {
        // Kalau yang dibuka ternyata akun admin, halaman dianggap tidak ada (404).
        abort_unless($pelanggan->peran === Pengguna::PERAN_PELANGGAN, 404);

        return view('admin.pelanggan.detail', [
            'pelanggan' => $pelanggan,
            // ponytail: proksi "terdaftar" = tanggal reservasi paling awal (tak ada created_at).
            'terdaftar' => $pelanggan->reservasi()->min('tanggal_dibuat'),
            // Riwayat reservasi lengkap dengan room dan pembayaran, terbaru lebih dulu.
            'daftarReservasi' => $pelanggan->reservasi()
                ->with(['detailReservasi.room', 'pembayaran'])
                ->orderByDesc('tanggal_dibuat')
                ->orderByDesc('id_reservasi')
                ->get(),
            // Riwayat pembayaran pelanggan ini, diambil lewat relasi ke reservasi.
            'daftarPembayaran' => Pembayaran::query()
                ->with('reservasi')
                ->whereHas('reservasi', fn ($query) => $query->where('id_pengguna', $pelanggan->id_pengguna))
                ->orderByDesc('id_pembayaran')
                ->get(),
        ]);
    }

    /** Fitur 71–72: aktifkan atau nonaktifkan akun pelanggan. */
    // PATCH /admin/pelanggan/{pelanggan}/status — mengubah status akun.
    public function ubahStatus(Request $request, Pengguna $pelanggan): RedirectResponse
    {
        abort_unless($pelanggan->peran === Pengguna::PERAN_PELANGGAN, 404);

        $data = $request->validate([
            'status_akun' => ['required', 'in:aktif,nonaktif'],
        ], [], ['status_akun' => 'status akun']);

        // Status yang sama tidak perlu diproses ulang.
        if ($data['status_akun'] === $pelanggan->status_akun) {
            throw ValidationException::withMessages([
                'status_akun' => "Akun {$pelanggan->nama} sudah berstatus {$pelanggan->status_akun}.",
            ]);
        }

        // `status_akun` sengaja tidak ada di $fillable model, jadi diset langsung.
        $pelanggan->status_akun = $data['status_akun'];
        $pelanggan->save();

        // Akun nonaktif tidak bisa login, tetapi seluruh riwayat tetap tersimpan.
        $pesan = $data['status_akun'] === Pengguna::STATUS_AKTIF
            ? "Akun {$pelanggan->nama} diaktifkan kembali dan dapat login."
            : "Akun {$pelanggan->nama} dinonaktifkan. Riwayat reservasi dan pembayarannya tetap tersimpan.";

        return back()->with('sukses', $pesan);
    }
}

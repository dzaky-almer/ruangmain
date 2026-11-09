<?php

// File ini mengurus pembayaran dari sisi pelanggan.
// Fungsi: menampilkan halaman bayar, membuat baris pembayaran baru,
//         menampilkan status QRIS, membuka ulang tautan Snap, menjalankan simulator
//         pembayaran, dan menampilkan riwayat pembayaran pelanggan.
// Dipakai oleh: routes/web.php baris 45-50 (pelanggan.pembayaran.form, .simpan,
//               pelanggan.pembayaran.index, .status, .lanjut, .simulasi).
// Mengambil data dari model: Pembayaran.php dan Reservasi.php.
// Memakai service: PembayaranService.php (buat pembayaran, hitung ringkasan, terima callback).
// Payment gateway diambil lewat app(PaymentGateway::class); binding-nya ada di
// AppServiceProvider.php, jadi isinya bisa MockQrisGateway atau MidtransGateway.
// View yang dipakai: pelanggan/pembayaran/bayar.blade.php, status.blade.php, index.blade.php.
//
// Untuk mencoba manual:
//   php artisan serve
// Lalu login sebagai pelanggan dan buka halaman detail reservasi > Bayar.

namespace App\Http\Controllers\Pelanggan;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Reservasi;
use App\Services\MidtransGateway;
use App\Services\PembayaranService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pembayaran sisi pelanggan: halaman bayar (fitur 42, 45, 46), halaman status
 * QRIS + hasil (fitur 47), dan riwayat pembayaran (fitur 48).
 */
class PembayaranController extends Controller
{
    // PembayaranService disuntikkan otomatis oleh Laravel.
    public function __construct(private readonly PembayaranService $pembayaran) {}

    /** Halaman bayar: pilih jenis (DP/pelunasan) dan metode (QRIS/cash). */
    // GET /reservasi/{reservasi}/bayar
    // Untuk mengujinya:
    //   php artisan test --filter=Pembayaran
    public function form(Request $request, Reservasi $reservasi): View|RedirectResponse
    {
        // Memastikan reservasi ini memang milik pelanggan yang login.
        $this->milikPelanggan($request, $reservasi);

        $opsi = $this->pembayaran->opsi($reservasi);

        // Tidak ada tagihan tersisa berarti reservasi sudah lunas.
        if ($opsi === []) {
            return redirect()
                ->route('pelanggan.reservasi.detail', $reservasi)
                ->with('sukses', "Reservasi {$reservasi->kode_booking} sudah lunas.");
        }

        // Reservasi yang dibatalkan atau sudah selesai tidak boleh dibayar.
        if (! in_array($reservasi->status_reservasi, ['menunggu_pembayaran', 'dikonfirmasi'], true)) {
            return redirect()
                ->route('pelanggan.reservasi.detail', $reservasi)
                ->with('galat', "Reservasi {$reservasi->kode_booking} berstatus {$reservasi->status_reservasi} sehingga tidak dapat dibayar.");
        }

        $pending = $this->pembayaran->pendingAktif($reservasi);

        // Sudah ada pembayaran yang menunggu: lanjutkan yang itu saja,
        // supaya tidak ada dua pembayaran terbuka untuk satu tagihan.
        if ($pending !== null) {
            return redirect()->route('pelanggan.pembayaran.status', $pending);
        }

        return view('pelanggan.pembayaran.bayar', [
            'reservasi' => $reservasi->load('detailReservasi.room.fasilitas'),
            'opsi' => $opsi,
            'ringkasan' => $this->pembayaran->ringkasan($reservasi),
            // Secara bawaan pilihan pertama yang ditandai terpilih.
            'jenisTerpilih' => $opsi[0]['jenis'],
        ]);
    }

    /** Buat baris pembayaran pending, lalu arahkan ke Snap (QRIS) atau halaman status. */
    // POST /reservasi/{reservasi}/bayar
    public function simpan(Request $request, Reservasi $reservasi): RedirectResponse
    {
        $this->milikPelanggan($request, $reservasi);

        // Nilai jenis dan metode dibatasi supaya tidak bisa diisi sembarangan dari form.
        $data = $request->validate([
            'jenis_pembayaran' => ['required', 'in:dp,pelunasan'],
            'metode_pembayaran' => ['required', 'in:qris,cash'],
        ], [], [
            // Argumen ketiga mengganti nama kolom di pesan error.
            'jenis_pembayaran' => 'jenis pembayaran',
            'metode_pembayaran' => 'metode pembayaran',
        ]);

        // PembayaranService yang menentukan nominal dan kode transaksi.
        $pembayaran = $this->pembayaran->buat($reservasi, $data['jenis_pembayaran'], $data['metode_pembayaran']);

        // Gateway yang aktif diambil dari container, bukan dibuat langsung,
        // supaya mudah diganti mock saat testing.
        $gateway = app(PaymentGateway::class);

        // QRIS hanya bisa diarahkan ke Snap kalau gateway aslinya Midtrans.
        if ($data['metode_pembayaran'] === 'qris' && $gateway instanceof MidtransGateway) {
            $tautan = $gateway->tautanBayar($pembayaran);

            if ($tautan !== null) {
                // redirect()->away() melempar pelanggan ke halaman Midtrans.
                return redirect()->away($tautan);
            }
        }

        return redirect()
            ->route('pelanggan.pembayaran.status', $pembayaran)
            ->with('sukses', "Pembayaran #{$pembayaran->id_pembayaran} ({$pembayaran->jenis_pembayaran}) dibuat dan menunggu penyelesaian.");
    }

    /** Halaman status QRIS (polling) sekaligus halaman hasil (fitur 47). */
    // GET /pembayaran/{pembayaran}
    public function status(Request $request, Pembayaran $pembayaran): View
    {
        // Pembayaran milik pelanggan lain dibalas 404.
        $this->milikPelanggan($request, $pembayaran->reservasi);

        // diverifikasiOleh dipakai untuk menampilkan admin yang memverifikasi pembayaran cash.
        $pembayaran->load('reservasi.detailReservasi.room.fasilitas', 'diverifikasiOleh');
        $reservasi = $pembayaran->reservasi;

        return view('pelanggan.pembayaran.status', [
            'pembayaran' => $pembayaran,
            'reservasi' => $reservasi,
            'ringkasan' => $this->pembayaran->ringkasan($reservasi),
            // Pembayaran gagal dan batas waktu sudah lewat berarti benar-benar kedaluwarsa.
            'kedaluwarsa' => $pembayaran->status_pembayaran === 'gagal'
                && $reservasi->batas_pembayaran?->isPast() === true,
            'midtransAktif' => app(PaymentGateway::class) instanceof MidtransGateway,
            // Tanda tangan ini dipakai tombol simulator supaya callback dianggap sah.
            'tandaTanganSimulasi' => $pembayaran->status_pembayaran === 'pending'
                ? app(PaymentGateway::class)->tandaTanganSimulasi((string) $pembayaran->id_transaksi_gateway, 'sukses')
                : null,
        ]);
    }

    /** Buka ulang halaman pembayaran Snap untuk pembayaran QRIS yang masih pending. */
    // GET /pembayaran/{pembayaran}/lanjut
    public function lanjut(Request $request, Pembayaran $pembayaran): RedirectResponse
    {
        $this->milikPelanggan($request, $pembayaran->reservasi);

        $gateway = app(PaymentGateway::class);

        // Hanya pembayaran QRIS yang masih pending yang bisa dibuka ulang.
        if ($pembayaran->status_pembayaran === 'pending'
            && $pembayaran->metode_pembayaran === 'qris'
            && $gateway instanceof MidtransGateway) {
            $tautan = $gateway->tautanBayar($pembayaran);

            if ($tautan !== null) {
                return redirect()->away($tautan);
            }
        }

        return redirect()->route('pelanggan.pembayaran.status', $pembayaran)
            ->with('galat', 'Tautan pembayaran gagal dibuat. Coba lagi beberapa saat lagi.');
    }

    /** Simulator: memicu callback gateway untuk pembayaran pending ini. */
    // POST /pembayaran/{pembayaran}/simulasi — hanya hidup di lingkungan local dan testing.
    public function simulasi(Request $request, Pembayaran $pembayaran): RedirectResponse
    {
        $this->milikPelanggan($request, $pembayaran->reservasi);

        // Di server produksi alamat ini tidak boleh ada.
        if (! app()->environment('local', 'testing')) {
            abort(404);
        }

        $data = $request->validate([
            'status_simulasi' => ['required', 'in:sukses,gagal'],
        ], [], ['status_simulasi' => 'status simulasi']);

        $gateway = app(PaymentGateway::class);
        $idTransaksi = (string) $pembayaran->id_transaksi_gateway;

        // Callback diproses lewat service yang sama dengan webhook asli,
        // jadi jalur pengujian dan jalur nyata tidak berbeda.
        $this->pembayaran->terimaCallback([
            'id_transaksi' => $idTransaksi,
            'status' => $data['status_simulasi'],
            'tanda_tangan' => (string) $gateway->tandaTanganSimulasi($idTransaksi, $data['status_simulasi']),
        ]);

        return redirect()
            ->route('pelanggan.pembayaran.status', $pembayaran)
            ->with('sukses', 'Simulator mengirim status '.$data['status_simulasi'].'.');
    }

    /** Riwayat pembayaran milik pelanggan yang login (fitur 48). */
    // GET /pembayaran
    public function index(Request $request): View
    {
        $cari = $request->string('cari')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $daftarPembayaran = Pembayaran::query()
            // whereHas menyaring lewat relasi: hanya pembayaran milik reservasi pengguna ini.
            ->whereHas('reservasi', fn ($query) => $query->where('id_pengguna', $request->user()->id_pengguna))
            ->when($cari !== '', fn ($query) => $query->whereHas(
                'reservasi',
                fn ($reservasi) => $reservasi->where('kode_booking', 'like', "%{$cari}%"),
            ))
            ->when(
                in_array($status, ['pending', 'sukses', 'gagal', 'dibatalkan'], true),
                fn ($query) => $query->where('status_pembayaran', $status),
            )
            ->with('reservasi')
            ->orderByDesc('id_pembayaran')
            ->get();

        return view('pelanggan.pembayaran.index', [
            'daftarPembayaran' => $daftarPembayaran,
            'cari' => $cari,
            'status' => $status,
        ]);
    }

    // Pemeriksa kepemilikan data. Dipakai oleh semua method di atas
    // supaya pelanggan tidak bisa membuka data pelanggan lain hanya dengan menebak URL.
    private function milikPelanggan(Request $request, Reservasi $reservasi): void
    {
        abort_if($reservasi->id_pengguna !== $request->user()->id_pengguna, 404);
    }
}

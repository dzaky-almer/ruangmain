<?php

// File ini menerima notifikasi pembayaran dari gateway QRIS (Midtrans).
// Fungsi: menjadi pintu masuk webhook, lalu meneruskan isinya ke PembayaranService.
// Tidak memakai login/sesi, jadi bisa dipanggil langsung oleh server Midtrans.
// Dipakai oleh: route `pembayaran.callback` di routes/web.php (POST /pembayaran/callback).
// Alur: Midtrans -> file ini -> PembayaranService::terimaCallback() -> tabel `pembayaran`.
//
// Agar server lokal bisa dihubungi Midtrans, buka terminal lain lalu jalankan:
//   ngrok http 8000
// Lalu isi URL ngrok + /pembayaran/callback pada Payment Notification URL di dashboard Midtrans.

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Services\MidtransGateway;
use App\Services\PembayaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Webhook gateway QRIS (fitur 47). Tanpa sesi/login; verifikasi tanda tangan
 * dilakukan di PembayaranService sebelum status apa pun diubah (SECURITY.md §5).
 *
 * Notifikasi Midtrans dikirim dalam bentuk aslinya (order_id, transaction_status,
 * signature_key), jadi diterjemahkan dulu ke payload internal. Terjemahan gagal
 * berarti notifikasi tidak dapat dipercaya ketika Midtrans tidak aktif.
 */
class PembayaranCallbackController extends Controller
{
    // Method ini dijalankan setiap ada notifikasi masuk.
    // Contoh menguji manual dengan data simulasi:
    //   php artisan test --filter=PembayaranCallback
    public function __invoke(Request $request, PembayaranService $pembayaran): JsonResponse
    {
        // Seluruh isi body request diambil apa adanya.
        $payload = $request->all();
        // Gateway dipilih otomatis: Midtrans saat kredensial diisi, mock saat pengujian.
        $gateway = app(PaymentGateway::class);

        // Saat Midtrans aktif, hanya notifikasi asli (bersignature_key sha512) yang diterjemahkan.
        // Payload tanpa signature_key tidak boleh jatuh ke jalur HMAC simulator.
        if ($gateway instanceof MidtransGateway) {
            // Diterjemahkan ke format internal aplikasi (id_transaksi/status/tanda_tangan).
            $payload = $gateway->terjemahkan($payload);

            // Terjemahan gagal = notifikasi tidak sah, dijawab 200 supaya tidak dikirim ulang.
            if ($payload === null) {
                return response()->json(['diterima' => true, 'diproses' => false]);
            }
        }

        try {
            // Pemeriksaan tanda tangan + perubahan status ada di service, bukan di sini.
            $hasil = $pembayaran->terimaCallback($payload);
        } catch (ValidationException) {
            // Callback cacat (status/tanda tangan tidak sah) tetap dijawab 200
            // agar gateway tidak mengulang kiriman yang sama.
            $hasil = null;
        }

        // Callback ganda / tidak dikenal tetap dijawab 200 agar gateway tidak mengulang.
        return response()->json([
            'diterima' => true,
            'diproses' => $hasil !== null,
        ]);
    }
}

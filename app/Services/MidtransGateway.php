<?php

// Dipakai oleh: AppServiceProvider (dipakai saat MIDTRANS_SERVER_KEY terisi dan bukan testing),
//               PembayaranService (buatTransaksi, tautanBayar), dan
//               PembayaranCallbackController (terjemahkan notifikasi webhook).
// Fungsi: implementasi PaymentGateway asli memakai Midtrans Snap.
// Catatan: konfigurasi lewat env MIDTRANS_SERVER_KEY / MIDTRANS_IS_PRODUCTION;
//          setelah mengubah .env jalankan `php artisan config:clear`.

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Pembayaran;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;
use Throwable;

/**
 * Gateway Midtrans Snap (SECURITY.md bagian 5).
 *
 * buatTransaksi() hanya menyusun order_id; transaksi Snap baru dibuat saat
 * tautanBayar() dipanggil, jadi tidak ada panggilan jaringan di dalam transaksi
 * database milik PembayaranService.
 *
 * Verifikasi tanda tangan Midtrans (sha512) ada di terjemahkan(), bukan
 * tandaTanganSah(), karena rumusnya butuh field mentah notifikasi
 * (status_code dan gross_amount) yang tidak ikut ke payload internal.
 *
 * Simulator tetap hidup: tandaTanganSimulasi() menurunkan HMAC ke
 * MockQrisGateway, dan tandaTanganSah() menerima HMAC itu juga.
 */
class MidtransGateway implements PaymentGateway
{
    // $serverKey & $produksi dari config('services.midtrans.*'); $simulator dari container.
    public function __construct(
        private readonly string $serverKey,
        private readonly bool $produksi,
        private readonly MockQrisGateway $simulator,
    ) {
        // Konfigurasi statis dipasang sekali di konstruktor, dipakai Snap::createTransaction().
        Config::$serverKey = $serverKey;
        Config::$isProduction = $produksi;
        Config::$is3ds = true;
    }

    // order_id = "{kode_booking}-{id_pembayaran}"; dipakai Midtrans untuk mencocokkan webhook.
    public function buatTransaksi(Pembayaran $pembayaran): string
    {
        return $pembayaran->reservasi->kode_booking.'-'.$pembayaran->id_pembayaran;
    }

    // Dipanggil PembayaranController saat pelanggan membuka halaman bayar;
    // mengembalikan URL Snap, atau null kalau gagal (view menampilkan pesan error).
    /**
         * Midtrans menerima order_id yang sama berkali-kali selama sesi Snap-nya
         * belum pernah dibuka. Begitu pelanggan membuka Snap lalu menekan tombol
         * kembali, order_id itu terkunci ("has already been taken") sehingga token
         * baru harus memakai order_id lain — suffix acak, disimpan lebih dulu agar
         * notifikasi webhook tetap menemukan baris pembayarannya.
         *
         * ponytail: expiry Snap dibiarkan default (24 jam); batas_pembayaran aplikasi
         * tetap tenggat sebenarnya. Tambah 'expiry' di sini kalau perlu disamakan.
         */
        public function tautanBayar(Pembayaran $pembayaran): ?string
        {
            $orderId = (string) $pembayaran->id_transaksi_gateway;

            // Belum ada order_id (misal dibuat lewat mock) -> tidak bisa minta token.
            if ($orderId === '') {
                return null;
            }

            $buat = fn (string $id): string => $this->mintaToken($id, $pembayaran);

            try {
                return $buat($orderId);
            } catch (Throwable) {
                // Percobaan kedua: order_id lama sudah terkunci di Midtrans,
                // jadi tambah suffix acak dan simpan supaya webhook tetap ketemu.
                $orderId = Str::limit($orderId, 60, '').'-'.Str::lower(Str::random(8));
                $pembayaran->update(['id_transaksi_gateway' => $orderId]);

                try {
                    return $buat($orderId);
                } catch (Throwable $galat) {
                    // Gagal dua kali: cukup dicatat di log, controller menampilkan pesan ke user.
                    Log::error('Snap gagal membuat tautan bayar.', [
                        'id_pembayaran' => $pembayaran->id_pembayaran,
                        'order_id' => $orderId,
                        'galat' => $galat->getMessage(),
                    ]);

                    return null;
                }
            }
        }

        // Satu-satunya tempat yang benar-benar memanggil API Midtrans (jaringan).
        /** Dipisah agar pengujian bisa menggagalkan pembuatan token tanpa jaringan. */
        protected function mintaToken(string $orderId, Pembayaran $pembayaran): string
        {
            return Snap::createTransaction([
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int) round((float) $pembayaran->jumlah),
                ],
                'callbacks' => [
                    // Setelah bayar, Midtrans mengembalikan pelanggan ke halaman status ini.
                    'finish' => route('pelanggan.pembayaran.status', $pembayaran),
                ],
            ])->redirect_url;
        }

    // HMAC simulator tetap diterima sebagai jalur alternatif (dipakai test).
    public function tandaTanganSah(string $idTransaksi, string $status, string $tandaTangan): bool
    {
        return $this->simulator->tandaTanganSah($idTransaksi, $status, $tandaTangan);
    }

    // Tombol simulator di halaman status tetap berfungsi meski Midtrans aktif.
    public function tandaTanganSimulasi(string $idTransaksi, string $status): ?string
    {
        return $this->simulator->tandaTanganSimulasi($idTransaksi, $status);
    }

    // Dipanggil PembayaranCallbackController untuk menerjemahkan body webhook Midtrans.
    /**
     * Notifikasi Midtrans → payload internal (id_transaksi, status, tanda_tangan).
     * Mengembalikan null untuk notifikasi yang tidak perlu diproses: status
     * pending, status di luar peta, atau tanda tangan yang tidak sah.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>|null
     */
    public function terjemahkan(array $payload): ?array
    {
        // Field wajib; kalau salah satu hilang payload dianggap tidak valid.
        if (! isset(
            $payload['order_id'],
            $payload['transaction_status'],
            $payload['status_code'],
            $payload['gross_amount'],
            $payload['signature_key'],
        )) {
            return null;
        }

        // Hanya status yang mengubah pembayaran yang dipetakan; belum bayar diabaikan.
        $status = match ($payload['transaction_status']) {
            'settlement', 'capture' => 'sukses',
            'expire', 'cancel', 'deny' => 'gagal',
            default => null,
        };

        if ($status === null) {
            return null;
        }

        $idTransaksi = (string) $payload['order_id'];

        // Rumus resmi Midtrans: sha512(order_id + status_code + gross_amount + serverKey).
        $diharapkan = hash(
            'sha512',
            $idTransaksi.$payload['status_code'].$payload['gross_amount'].$this->serverKey,
        );

        // hash_equals mencegah timing attack; tanda tangan palsu ditolak dan dicatat.
        if (! hash_equals($diharapkan, (string) $payload['signature_key'])) {
            Log::warning('Notifikasi Midtrans dengan tanda tangan tidak sah ditolak.', [
                'order_id' => $idTransaksi,
            ]);

            return null;
        }

        // Bentuk payload internal; ditandatangani ulang pakai HMAC simulator agar
        // PembayaranService hanya perlu tahu satu jenis tanda tangan.
        return [
            'id_transaksi' => $idTransaksi,
            'status' => $status,
            'tanda_tangan' => (string) $this->simulator->tandaTanganSimulasi($idTransaksi, $status),
        ];
    }
}

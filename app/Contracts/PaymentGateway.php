<?php

// Dipakai oleh: AppServiceProvider (binding ke MockQrisGateway atau MidtransGateway),
//               PembayaranService, dan PembayaranCallbackController.
// Fungsi: kontrak (interface) gateway pembayaran QRIS — 3 method wajib.
// Catatan: tidak ada command artisan khusus; kelas ini hanya dipakai lewat container
//          Laravel (`app(PaymentGateway::class)`), dijalankan saat web request.

namespace App\Contracts;

use App\Models\Pembayaran;

/**
 * Kontrak gateway pembayaran QRIS.
 *
 * Implementasi saat ini hanya simulasi. Gateway asli cukup menambah kelas baru
 * yang mengimplementasikan interface ini, lalu ubah bindingnya di AppServiceProvider.
 */
interface PaymentGateway
{
    // Dipanggil PembayaranService saat membuat pembayaran qris; hasilnya disimpan
    // di kolom pembayaran.id_transaksi_gateway.
    /** Buat transaksi di sisi gateway, kembalikan id transaksi yang dipakai untuk mencocokkan callback. */
    public function buatTransaksi(Pembayaran $pembayaran): string;

    // Dipanggil PembayaranCallbackController sebelum mengubah status jadi sukses.
    /** Verifikasi tanda tangan payload callback (SECURITY.md bagian 5). */
    public function tandaTanganSah(string $idTransaksi, string $status, string $tandaTangan): bool;

    // Dipakai halaman status pembayaran untuk menampilkan tombol simulator.
    /**
     * Tanda tangan buatan sendiri untuk tombol simulator di halaman status.
     * Gateway yang tidak menyediakannya mengembalikan null sehingga kartu
     * simulator tidak ditampilkan.
     */
    public function tandaTanganSimulasi(string $idTransaksi, string $status): ?string;
}

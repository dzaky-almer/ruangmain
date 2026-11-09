<?php

// File ini mengatur laporan admin (reservasi, transaksi, pendapatan) dan ekspor CSV.
// Fungsi: menerima filter dari halaman, meminta data ke LaporanService,
//         lalu mengirimnya ke view atau mengunduhnya sebagai file CSV.
// Dipakai oleh: routes/web.php baris 79-84 (admin.laporan.*).
// Mengambil data lewat: LaporanService.php.
// Membaca tabel: `reservasi`, `detail_reservasi`, `pembayaran`, `room`, `fasilitas`.
//
// Untuk mencoba manual, jalankan server lalu login sebagai admin:
//   php artisan serve
// Lalu buka http://localhost:8000/admin/laporan/reservasi

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use App\Models\Room;
use App\Services\LaporanService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

/**
 * Laporan admin (fitur 79–93). Tiga halaman terpisah sesuai ARCHITECTURE.md
 * §4.3.9, disatukan oleh tab chip pada tampilan (DESIGN.md §8.15).
 */
class LaporanController extends Controller
{
    // LaporanService disuntikkan otomatis oleh Laravel.
    // Hampir semua perhitungan angka ada di service tersebut, bukan di sini.
    public function __construct(private readonly LaporanService $laporan) {}

    /** Fitur 79–83: filter periode, status, fasilitas, room. */
    // GET /admin/laporan/reservasi — halaman laporan reservasi.
    // Untuk mengujinya:
    //   php artisan test --filter=Laporan
    public function reservasi(Request $request): View
    {
        return view('admin.laporan.reservasi', [
            // Hasil query sesuai filter yang sedang aktif.
            'hasil' => $this->laporan->laporanReservasi($this->filterReservasi($request)),
            // Data dropdown filter.
            'fasilitas' => Fasilitas::query()->orderBy('nama_fasilitas')->get(),
            'room' => Room::query()->with('fasilitas')->orderBy('nama_room')->get(),
        ]);
    }

    /** Ekspor rincian reservasi sesuai filter yang sedang aktif. */
    // GET /admin/laporan/reservasi/ekspor — mengunduh CSV laporan reservasi.
    // Browser akan menyimpan file laporan-reservasi-YYYYMMDD-YYYYMMDD.csv.
    public function eksporReservasi(Request $request): StreamedResponse
    {
        $filter = $this->filterReservasi($request);
        $hasil = $this->laporan->laporanReservasi($filter);

        // Setiap baris reservasi diubah menjadi array sederhana sesuai urutan kolom CSV.
        $baris = $hasil['baris']->map(function ($item): array {
            // Tanggal bermain diambil dari detail reservasi paling awal.
            $jadwal = $item->detailReservasi->sortBy('tanggal')->first();

            return [
                $item->kode_booking,
                $item->pengguna?->nama,
                $item->pengguna?->email,
                $jadwal?->tanggal?->format('Y-m-d'),
                // Kalau satu reservasi memesan beberapa room, namanya digabung jadi satu sel.
                $item->detailReservasi->pluck('room.nama_room')->filter()->unique()->implode(', '),
                $item->detailReservasi->pluck('room.fasilitas.nama_fasilitas')->filter()->unique()->implode(', '),
                (float) $item->total_reservasi,
                $item->status_reservasi,
            ];
        })->all();

        // Baris terakhir berisi total supaya bisa langsung diperiksa di Excel.
        $baris[] = ['Total', '', '', '', '', '', $hasil['total'], ''];

        return $this->unduhCsv(
            $this->namaBerkas('reservasi', $filter['mulai'], $filter['akhir']),
            ['Kode booking', 'Pelanggan', 'Email', 'Tanggal', 'Room', 'Fasilitas', 'Total tagihan', 'Status'],
            $baris,
        );
    }

    /** Fitur 84–88: filter periode, metode, jenis, status pembayaran. */
    // GET /admin/laporan/transaksi — halaman laporan transaksi.
    public function transaksi(Request $request): View
    {
        return view('admin.laporan.transaksi', [
            'hasil' => $this->laporan->laporanTransaksi($this->filterTransaksi($request)),
        ]);
    }

    /** Ekspor rincian transaksi sesuai filter yang sedang aktif. */
    // GET /admin/laporan/transaksi/ekspor — mengunduh CSV laporan transaksi.
    public function eksporTransaksi(Request $request): StreamedResponse
    {
        $filter = $this->filterTransaksi($request);
        $hasil = $this->laporan->laporanTransaksi($filter);

        $baris = $hasil['baris']->map(fn ($item): array => [
            // Nomor referensi dibuat seragam: TX-0001, TX-0002, dst.
            'TX-'.str_pad((string) $item->id_pembayaran, 4, '0', STR_PAD_LEFT),
            $item->reservasi?->kode_booking,
            $item->reservasi?->pengguna?->nama,
            $item->jenis_pembayaran,
            $item->metode_pembayaran,
            (float) $item->jumlah,
            $item->status_pembayaran,
            $item->waktu_bayar?->format('Y-m-d H:i'),
            $item->diverifikasiOleh?->nama,
        ])->all();

        $baris[] = ['Total berhasil', '', '', '', '', $hasil['berhasil'], '', '', ''];

        return $this->unduhCsv(
            $this->namaBerkas('transaksi', $filter['mulai'], $filter['akhir']),
            ['Referensi', 'Kode booking', 'Pelanggan', 'Jenis', 'Metode', 'Nominal', 'Status', 'Waktu', 'Diverifikasi oleh'],
            $baris,
        );
    }

    /** Fitur 89–93: harian, mingguan, bulanan, periode tertentu. */
    // GET /admin/laporan/pendapatan — halaman laporan pendapatan.
    public function pendapatan(Request $request): View
    {
        [$periode, $mulai, $akhir, $hasil] = $this->filterPendapatan($request);

        return view('admin.laporan.pendapatan', [
            'periode' => $periode,
            'mulai' => $mulai,
            'akhir' => $akhir,
            'hasil' => $hasil,
        ]);
    }

    /** Ekspor rincian pendapatan sesuai filter yang sedang aktif. */
    // GET /admin/laporan/pendapatan/ekspor — mengunduh CSV laporan pendapatan.
    public function eksporPendapatan(Request $request): StreamedResponse
    {
        [$periode, $mulai, $akhir, $hasil] = $this->filterPendapatan($request);

        // Satu baris = satu titik periode (tanggal / minggu / bulan).
        $baris = collect($hasil['baris'])
            ->map(fn (array $titik): array => [$titik['label'], $titik['transaksi'], $titik['total']])
            ->all();
        $baris[] = ['Total', $hasil['transaksi'], $hasil['total']];

        return $this->unduhCsv(
            $this->namaBerkas('pendapatan', $mulai, $akhir, $periode),
            ['Periode', 'Jumlah transaksi berhasil', 'Pendapatan'],
            $baris,
        );
    }

    /** @return array{0: string, 1: Carbon, 2: Carbon, 3: array} */
    // Menyiapkan periode + rentang tanggal untuk laporan pendapatan.
    // Periode yang tidak dikenal otomatis dianggap 'harian'.
    private function filterPendapatan(Request $request): array
    {
        $periode = $request->string('periode')->trim()->value();
        $periode = in_array($periode, LaporanService::PERIODE, true) ? $periode : 'harian';

        // rentang() menghitung tanggal awal dan akhir berdasarkan periode.
        [$mulai, $akhir] = $this->laporan->rentang(
            $periode,
            $request->date('mulai'),
            $request->date('akhir'),
        );

        return [$periode, $mulai, $akhir, $this->laporan->laporanPendapatan($periode, $mulai, $akhir)];
    }

    /** @return array{mulai: ?Carbon, akhir: ?Carbon, status: ?string, fasilitas: ?int, room: ?int} */
    // Menyaring input filter laporan reservasi; nilai yang tidak sah diubah jadi null.
    private function filterReservasi(Request $request): array
    {
        $status = $request->string('status')->trim()->value();

        return [
            'mulai' => $request->date('mulai'),
            'akhir' => $request->date('akhir'),
            'status' => in_array($status, LaporanService::STATUS_RESERVASI, true) ? $status : null,
            'fasilitas' => $request->integer('fasilitas') ?: null,
            'room' => $request->integer('room') ?: null,
        ];
    }

    /** @return array{mulai: ?Carbon, akhir: ?Carbon, metode: ?string, jenis: ?string, status: ?string} */
    // Menyaring input filter laporan transaksi; nilai yang tidak sah diubah jadi null.
    private function filterTransaksi(Request $request): array
    {
        $metode = $request->string('metode')->trim()->value();
        $jenis = $request->string('jenis')->trim()->value();
        $status = $request->string('status')->trim()->value();

        return [
            'mulai' => $request->date('mulai'),
            'akhir' => $request->date('akhir'),
            'metode' => in_array($metode, LaporanService::METODE_PEMBAYARAN, true) ? $metode : null,
            'jenis' => in_array($jenis, LaporanService::JENIS_PEMBAYARAN, true) ? $jenis : null,
            'status' => in_array($status, LaporanService::STATUS_PEMBAYARAN, true) ? $status : null,
        ];
    }

    /**
     * Mengirim CSV langsung ke browser tanpa menyimpan file di server.
         * Header ditulis sekali di awal, lalu setiap baris data ditulis berurutan.
     *
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $baris
     */
    private function unduhCsv(string $nama, array $header, array $baris): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $baris): void {
            // php://output menulis langsung ke respons HTTP.
            $keluaran = fopen('php://output', 'w');
            // BOM UTF-8 supaya Excel membaca huruf beraksen dengan benar.
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, $header);
            foreach ($baris as $isi) {
                fputcsv($keluaran, $isi);
            }

            fclose($keluaran);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Menyusun nama berkas CSV: laporan-<jenis>[-<periode>]-<mulai>-<akhir>.csv
    private function namaBerkas(string $jenis, ?Carbon $mulai, ?Carbon $akhir, ?string $kualifikasi = null): string
    {
        $rentang = ($mulai?->format('Ymd') ?? 'awal').'-'.($akhir?->format('Ymd') ?? 'kini');

        return 'laporan-'.$jenis.($kualifikasi ? '-'.$kualifikasi : '').'-'.$rentang.'.csv';
    }
}

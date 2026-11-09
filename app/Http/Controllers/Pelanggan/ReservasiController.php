<?php

// File ini mengurus reservasi dari sisi pelanggan.
// Fungsi: menampilkan form reservasi multi-room, menyimpan reservasi baru,
//         menampilkan riwayat, dan menampilkan detail reservasi beserta QR kode booking.
// Dipakai oleh: routes/web.php baris 41-44 (pelanggan.reservasi.baru, pelanggan.reservasi.simpan,
//               pelanggan.reservasi.index, pelanggan.reservasi.detail).
// Mengambil data dari model: Fasilitas.php, Reservasi.php, Room.php.
// Memakai service: ReservasiService.php (menyimpan + menghitung harga),
//                  PembayaranService.php (opsi pembayaran + ringkasan biaya),
//                  KetersediaanService.php (slot kosong dan jam mulai terdekat).
// Validasi input ditangani oleh: SimpanReservasiRequest.php.
// Mengubah tabel: `reservasi`, `detail_reservasi`, `pembayaran` (lewat ReservasiService).
// View yang dipakai: pelanggan/reservasi/baru.blade.php, index.blade.php, detail.blade.php.
//
// Untuk mencoba manual:
//   php artisan serve
// Lalu login sebagai pelanggan dan buka http://localhost:8000/reservasi/baru

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanReservasiRequest;
use App\Models\Fasilitas;
use App\Models\Reservasi;
use App\Models\Room;
use App\Services\KetersediaanService;
use App\Services\PembayaranService;
use App\Services\ReservasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ReservasiController extends Controller
{
    // Ketiga service disuntikkan otomatis oleh Laravel lewat constructor.
    public function __construct(
        private readonly ReservasiService $reservasi,
        private readonly PembayaranService $pembayaran,
            private readonly KetersediaanService $ketersediaan,
        ) {}

    /**
     * Form reservasi multi-room (fitur 23–30).
     */
    // GET /reservasi/baru — menampilkan form pilih fasilitas, room, tanggal, dan jam.
    // Untuk mengujinya:
    //   php artisan test --filter=Reservasi
    public function formBaru(Request $request): View
    {
        // Kalau parameter tanggal kosong atau tidak valid, dipakai tanggal hari ini.
        $tanggal = $request->string('tanggal')->trim()->value() ?: now()->toDateString();
            // Jam yang sudah lewat tidak bisa dipilih.
            $jamTerdekat = $this->ketersediaan->jamMulaiTerdekat($tanggal);
                    // Sudah lewat jam tutup: tidak ada jam mulai yang sah, jadi durasi tidak boleh 0.
                    $sisaJam = max(1, KetersediaanService::JAM_TUTUP - $jamTerdekat);

                    return view('pelanggan.reservasi.baru', [
                // Hanya fasilitas aktif dan room aktif yang boleh muncul di form.
                'daftarFasilitas' => Fasilitas::query()
                    ->where('status_fasilitas', 'aktif')
                    ->with(['room' => fn ($query) => $query->where('status_room', '<>', 'tidak_aktif')->orderBy('nama_room')])
                    ->orderBy('id_fasilitas')
                    ->get(),
                // roomTerpilih() membaca room yang tadi dicentang di halaman katalog.
                'terpilih' => $this->roomTerpilih($request),
                'tanggal' => $tanggal,
                // Jam mulai dari URL dipakai kalau ada, kalau tidak diisi jam terdekat.
                'jamMulai' => $request->string('jam_mulai')->trim()->value() ?: sprintf('%02d:00', $jamTerdekat),
                'jamAwal' => $jamTerdekat,
                // Room tanpa slot kosong pada tanggal ini tidak bisa dipilih.
                'slotKosong' => $this->ketersediaan->slotKosongSemua(
                    Room::query()->where('status_room', '<>', 'tidak_aktif')->pluck('id_room')->all(),
                    $tanggal,
                ),
                // Durasi dibatasi 1 sampai MAKS_DURASI, dan tidak melebihi sisa jam buka.
                'durasi' => $request->filled('durasi')
                    ? min(ReservasiService::MAKS_DURASI, max(1, $request->integer('durasi')))
                                    : max(1, min(2, $sisaJam)),
            ]);
        }

    /**
     * Simpan reservasi: harga, subtotal, dan total dihitung server (fitur 31, 38).
     */
    // POST /reservasi — seluruh perhitungan ada di ReservasiService::simpanReservasi().
    // Setelah tersimpan, pelanggan langsung diarahkan ke halaman detail untuk membayar.
    public function simpan(SimpanReservasiRequest $request): RedirectResponse
    {
        $reservasi = $this->reservasi->simpanReservasi(
            // ID pelanggan diambil dari sesi login, bukan dari form, supaya tidak bisa dipalsukan.
            $request->user()->id_pengguna,
            $request->validated('items'),
        );

        return redirect()
            ->route('pelanggan.reservasi.detail', $reservasi)
            ->with('sukses', "Reservasi {$reservasi->kode_booking} dibuat. Selesaikan pembayaran sebelum ".$reservasi->batas_pembayaran->translatedFormat('d M Y, H.i').'.');
    }

    /**
     * Riwayat reservasi milik pelanggan yang login (fitur 34).
     */
    // GET /reservasi — daftar riwayat dengan pencarian kode booking dan filter status.
    public function index(Request $request): View
    {
        $cari = $request->string('cari')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $daftarReservasi = Reservasi::query()
            // where id_pengguna membuat pelanggan hanya melihat reservasinya sendiri.
            ->where('id_pengguna', $request->user()->id_pengguna)
            ->when($cari !== '', fn ($query) => $query->where('kode_booking', 'like', "%{$cari}%"))
            ->when(
                // Filter status hanya berlaku untuk status yang dikenal.
                in_array($status, ['menunggu_pembayaran', 'dikonfirmasi', 'check_in', 'selesai', 'dibatalkan'], true),
                fn ($query) => $query->where('status_reservasi', $status),
            )
            // Eager load relasi supaya daftar room dibaca sekaligus, bukan satu per satu.
            ->with('detailReservasi.room.fasilitas')
            ->orderByDesc('tanggal_dibuat')
            ->orderByDesc('id_reservasi')
            ->paginate(10)
            // withQueryString menjaga kata kunci pencarian tetap ada saat pindah halaman.
            ->withQueryString();

        return view('pelanggan.reservasi.index', [
            'daftarReservasi' => $daftarReservasi,
            'cari' => $cari,
            'status' => $status,
        ]);
    }

    /**
     * Detail reservasi beserta kode booking dan QR (fitur 32, 33, 38, 39).
     */
    // GET /reservasi/{reservasi} — halaman ini juga tempat pelanggan membayar.
    public function detail(Request $request, Reservasi $reservasi): View
    {
        // Reservasi milik pelanggan lain dibalas 404, supaya keberadaannya tidak terbocor.
        abort_if($reservasi->id_pengguna !== $request->user()->id_pengguna, 404);

        // Memuat relasi sekaligus: pemilik, detail room + fasilitas, dan data pembayaran.
        $reservasi->load(['pengguna', 'detailReservasi.room.fasilitas', 'pembayaran']);

        $detail = $reservasi->detailReservasi;
        // ringkasan() dari PembayaranService menghitung sisa tagihan dan status pembayaran.
        $ringkasan = $this->pembayaran->ringkasan($reservasi);

        return view('pelanggan.reservasi.detail', array_merge([
            'reservasi' => $reservasi,
            'detail' => $detail,
            'opsiPembayaran' => $this->pembayaran->opsi($reservasi),
            // QR berisi kode booking dan dibuat dalam bentuk SVG supaya tidak butuh file gambar.
            'qr' => QrCode::format('svg')->size(170)->margin(1)->generate($reservasi->kode_booking),
        ], $ringkasan));
    }

    /**
     * @return Collection<int, Room>
     */
    // Mengambil daftar room yang dipilih di halaman katalog.
    // Room utama datang dari id_room, tambahannya dari id_room_tambahan[] (reservasi multi-room).
    private function roomTerpilih(Request $request): Collection
    {
        $idRoom = array_filter([
            $request->input('id_room'),
            ...(array) $request->input('id_room_tambahan', []),
        ], fn ($id): bool => is_numeric($id));

        return $idRoom === []
            ? collect()
            : Room::query()->with('fasilitas')->whereIn('id_room', $idRoom)->orderBy('id_room')->get();
    }
}

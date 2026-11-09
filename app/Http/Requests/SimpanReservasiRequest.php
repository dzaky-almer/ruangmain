<?php

// File ini mengatur pengecekan input form reservasi (pilih room + tanggal + jam + durasi).
// Fungsi: memastikan setiap baris pesanan wajar, jamnya masih dalam jam buka,
//         belum lewat, dan durasinya tidak melewati jam tutup.
// Dipakai oleh: route `POST /reservasi` (routes/web.php baris 42) lewat
//               Pelanggan\ReservasiController::simpan().
// Berhubungan dengan: ReservasiService.php (batas maksimum) dan
//                     KetersediaanService.php (jam buka/tutup + jam terdekat yang masih bisa dipesan).
//
// Untuk menguji validasi reservasi:
//   php artisan test --filter=Reservasi

namespace App\Http\Requests;

use App\Services\KetersediaanService;
use App\Services\ReservasiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanReservasiRequest extends FormRequest
{
    // Selalu true karena route-nya sudah dilindungi middleware `auth` + `peran:pelanggan`.
    public function authorize(): bool
    {
        return true;
    }

    // Aturan input form reservasi.
    // Form reservasi mengirim banyak baris sekaligus, jadi datanya berbentuk array `items`.
    // `items.*` artinya aturan itu berlaku untuk SETIAP baris di dalam array.
    // Batas jumlah room dan durasi diambil dari konstanta ReservasiService supaya
    // angkanya cuma ditulis di satu tempat.
    // `jam_mulai` harus jam penuh (regex), contoh 19:00 — bukan 19:30.
    // `after_or_equal:today` menolak tanggal yang sudah lewat.
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.ReservasiService::MAKS_ROOM],
            'items.*.id_room' => ['required', 'integer', Rule::exists('room', 'id_room')],
            'items.*.tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'items.*.jam_mulai' => ['required', 'regex:/^([01]\d|2[0-3]):00(:00)?$/'],
            'items.*.durasi' => ['required', 'integer', 'min:1', 'max:'.ReservasiService::MAKS_DURASI],
        ];
    }

    // Pengecekan lanjutan yang butuh data dari database, jadi tidak bisa ditulis
    // di rules() biasa.
    // Tiga hal yang diperiksa per baris pesanan:
    //   1. Jam mulai tidak lebih awal dari jam buka (misal 10.00).
    //   2. Jam mulai belum lewat dibanding jam sekarang (lewat jamMulaiTerdekat()).
    //   3. Jam mulai + durasi tidak melewati jam tutup.
    // Kalau aturan dasar di rules() sudah gagal, baris itu dilewati dulu supaya
    // user tidak dibanjiri pesan galat yang saling tumpang tindih.
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $jamBuka = (int) KetersediaanService::JAM_BUKA;
            $jamTutup = (int) KetersediaanService::JAM_TUTUP;

            foreach ((array) $this->input('items', []) as $indeks => $item) {
                if (! is_array($item) || $validator->errors()->hasAny(["items.{$indeks}.jam_mulai", "items.{$indeks}.durasi"])) {
                    continue;
                }

                // Dua digit pertama dari "19:00" → 19, supaya bisa dibandingkan sebagai angka.
                $jamMulai = (int) substr((string) ($item['jam_mulai'] ?? ''), 0, 2);

                if ($jamMulai < $jamBuka) {
                    $validator->errors()->add("items.{$indeks}.jam_mulai", sprintf('Jam mulai paling awal %02d.00.', $jamBuka));
                    continue;
                }

                    // Ambil jam terdekat yang masih bisa dipesan untuk tanggal ini.
                    // Contoh: sekarang jam 15.20, maka 15.00 sudah lewat → terdekat 16.00.
                    $jamTerdekat = app(KetersediaanService::class)
                        ->jamMulaiTerdekat((string) ($item['tanggal'] ?? ''));

                    if ($jamMulai < $jamTerdekat) {
                        $validator->errors()->add("items.{$indeks}.jam_mulai", sprintf('Jam mulai sudah lewat. Jam terdekat %02d.00.', $jamTerdekat));
                        continue;
                    }

                    if ($jamMulai + (int) ($item['durasi'] ?? 0) > $jamTutup) {
                        $validator->errors()->add("items.{$indeks}.durasi", sprintf('Durasi melewati jam tutup %02d.00. Jam mulai terakhir adalah %02d.00.', $jamTutup, $jamTutup - 1));
                    }
                }
            });
        }

    // Nama tampilan kolom, dipakai mengganti :attribute di pesan galat.
    // Karena datanya array, namanya memakai tanda bintang: `items.*.jam_mulai`.
    public function attributes(): array
    {
        return [
            'items' => 'daftar room',
            'items.*.id_room' => 'room',
            'items.*.tanggal' => 'tanggal bermain',
            'items.*.jam_mulai' => 'jam mulai',
            'items.*.durasi' => 'durasi',
        ];
    }

    // Pesan galat berbahasa Indonesia untuk aturan yang pesannya terlalu teknis.
    // Batas jumlah room dan durasi diambil dari konstanta ReservasiService supaya
    // kalau batasnya diubah, pesannya ikut berubah otomatis.
    public function messages(): array
    {
        return [
            'items.max' => 'Maksimal '.ReservasiService::MAKS_ROOM.' room dalam satu reservasi.',
            'items.*.jam_mulai.regex' => 'Jam mulai harus jam penuh, misalnya 19:00.',
            'items.*.durasi.max' => 'Durasi maksimal '.ReservasiService::MAKS_DURASI.' jam per room.',
        ];
    }
}

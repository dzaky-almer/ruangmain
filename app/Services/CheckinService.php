<?php

// File ini berfungsi seperti petugas depan: mengurus check-in (pelanggan datang) dan check-out (selesai).
// Aturan penting: jam selesai tidak pernah digeser walau pelanggan telat.
//        Keterlambatan hanya dicatat, tidak ada tagihan tambahan.
// Dipanggil oleh: AdminCheckinController (halaman check-in admin).
// Bekerja sama dengan: PembayaranService.php untuk memeriksa status lunas.
// Mengubah tabel: `detail_reservasi` (waktu_checkin/waktu_checkout), `room`, dan `reservasi`.

namespace App\Services;

use App\Models\DetailReservasi;
use App\Models\Reservasi;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckinService
{
    // Reservasi hanya boleh di-check-in kalau statusnya salah satu dari daftar ini.
    public const STATUS_BOLEH_CHECKIN = ['dikonfirmasi', 'check_in'];

    // PembayaranService disuntik otomatis oleh Laravel, dipakai untuk memeriksa status lunas.
    public function __construct(private readonly PembayaranService $pembayaran) {}

    /** Mencari reservasi dari kode booking yang diketik admin. Tidak ketemu berarti null. */
    public function cari(string $kodeBooking): ?Reservasi
    {
        // Rapikan dulu inputnya: buang spasi di ujung, jadikan huruf besar.
        $kode = strtoupper(trim($kodeBooking));

        if ($kode === '') {
            return null;
        }

        // with() sekaligus mengambil data pengguna, detail room, dan fasilitasnya
        // supaya tidak query berulang kali saat ditampilkan di view.
        return Reservasi::query()
            ->with(['pengguna', 'detailReservasi.room.fasilitas'])
            ->where('kode_booking', $kode)
            ->first();
    }

    /** Daftar room yang sedang dipakai: sudah check-in tapi belum check-out. */
    public function roomDigunakan(): Collection
    {
        // Gabungan whereNotNull checkin dan whereNull checkout berarti "sedang dipakai".
        return DetailReservasi::query()
            ->with(['room.fasilitas', 'reservasi.pengguna'])
            ->whereNotNull('waktu_checkin')
            ->whereNull('waktu_checkout')
            ->orderBy('waktu_checkin')
            ->get();
    }

    // Menggabungkan tanggal dengan jam mulai/jam selesai menjadi objek waktu (Carbon).
    /**
     * @return array{mulai: Carbon, selesai: Carbon}
     */
    public function jadwal(DetailReservasi $detail): array
    {
        return [
            'mulai' => $detail->tanggal->copy()->setTimeFromTimeString((string) $detail->jam_mulai),
            'selesai' => $detail->tanggal->copy()->setTimeFromTimeString((string) $detail->jam_selesai),
        ];
    }

    /** Pelanggan terlambat berapa menit? 0 kalau belum check-in atau datang lebih awal. */
    public function menitTerlambat(DetailReservasi $detail): int
    {
        $mulai = $this->jadwal($detail)['mulai'];

        if ($detail->waktu_checkin === null || ! $detail->waktu_checkin->greaterThan($mulai)) {
            return 0;
        }

        // Bagi 60 karena selisihnya dalam detik, sedangkan hasilnya diminta dalam menit.
        return max(0, intdiv($detail->waktu_checkin->getTimestamp() - $mulai->getTimestamp(), 60));
    }

    /**
     * Mengumpulkan semua alasan kenapa check-in ditolak.
     * Kalau hasilnya array kosong, berarti aman dan boleh check-in.
     *
     * @return array<int, string>
     */
    public function alasanTolakCheckin(DetailReservasi $detail): array
    {
        // Pastikan relasi room dan reservasi sudah dimuat.
        $detail->loadMissing(['room', 'reservasi']);
        $alasan = [];

        // 1. Status reservasinya harus sesuai.
        if (! in_array($detail->reservasi->status_reservasi, self::STATUS_BOLEH_CHECKIN, true)) {
            $alasan[] = "Reservasi berstatus {$detail->reservasi->status_reservasi} sehingga belum dapat check-in.";
        }

        // 2. Harus sudah lunas.
        if (! $this->pembayaran->lunas($detail->reservasi)) {
            $sisa = $this->pembayaran->sisaTagihan($detail->reservasi);

            $alasan[] = 'Check-in ditolak: reservasi belum lunas. Sisa tagihan Rp'.number_format($sisa, 0, ',', '.').' — lunasi dan verifikasi pembayaran dulu.';
        }

        // 3. Harus hari H, dan tidak boleh datang sebelum jam mulai.
        if ($detail->tanggal->toDateString() !== now()->toDateString()) {
            $alasan[] = 'Hari ini bukan tanggal jadwal ('.$detail->tanggal->translatedFormat('d M Y').'). Pelanggan datang bukan pada hari H.';
        } elseif (now()->lessThan($this->jadwal($detail)['mulai'])) {
            $alasan[] = 'Belum jam mulai ('.$detail->jam_mulai.'). Pelanggan datang lebih awal, minta menunggu sampai jam mulai.';
        }

        // 4. Jangan check-in dua kali, dan roomnya harus benar-benar tersedia.
        if ($detail->waktu_checkin !== null) {
            $alasan[] = 'Room ini sudah check-in pada '.$detail->waktu_checkin->translatedFormat('d M Y, H.i').'.';
        } elseif ($detail->room->status_room !== 'tersedia') {
            $alasan[] = "Room {$detail->room->nama_room} berstatus {$detail->room->status_room} sehingga tidak dapat dipakai.";
        }

        return $alasan;
    }

    /**
     * Mencatat waktu check-in. Sekaligus mengubah status room menjadi 'digunakan'
     * dan status reservasi menjadi 'check_in'.
     */
    public function checkIn(DetailReservasi $detail): DetailReservasi
    {
        return DB::transaction(function () use ($detail): DetailReservasi {
            // Mengambil ulang dan mengunci semua baris yang akan diubah, supaya aman
            // kalau admin dobel klik atau ada 2 admin membuka bersamaan.
            $terkunci = DetailReservasi::query()->whereKey($detail->id_detail)->lockForUpdate()->firstOrFail();
            $reservasi = Reservasi::query()->whereKey($terkunci->id_reservasi)->lockForUpdate()->firstOrFail();
            $room = Room::query()->whereKey($terkunci->id_room)->lockForUpdate()->firstOrFail();

            // Menempelkan relasinya supaya alasanTolakCheckin() membaca data yang sudah terkunci ini.
            $terkunci->setRelation('reservasi', $reservasi);
            $terkunci->setRelation('room', $room);

            $alasan = $this->alasanTolakCheckin($terkunci);

            if ($alasan !== []) {
                throw ValidationException::withMessages(['checkin' => $alasan]);
            }

            // Setelah aman, perubahan baru disimpan.
            $terkunci->update(['waktu_checkin' => now()]);
            $room->update(['status_room' => 'digunakan']);

            // Menaikkan status reservasi kalau sebelumnya masih 'dikonfirmasi'.
            if ($reservasi->status_reservasi === 'dikonfirmasi') {
                $reservasi->update(['status_reservasi' => 'check_in']);
            }

            return $terkunci->refresh();
        });
    }

    /**
     * Mencatat waktu check-out. Room kembali menjadi 'tersedia'.
     * Kalau semua baris room-nya sudah selesai, reservasinya menjadi 'selesai'.
     */
    public function checkOut(DetailReservasi $detail): DetailReservasi
    {
        return DB::transaction(function () use ($detail): DetailReservasi {
            $terkunci = DetailReservasi::query()->whereKey($detail->id_detail)->lockForUpdate()->firstOrFail();

            // Belum check-in kok mau check-out?
            if ($terkunci->waktu_checkin === null) {
                throw ValidationException::withMessages(['checkout' => 'Room ini belum check-in sehingga tidak dapat check-out.']);
            }

            // Jangan check-out dua kali.
            if ($terkunci->waktu_checkout !== null) {
                throw ValidationException::withMessages(['checkout' => 'Room ini sudah check-out pada '.$terkunci->waktu_checkout->translatedFormat('d M Y, H.i').'.']);
            }

            $reservasi = Reservasi::query()->whereKey($terkunci->id_reservasi)->lockForUpdate()->firstOrFail();
            $room = Room::query()->whereKey($terkunci->id_room)->lockForUpdate()->firstOrFail();

            $terkunci->update(['waktu_checkout' => now()]);
            $room->update(['status_room' => 'tersedia']);

            // Memeriksa apakah masih ada baris lain di reservasi ini yang belum check-out.
            $belumSelesai = DetailReservasi::query()
                ->where('id_reservasi', $reservasi->id_reservasi)
                ->whereNull('waktu_checkout')
                ->exists();

            // Kalau semua sudah selesai, reservasi ditutup.
            if (! $belumSelesai && in_array($reservasi->status_reservasi, ['dikonfirmasi', 'check_in'], true)) {
                $reservasi->update(['status_reservasi' => 'selesai']);
            }

            return $terkunci->refresh();
        });
    }
}
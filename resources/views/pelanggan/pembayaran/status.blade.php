@extends('layouts.app')
@section('title', 'Status Pembayaran — '.$reservasi->kode_booking)
@section('content')
@php
    $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
    $varian = match (true) {
        $pembayaran->status_pembayaran === 'sukses' => 'berhasil',
        $kedaluwarsa => 'kedaluwarsa',
        $pembayaran->status_pembayaran === 'gagal' => 'gagal',
        $pembayaran->status_pembayaran === 'dibatalkan' => 'dibatalkan',
        default => 'menunggu',
    };
    $tampilan = [
        'berhasil' => ['h1' => 'Pembayaran Berhasil', 'script' => 'GAME ON!', 'chip' => 'Transaksi: Berhasil', 'kelasChip' => 'border-ok bg-mint text-ink', 'badge' => '', 'kelasKartu' => 'bg-[#e3f5e7]', 'kelasBadge' => 'bg-mint', 'kepala' => 'PEMBAYARAN BERHASIL', 'ket' => 'Pembayaran sudah dikonfirmasi.'],
        'menunggu' => ['h1' => 'Pembayaran Menunggu', 'script' => 'sebentar, ya...', 'chip' => 'Transaksi: Menunggu', 'kelasChip' => 'border-warn text-warn', 'badge' => '', 'kelasKartu' => 'bg-[#fff3cf]', 'kelasBadge' => 'bg-sun', 'kepala' => 'MENUNGGU PEMBAYARAN', 'ket' => 'Halaman ini diperbarui otomatis saat status berubah.'],
        'gagal' => ['h1' => 'Pembayaran Gagal', 'script' => 'belum jadi game over', 'chip' => 'Transaksi: Gagal', 'kelasChip' => 'border-bad bg-danger text-bad', 'badge' => '✖', 'kelasKartu' => 'bg-[#ffe1e1]', 'kelasBadge' => 'bg-bubble', 'kepala' => 'PEMBAYARAN GAGAL', 'ket' => 'Tidak ada pembayaran yang tercatat.'],
        'kedaluwarsa' => ['h1' => 'Pembayaran Kedaluwarsa', 'script' => 'belum jadi game over', 'chip' => 'Transaksi: Kedaluwarsa', 'kelasChip' => 'border-bad bg-danger text-bad', 'badge' => '', 'kelasKartu' => 'bg-[#ffe1e1]', 'kelasBadge' => 'bg-bubble', 'kepala' => 'PEMBAYARAN KEDALUWARSA', 'ket' => 'Batas waktu pembayaran sudah lewat.'],
        'dibatalkan' => ['h1' => 'Pembayaran Dibatalkan', 'script' => 'transaksi ini sudah ditutup', 'chip' => 'Transaksi: Dibatalkan', 'kelasChip' => 'border-ink/25 bg-paper-2 text-ink-2', 'badge' => '✖', 'kelasKartu' => 'bg-paper-2', 'kelasBadge' => 'bg-white', 'kepala' => 'PEMBAYARAN DIBATALKAN', 'ket' => 'Transaksi ini sudah ditutup.'],
    ][$varian];
    $tampilSimulator = $pembayaran->status_pembayaran === 'pending' && $tandaTanganSimulasi !== null;
    $tampilTombolBayar = $tampilSimulator && $pembayaran->metode_pembayaran === 'qris' && $midtransAktif;
    $bolehBayar = in_array($reservasi->status_reservasi, ['menunggu_pembayaran', 'dikonfirmasi'], true)
        && ! $ringkasan['lunas'];
@endphp
<section class="mx-auto max-w-2xl px-5 py-8 sm:px-8">
    <p class="text-xs font-bold tracking-[.16em] text-ink-2">RUANGMAIN / PELANGGAN</p>
    <p class="mt-6 font-script text-2xl text-ink-2">{{ $tampilan['script'] }}</p>
    <h1 class="text-2xl font-extrabold tracking-[-.04em] sm:text-4xl">{{ $tampilan['h1'] }}</h1>
    <div class="mt-3 h-1.5 w-40 -rotate-1 bg-bubble"></div>

    <div class="mt-8 rounded-[18px] border-2 border-ink {{ $tampilan['kelasKartu'] }} px-6 py-7 text-center shadow-[4px_4px_0_#252525]">
        <span class="mx-auto flex size-14 items-center justify-center rounded-full border-2 border-ink {{ $tampilan['kelasBadge'] }} text-2xl" aria-hidden="true">{{ $tampilan['badge'] }}</span>
        <p class="mt-4 text-xs font-extrabold tracking-[.18em] text-ink-2">{{ $tampilan['kepala'] }}</p>
        <p class="mt-1 text-3xl font-extrabold tabular-nums">{{ $rupiah((float) $pembayaran->jumlah) }}</p>
        <p class="mt-2 text-sm text-ink-2">{{ $tampilan['ket'] }}</p>
    </div>

    <article class="card-soft mt-6 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-extrabold">Detail transaksi</h2>
            <span class="chip-status {{ $tampilan['kelasChip'] }}">{{ $tampilan['chip'] }}</span>
        </div>
        <div class="mt-4 space-y-3">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Referensi</dt><dd class="font-bold tabular-nums">TRX-{{ strtoupper(substr(md5((string) $pembayaran->id_pembayaran), 0, 8)) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Kode booking</dt><dd class="font-bold tabular-nums">{{ $reservasi->kode_booking }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Jenis</dt><dd class="font-bold uppercase">{{ $pembayaran->jenis_pembayaran }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Metode</dt><dd class="font-bold uppercase">{{ $pembayaran->metode_pembayaran }}</dd></div>
                <div class="flex justify-between gap-4 border-t border-[#e5dccb] pt-2"><dt class="text-ink-2">Waktu</dt><dd class="font-bold tabular-nums">{{ $pembayaran->waktu_bayar?->translatedFormat('d M Y, H.i') ?? 'Belum diverifikasi' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Total berhasil</dt><dd class="font-bold tabular-nums">{{ $rupiah($ringkasan['sudahDibayar']) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Sisa tagihan</dt><dd class="font-extrabold tabular-nums">{{ $rupiah($ringkasan['sisaTagihan']) }}</dd></div>
            </dl>
            @if ($tampilTombolBayar)
                <a class="btn-primary max-w-fit" href="{{ route('pelanggan.pembayaran.lanjut', $pembayaran) }}">Bayar sekarang</a>
            @endif
        </div>
    </article>

    @if ($tampilSimulator)
        <div class="card-soft mt-4 p-5">
            <h2 class="font-extrabold">Simulator callback gateway (dev)</h2>
            <p class="mt-1 text-sm text-ink-2">Mengirim callback gateway tiruan dengan tanda tangan HMAC yang sah, persis seperti gateway sungguhan.</p>
            <form class="mt-4 flex flex-wrap gap-2" method="POST" action="{{ route('pelanggan.pembayaran.simulasi', $pembayaran) }}">
                @csrf
                <button class="btn-sketch bg-mint text-sm" name="status_simulasi" value="sukses" type="submit">Simulasi berhasil</button>
                <button class="btn-sketch bg-danger text-sm text-bad" name="status_simulasi" value="gagal" type="submit">Simulasi gagal</button>
            </form>
        </div>
    @endif

    <div class="mt-6 flex flex-wrap gap-3">
        @if ($varian === 'berhasil')
            <a class="btn-primary" href="{{ route('pelanggan.reservasi.detail', $reservasi) }}">Lihat reservasi</a>
        @elseif (in_array($varian, ['gagal', 'kedaluwarsa', 'dibatalkan'], true) && $bolehBayar)
            <a class="btn-primary" href="{{ route('pelanggan.pembayaran.form', $reservasi) }}">Coba Pembayaran Lagi</a>
                @elseif ($varian === 'menunggu' && $bolehBayar)
                    <a class="btn-primary" href="{{ route('pelanggan.pembayaran.form', $reservasi) }}">Lanjutkan Pembayaran</a>
        @endif
                @unless ($varian === 'berhasil')
                    <a class="btn-secondary" href="{{ route('pelanggan.reservasi.detail', $reservasi) }}">Detail Reservasi</a>
                @endunless
    </div>
</section>
@endsection
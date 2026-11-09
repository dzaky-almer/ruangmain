@extends('layouts.app')
@section('title', 'Riwayat Pembayaran — RUANGMAIN')
@section('content')
@php
    $labelStatus = [
        'pending' => 'Menunggu',
        'sukses' => 'Berhasil',
        'gagal' => 'Gagal',
        'dibatalkan' => 'Dibatalkan',
    ];
    $kelasStatus = [
        'pending' => 'chip-status bg-sun',
        'sukses' => 'chip-status bg-mint',
        'gagal' => 'chip-status bg-danger',
        'dibatalkan' => 'chip-status bg-paper-2',
    ];
    $filter = ['' => 'Semua', 'pending' => 'Menunggu', 'sukses' => 'Berhasil', 'gagal' => 'Gagal', 'dibatalkan' => 'Dibatalkan'];
    $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
@endphp
<section class="mx-auto max-w-5xl px-5 py-8 sm:px-8">
    <p class="text-xs font-bold tracking-[.16em] text-ink-2">RUANGMAIN / PELANGGAN</p>
    <h1 class="mt-6 text-3xl font-extrabold tracking-[-.04em] sm:text-5xl">Riwayat Pembayaran</h1>
    <div class="mt-4 h-1.5 w-36 -rotate-1 bg-bubble"></div>
    <p class="mt-4 text-ink-2">Semua transaksi DP dan pelunasan milikmu, terbaru lebih dulu.</p>

    <form class="mt-6 flex flex-wrap gap-3" method="GET" action="{{ route('pelanggan.pembayaran.index') }}">
        <input class="field-sketch max-w-xs" type="search" name="cari" value="{{ $cari }}" placeholder="RM-20261020-001">
        <button class="btn-primary" type="submit">Cari</button>
    </form>

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($filter as $nilai => $teks)
            <a class="chip-status border-2 border-ink {{ $status === $nilai ? 'bg-sun' : 'bg-paper-2' }}"
               href="{{ route('pelanggan.pembayaran.index', array_filter(['cari' => $cari, 'status' => $nilai])) }}">{{ $teks }}</a>
        @endforeach
    </div>

    @forelse ($daftarPembayaran as $item)
        <article class="card-paper mt-5 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-script text-lg text-ink-2">transaksi #{{ $item->id_pembayaran }}</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ $item->reservasi?->kode_booking ?? '—' }}</p>
                    <p class="mt-1 text-sm text-ink-2">{{ strtoupper($item->jenis_pembayaran) }} · {{ strtoupper($item->metode_pembayaran) }} · {{ $item->waktu_bayar?->translatedFormat('d M Y, H.i') ?? 'Belum dibayar' }}</p>
                </div>
                <span class="{{ $kelasStatus[$item->status_pembayaran] ?? 'chip-status bg-paper-2' }}">{{ $labelStatus[$item->status_pembayaran] ?? $item->status_pembayaran }}</span>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="font-extrabold tabular-nums">{{ $rupiah((float) $item->jumlah) }}</p>
                <a class="btn-secondary" href="{{ route('pelanggan.pembayaran.status', $item) }}">Lihat Status</a>
            </div>
        </article>
    @empty
        <div class="card-soft mt-6 p-6 text-ink-2">Belum ada pembayaran yang cocok.</div>
    @endforelse
</section>
@endsection

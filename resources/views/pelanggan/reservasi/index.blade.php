@extends('layouts.app')
@section('title', 'Riwayat Reservasi — RUANGMAIN')
@section('content')
@php
    $labelStatus = [
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'dikonfirmasi' => 'Dikonfirmasi',
        'check_in' => 'Sedang Berlangsung',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
    $kelasStatus = [
        'menunggu_pembayaran' => 'chip-status bg-sun',
        'dikonfirmasi' => 'chip-status bg-mint',
        'check_in' => 'chip-status bg-sky',
        'selesai' => 'chip-status bg-paper-2',
        'dibatalkan' => 'chip-status bg-danger',
    ];
    $filter = ['' => 'Semua', 'menunggu_pembayaran' => 'Menunggu', 'dikonfirmasi' => 'Dikonfirmasi', 'check_in' => 'Berlangsung', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
@endphp
<section class="mx-auto max-w-5xl px-5 py-8 sm:px-8">
    <p class="text-xs font-bold tracking-[.16em] text-ink-2">RUANGMAIN / PELANGGAN</p>
    <h1 class="mt-6 text-3xl font-extrabold tracking-[-.04em] sm:text-5xl">Riwayat Reservasi</h1>
    <div class="mt-4 h-1.5 w-36 -rotate-1 bg-sun"></div>
    <p class="mt-4 text-ink-2">Cari reservasimu dengan kode booking.</p>

    <form class="mt-6 flex flex-wrap gap-3" method="GET" action="{{ route('pelanggan.reservasi.index') }}">
        <input class="field-sketch max-w-xs" type="search" name="cari" value="{{ $cari }}" placeholder="RM-20261020-001">
        <button class="btn-primary" type="submit">Cari</button>
        <a class="btn-secondary" href="{{ route('pelanggan.reservasi.baru') }}">Reservasi Baru</a>
    </form>

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($filter as $nilai => $teks)
            <a class="chip-status border-2 border-ink {{ $status === $nilai ? 'bg-sun' : 'bg-paper-2' }}"
               href="{{ route('pelanggan.reservasi.index', array_filter(['cari' => $cari, 'status' => $nilai])) }}">{{ $teks }}</a>
        @endforeach
    </div>

    @php($ringkas = 'grid h-9 w-9 shrink-0 place-items-center rounded-full border-2 border-ink font-bold')
    @forelse ($daftarReservasi as $reservasi)
        <details class="card-paper group mt-5">
            <summary class="flex cursor-pointer list-none flex-wrap items-start justify-between gap-3 p-5 [&::-webkit-details-marker]:hidden">
                <div>
                    <p class="font-script text-lg text-ink-2">booking pass</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ $reservasi->kode_booking }}</p>
                    <p class="mt-1 text-sm text-ink-2">
                        Dibuat {{ $reservasi->tanggal_dibuat?->translatedFormat('d M Y, H.i') }}
                        · {{ $reservasi->detailReservasi->count() }} room
                        · Rp{{ number_format((float) $reservasi->total_reservasi, 0, ',', '.') }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="{{ $kelasStatus[$reservasi->status_reservasi] ?? 'chip-status bg-paper-2' }}">{{ $labelStatus[$reservasi->status_reservasi] ?? $reservasi->status_reservasi }}</span>
                    <span class="{{ $ringkas }} transition group-open:rotate-180" aria-hidden="true">⌄</span>
                </div>
            </summary>
            <div class="border-t-2 border-dashed border-[#e5dccb] px-5 pb-5 pt-4">
                <ul class="space-y-1 text-sm">
                    @foreach ($reservasi->detailReservasi as $detail)
                        <li>{{ $detail->room?->nama_room ?? 'Room' }} · {{ $detail->tanggal?->translatedFormat('d M Y') }} · {{ str_replace(':', '.', substr($detail->jam_mulai, 0, 5)) }}–{{ str_replace(':', '.', substr($detail->jam_selesai, 0, 5)) }}</li>
                    @endforeach
                </ul>
                <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                    <a class="btn-primary" href="{{ route('pelanggan.reservasi.detail', $reservasi) }}">Lihat Detail</a>
                </div>
            </div>
        </details>
    @empty
        <div class="card-soft mt-6 p-6 text-ink-2">Belum ada reservasi yang cocok.</div>
    @endforelse

    @if ($daftarReservasi->hasPages())
        <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-ink-2">{{ $daftarReservasi->firstItem() }}–{{ $daftarReservasi->lastItem() }} dari {{ $daftarReservasi->total() }} reservasi</p>
            <div class="flex items-center gap-2">
                @if ($daftarReservasi->onFirstPage())
                    <span class="{{ $ringkas }} cursor-not-allowed border-[#d8cfbe] text-[#b7ad9c]">←</span>
                @else
                    <a class="{{ $ringkas }} bg-paper-2 hover:bg-sun" href="{{ $daftarReservasi->previousPageUrl() }}" rel="prev">←</a>
                @endif
                @foreach ($daftarReservasi->getUrlRange(1, $daftarReservasi->lastPage()) as $halaman => $url)
                    <a class="{{ $ringkas }} {{ $daftarReservasi->currentPage() === $halaman ? 'bg-sun shadow-[3px_3px_0_#252525]' : 'bg-paper-2 hover:bg-sun' }}"
                       href="{{ $url }}" @if ($daftarReservasi->currentPage() === $halaman) aria-current="page" @endif>{{ $halaman }}</a>
                @endforeach
                @if ($daftarReservasi->hasMorePages())
                    <a class="{{ $ringkas }} bg-paper-2 hover:bg-sun" href="{{ $daftarReservasi->nextPageUrl() }}" rel="next">→</a>
                @else
                    <span class="{{ $ringkas }} cursor-not-allowed border-[#d8cfbe] text-[#b7ad9c]">→</span>
                @endif
            </div>
        </div>
    @endif
</section>
@endsection

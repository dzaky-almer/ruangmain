@extends('layouts.admin')

@section('title', 'Detail Pembayaran — RUANGMAIN')

@section('content')
    @php
        $referensi = 'TX-'.str_pad((string) $pembayaran->id_pembayaran, 4, '0', STR_PAD_LEFT);
        $varian = $pembayaran->metode_pembayaran === 'cash' && $pembayaran->status_pembayaran === 'pending'
            ? 'Cash'
            : ($pembayaran->metode_pembayaran === 'qris' && $pembayaran->status_pembayaran === 'pending' ? 'QRIS' : 'Terkunci');
    @endphp
    <section>
        <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / PEMBAYARAN / {{ $referensi }}</p>

        <div class="mt-6">
            <h1 class="text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Detail Pembayaran · {{ $varian }}</h1>
            <p class="mt-3 text-ink-2">{{ $referensi }} · {{ $reservasi->kode_booking }} · {{ $reservasi->pengguna?->nama ?? '—' }}</p>
            <div class="mt-4 h-1.5 w-36 -rotate-1 bg-mint"></div>
        </div>

        <div class="mt-6">
            @include('admin.pembayaran._detail')
        </div>

        <p class="mt-6"><a class="btn-secondary inline-block" href="{{ route('admin.pembayaran.index') }}">Kembali ke Manajemen Pembayaran</a></p>
    </section>
@endsection

@extends('layouts.app')
@section('title', 'Fasilitas — RUANGMAIN')
@section('content')
<section class="mx-auto max-w-6xl px-5 py-8 sm:px-12 lg:py-14">
    @php
        $urut = 0;
        $warnaJudul = 'text-ink-2';
        $warnaFrame = ['bg-sky', 'bg-bubble', 'bg-sun'];
    @endphp
    <div class="flex flex-wrap items-end justify-between gap-6">
        <div>
            <p class="font-script text-2xl text-ink-2">pilih arena</p>
            <h1 class="mt-1 text-4xl font-extrabold leading-[.9] tracking-[-.05em] sm:text-7xl">FASILITAS</h1>
            <svg class="mt-1 h-3 w-56 max-w-full text-sun sm:w-72" viewBox="0 0 220 14" fill="none" preserveAspectRatio="none" aria-hidden="true">
                <path d="M3 9c45-6 130-8 214-4" stroke="currentColor" stroke-width="7" stroke-linecap="round"/>
            </svg>
        </div>

        <form class="flex w-full flex-wrap items-center gap-3 sm:w-auto" id="form-katalog" action="{{ route('pelanggan.katalog') }}" method="GET">
            <div class="relative w-full sm:w-auto">
                <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4" stroke-linecap="round"/></svg>
                <input class="field-sketch !mt-0 w-full pl-9 sm:w-64" id="cari" name="cari" type="search" value="{{ $cari }}" placeholder="Cari fasilitas..." aria-label="Cari fasilitas">
            </div>
            <div class="relative">
                <select class="field-sketch !mt-0 appearance-none pr-9" id="pilih-kategori" name="kategori" aria-label="Filter kategori">
                    <option value="">Semua kategori</option>
                    @foreach ($chipKategori as $chip)
                        <option value="{{ $chip->id_fasilitas }}" @selected($kategori === (string) $chip->id_fasilitas)>{{ $chip->nama_fasilitas }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round"/></svg>
            </div>
        </form>
    </div>

    <div id="daftar-fasilitas">
    @if ($daftarFasilitas->isEmpty())
        <div class="alert-info mt-10">Fasilitas tidak ditemukan. Coba kata kunci lain atau pilih kategori Semua.</div>
    @endif

    <div class="mt-6 space-y-8 sm:space-y-14">
        @foreach ($daftarFasilitas as $fasilitas)
            @php
                $urut++;
                $roomPilihan = $fasilitas->room->where('status_room', '!=', 'tidak_aktif');
                $roomUtama = $roomPilihan->first();
                $adaTidakAktif = $fasilitas->room->contains('status_room', 'tidak_aktif');
                $hargaRoom = $roomPilihan->map(fn ($room) => $room->hargaEfektif())->filter(fn (float $nilai) => $nilai > 0);
                $hargaMin = $hargaRoom->min() ?? 0;
                $hargaMaks = $hargaRoom->max() ?? 0;
            @endphp
            <article class="grid items-center gap-6 md:grid-cols-2 md:gap-10">
                <div class="{{ $loop->even ? 'md:order-2' : '' }}">
                    <div class="card-paper {{ $warnaFrame[($urut - 1) % 3] }} -rotate-1 p-3 sm:p-4">
                        <div class="overflow-hidden rounded-[16px] border-2 border-ink bg-paper">
                            <img class="aspect-[16/9] w-full object-cover sm:aspect-[4/3]" src="{{ $fasilitas->gambar() }}" alt="{{ $fasilitas->nama_fasilitas }}" loading="lazy" data-gambar-fasilitas>
                        </div>
                    </div>
                </div>
                <div class="{{ $loop->even ? 'md:order-1' : '' }} px-1 py-1 sm:px-2">
                    <p class="text-xs font-bold tracking-[.16em] {{ $warnaJudul }}">{{ sprintf('%02d', $urut) }} / PILIH ARENA KAMU</p>
                    @if ($fasilitas->tagline)
                        <p class="font-script mt-1 text-2xl leading-none text-ink-2">{{ $fasilitas->tagline }}</p>
                    @endif
                    <h2 class="mt-2 text-2xl font-extrabold tracking-[-.04em] sm:text-4xl">{{ $fasilitas->nama_fasilitas }}</h2>
                    <p class="mt-3 text-sm leading-7 text-ink-2">{{ $fasilitas->deskripsi }}</p>
                    <p class="mt-5 text-sm text-ink-2">{{ $roomPilihan->count() }} room · {{ $adaTidakAktif ? 'Ada room tidak aktif' : 'Tersedia secara operasional' }} · 10.00–21.00</p>
                    <p class="text-2xl font-extrabold">Rp{{ number_format($hargaMin, 0, ',', '.') }}@if ($hargaMaks > $hargaMin) – Rp{{ number_format($hargaMaks, 0, ',', '.') }}@endif <span class="text-base font-bold text-ink-2">/ jam</span></p>
                    @if ($roomUtama)
                        <a class="btn-primary mt-5" href="{{ route('pelanggan.room.detail', $roomUtama) }}">Lihat Detail <span aria-hidden="true">→</span></a>
                    @else
                        <button class="btn-secondary mt-5 cursor-not-allowed" type="button" disabled>Lihat Detail</button>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
    </div>
</section>

<script>
    (() => {
        const form = document.getElementById('form-katalog');
        const hasil = document.getElementById('daftar-fasilitas');
        const cari = document.getElementById('cari');
        const kategori = document.getElementById('pilih-kategori');
        if (!form || !hasil || !cari || !kategori) return;

        const muat = async (url, push) => {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const hasilBaru = doc.getElementById('daftar-fasilitas');
            if (!hasilBaru) return;
            hasil.innerHTML = hasilBaru.innerHTML;
            if (push) history.pushState({ katalog: true }, '', url);
        };

        const kirim = () => {
            const url = new URL(form.action);
            if (cari.value) url.searchParams.set('cari', cari.value);
            if (kategori.value) url.searchParams.set('kategori', kategori.value);
            muat(url.toString(), true);
        };

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            kirim();
        });

        kategori.addEventListener('change', kirim);

        window.addEventListener('popstate', () => {
            const url = new URL(location.href);
            cari.value = url.searchParams.get('cari') ?? '';
            kategori.value = url.searchParams.get('kategori') ?? '';
            muat(location.href, false);
        });
    })();
</script>
@endsection

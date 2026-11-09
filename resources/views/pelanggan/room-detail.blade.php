@extends('layouts.app')
@section('title', $fasilitas->nama_fasilitas . ' — RUANGMAIN')
@section('content')
@php
    $chipRoom = [
        'tersedia' => ['kelas' => 'chip-aktif', 'label' => 'Tersedia'],
        'digunakan' => ['kelas' => 'chip-status border-warn text-warn', 'label' => 'Digunakan'],
        'tidak_aktif' => ['kelas' => 'chip-nonaktif', 'label' => 'Tidak aktif'],
    ];
    $harga = fn (float $nilai): string => 'Rp' . number_format($nilai, 0, ',', '.');
    $hargaRoom = $fasilitas->room->filter(fn ($room) => $room->status_room !== 'tidak_aktif')
        ->map(fn ($room) => $room->hargaEfektif())->filter(fn (float $nilai) => $nilai > 0);
    $hargaMin = $hargaRoom->min() ?? 0;
        $hargaMaks = $hargaRoom->max() ?? 0;
@endphp
    <section class="mx-auto max-w-6xl px-5 py-8 sm:px-12 lg:py-14">
    <p class="text-xs font-bold tracking-[.16em] text-ink-2">RUANGMAIN / PELANGGAN / FASILITAS</p>
        <h1 class="mt-4 text-4xl font-extrabold leading-[.9] tracking-[-.05em] sm:text-6xl">{{ $fasilitas->nama_fasilitas }}</h1>
        <p class="mt-3 max-w-2xl text-sm leading-7 text-ink-2">Ruang buat rivalitas seru, mabar panjang, dan satu pertandingan lagi.</p>
    <div class="mt-4 h-1.5 w-36 -rotate-1 bg-bubble"></div>

        <div class="mt-6 grid gap-5 sm:mt-8 sm:gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
                <div class="card-paper overflow-hidden rounded-[20px]">
                <img class="aspect-[16/9] w-full object-cover" src="{{ $fasilitas->gambar() }}" alt="{{ $fasilitas->nama_fasilitas }}" data-gambar-fasilitas>
            </div>
        </div>
            <div class="card-soft h-fit p-5">
                <p class="text-2xl font-extrabold">{{ $harga($hargaMin) }}@if ($hargaMaks > $hargaMin) – {{ $harga($hargaMaks) }}@endif <span class="text-sm font-bold text-ink-2">/ jam</span></p>
                <p class="mt-3 text-sm leading-7 text-ink-2">{{ $fasilitas->deskripsi }}</p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                @foreach (['Kapasitas 2–4 orang', 'AC & Wi-Fi', 'Kontroler lengkap', 'TV layar besar'] as $fitur)
                        <span class="rounded-full border-2 border-ink bg-sun px-2.5 py-1 text-center text-xs font-bold">{{ $fitur }}</span>
                @endforeach
            </div>
                <p class="mt-4 text-xs leading-6 text-ink-2">Operasional <strong class="text-ink">10.00–21.00</strong>. 21.00 adalah batas selesai, bukan jam mulai.</p>
        </div>
    </div>

        <div class="mt-8 sm:mt-12">
            <h2 class="text-2xl font-extrabold tracking-[-.03em] sm:text-3xl">Temukan slot mainmu.</h2>
            <p class="mt-2 text-sm leading-7 text-ink-2">Pilih tanggal dulu, lalu pilih room untuk melihat slot jam yang masih kosong.</p>
            <div class="mt-4 w-full max-w-xs sm:mt-5">
            <label class="label-sketch" for="tanggal">Tanggal bermain</label>
            <input class="field-sketch" id="tanggal" type="date" value="{{ $tanggal }}" min="{{ now()->toDateString() }}" data-tanggal-fasilitas>
        </div>
    </div>

    <div class="mt-7 sm:mt-10">
            <h2 class="text-xl font-extrabold sm:text-2xl">Room yang tersedia</h2>
        <p class="mt-1 text-sm text-ink-2">Klik Pilih Room untuk membuka jadwal dan memesan slot.</p>

            <div class="mt-4 grid gap-4 sm:mt-5 sm:gap-5 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($fasilitas->room as $room)
                @php
                    // Status kartu ikut ketersediaan jam pada tanggal terpilih, bukan status operasional semata.
                    $kosong = $slotKosong[$room->id_room] ?? 0;
                    $penuh = $room->status_room !== 'tidak_aktif' && $kosong === 0;
                    $aktif = $room->status_room !== 'tidak_aktif' && ! $penuh;
                    $chip = $room->status_room === 'tidak_aktif'
                        ? $chipRoom['tidak_aktif']
                        : ($penuh ? $chipRoom['digunakan'] : $chipRoom['tersedia']);
                @endphp
                <article class="card-paper flex flex-col p-4">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-lg font-extrabold">{{ $room->nama_room }}</h3>
                        <span class="{{ $chip['kelas'] }}">{{ $chip['label'] }}</span>
                    </div>
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-2">Kapasitas</dt><dd class="font-bold tabular-nums">{{ $room->kapasitas }} orang</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-2">Jam buka</dt><dd class="font-bold tabular-nums">10.00–21.00</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-2">Harga</dt><dd class="font-extrabold tabular-nums">{{ $harga($room->hargaEfektif()) }} / jam</dd></div>
                    </dl>
                    <div class="mt-4 grow"></div>
                    @if ($aktif)
                        <a class="btn-primary inline-block text-center" data-tombol-pilih-room
                           href="{{ route('pelanggan.room.jadwal', ['room' => $room, 'tanggal' => $tanggal]) }}">Pilih Room</a>
                    @elseif ($penuh)
                        <button class="btn-secondary cursor-not-allowed text-center" type="button" disabled data-tombol-room-penuh>Room penuh · {{ $tanggal }}</button>
                    @else
                        <button class="btn-secondary cursor-not-allowed text-center" type="button" disabled>Room tidak aktif</button>
                    @endif
                </article>
            @empty
                <p class="text-sm text-ink-2">Belum ada room pada fasilitas ini.</p>
            @endforelse
        </div>
    </div>
</section>

<script>
    (() => {
        // Status room dirender server, jadi tanggal baru harus minta render ulang.
        const tanggal = document.querySelector('[data-tanggal-fasilitas]');
        if (! tanggal) return;

        tanggal.addEventListener('change', () => {
            if (! tanggal.value) return;

            const url = new URL(window.location.href);
            url.searchParams.set('tanggal', tanggal.value);
            window.location.assign(url.toString());
        });
    })();
</script>
@endsection

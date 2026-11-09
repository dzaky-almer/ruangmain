@extends('layouts.app')
@section('title', $roomTerpilih->nama_room . ' — Jadwal — RUANGMAIN')
@section('content')
@php
    $chipRoom = [
        'tersedia' => ['kelas' => 'chip-aktif', 'label' => 'Tersedia'],
        'digunakan' => ['kelas' => 'chip-status border-warn text-warn', 'label' => 'Digunakan'],
        'tidak_aktif' => ['kelas' => 'chip-nonaktif', 'label' => 'Tidak aktif'],
    ];
    $chip = $chipRoom[$roomTerpilih->status_room] ?? $chipRoom['tidak_aktif'];
    $harga = fn (float $nilai): string => 'Rp' . number_format($nilai, 0, ',', '.');
    $bisaPesan = $statusSlot !== [];
    $jamMulaiDefault = sprintf('%02d:00', $jamAwal);
    // Sisa jam hari ini bisa habis kalau sudah lewat 20.00.
    $adaSisaJam = $jamAwal < 21;
    $durasiDefault = max(1, min(2, 21 - $jamAwal));
@endphp
<section class="mx-auto max-w-6xl px-5 py-8 sm:px-12 lg:py-14">
    <p class="text-xs font-bold tracking-[.16em] text-ink-2">RUANGMAIN / PELANGGAN / JADWAL</p>

    <div class="mt-6 flex flex-wrap items-center gap-3 text-sm">
        <a class="chip-status border-ink text-ink" href="{{ route('pelanggan.room.detail', ['room' => $roomTerpilih, 'tanggal' => $tanggal]) }}">← {{ $fasilitas->nama_fasilitas }}</a>
    </div>

    <h1 class="mt-4 text-4xl font-extrabold leading-[.9] tracking-[-.05em] sm:text-6xl">{{ $roomTerpilih->nama_room }}</h1>
    <p class="mt-3 max-w-xl text-sm leading-7 text-ink-2">Tentukan tanggal dan jam mainmu, lalu lanjut ke reservasi.</p>
    <div class="mt-4 h-1.5 w-36 -rotate-1 bg-bubble"></div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <div class="card-paper p-5">
                <h2 class="text-lg font-extrabold sm:text-xl">Pilih tanggal</h2>
                <p class="mt-1 text-sm text-ink-2">Slot yang sudah dipesan ditandai merah muda.</p>
                <div class="mt-4 max-w-xs">
                    <label class="label-sketch" for="tanggal">Tanggal bermain</label>
                    <input class="field-sketch" id="tanggal" type="date" value="{{ $tanggal }}" min="{{ now()->toDateString() }}" data-tanggal>
                </div>
            </div>

            <div class="card-paper p-5">
                <h2 class="text-lg font-extrabold sm:text-xl">Slot jam</h2>
                <p class="mt-1 text-sm text-ink-2">Hijau masih kosong, merah muda sudah dipesan, abu-abu sudah lewat. Ini hanya pratinjau — jam main dipilih di langkah reservasi.</p>
                <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6" data-slot-grid>
                    @foreach ($statusSlot as $jam => $status)
                        @php $lewat = (int) substr($jam, 0, 2) < $jamAwal; @endphp
                        <div class="rounded-full border-2 px-3 py-2 text-center text-sm font-bold tabular-nums {{ $lewat ? 'border-ink/30 bg-paper-2 text-ink-2 line-through' : 'border-ink ' . ($status === 'tersedia' ? 'bg-mint' : 'bg-bubble opacity-70') }}">{{ $jam }}</div>
                    @endforeach
                </div>

                <p class="mt-5 text-xs leading-6 text-ink-2">
                    Jam operasional <strong class="tabular-nums text-ink">10.00–21.00</strong>. 21.00 adalah batas selesai, bukan jam mulai.
                </p>

                <p class="mt-2 text-xs leading-6 {{ $bisaPesan && $adaSisaJam ? 'text-ink-2' : 'font-bold text-ink' }}" data-pesan-ketersediaan>
                    @if (! $bisaPesan)
                        Room ini sedang tidak aktif, reservasi ditutup.
                    @elseif (! $adaSisaJam)
                        Jam hari ini sudah habis. Pilih tanggal lain.
                    @else
                        Pilih jam mulai dan durasi di halaman reservasi.
                    @endif
                </p>
            </div>
        </div>

        <aside class="card-paper h-fit bg-sun p-5">
            <h2 class="text-xs font-bold tracking-[.16em]">ROOM PILIHANMU</h2>
            <p class="mt-2 text-xl font-extrabold">{{ $roomTerpilih->nama_room }}</p>
            <div class="mt-2"><span class="{{ $chip['kelas'] }}">{{ $chip['label'] }}</span></div>
            <dl class="mt-4 space-y-1 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Kapasitas</dt><dd class="font-bold tabular-nums">{{ $roomTerpilih->kapasitas }} orang</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Jam buka</dt><dd class="font-bold tabular-nums">10.00–21.00</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Harga</dt><dd class="font-extrabold tabular-nums">{{ $harga($roomTerpilih->hargaEfektif()) }} / jam</dd></div>
            </dl>
            <div class="mt-5">
                @if ($bisaPesan && $adaSisaJam)
                    <a class="btn-primary inline-block text-center" data-tombol-reservasi
                       href="{{ route('pelanggan.reservasi.baru', ['id_room' => $roomTerpilih->id_room, 'tanggal' => $tanggal, 'jam_mulai' => $jamMulaiDefault, 'durasi' => $durasiDefault]) }}">Reservasi Room Ini</a>
                @else
                    <button class="btn-secondary cursor-not-allowed text-center" type="button" disabled data-tombol-room-mati>Reservasi Room Ini</button>
                @endif
            </div>
        </aside>
    </div>
</section>

<script type="application/json" data-konfigurasi-ketersediaan>{!! json_encode([
    'id_room' => $roomTerpilih->id_room,
    'aktif' => $bisaPesan,
    'url' => route('pelanggan.ketersediaan'),
    'token' => csrf_token(),
], JSON_UNESCAPED_UNICODE) !!}</script>

<script>
    (() => {
        const konfigurasi = JSON.parse(document.querySelector('[data-konfigurasi-ketersediaan]').textContent);
        if (! konfigurasi.aktif) return;

        const tanggal = document.querySelector('[data-tanggal]');

        tanggal.addEventListener('change', () => window.location.assign(
            new URL(`?tanggal=${encodeURIComponent(tanggal.value)}`, window.location.href).toString()
        ));
    })();
</script>
@endsection

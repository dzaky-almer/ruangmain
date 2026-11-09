@extends('layouts.admin')

@section('title', 'Reservasi — RUANGMAIN')

@section('content')
    @php
        $labelStatus = ['menunggu_pembayaran' => 'Menunggu', 'dikonfirmasi' => 'Dikonfirmasi', 'check_in' => 'Check-in', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
        $kelasReservasi = ['menunggu_pembayaran' => 'border-warn text-warn', 'dikonfirmasi' => 'border-info text-info', 'check_in' => 'border-ok text-ok', 'selesai' => 'border-[#b4aca0] text-ink-2', 'dibatalkan' => 'border-bad text-bad'];
        $kelasBayar = ['belum' => 'border-bad text-bad', 'dp' => 'border-warn text-warn', 'lunas' => 'border-ok text-ok'];
        $labelBayar = ['belum' => 'Belum dibayar', 'dp' => 'DP dibayar', 'lunas' => 'Lunas'];
    @endphp

    <section>
        <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / RESERVASI</p>

        <div class="mt-6">
            <p class="font-script text-xl text-ink-2">operasional</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Reservasi</h1>
            <div class="mt-4 h-1.5 w-32 -rotate-1 bg-sun"></div>
        </div>

        @php
            $filterAktif = collect(['cari', 'status', 'tanggal', 'pembayaran', 'fasilitas', 'room'])
                ->contains(fn ($kunci) => request()->filled($kunci));
            $filterLanjutanAktif = collect(['tanggal', 'pembayaran', 'fasilitas', 'room'])
                ->contains(fn ($kunci) => request()->filled($kunci));
        @endphp

        <form class="mt-6" method="GET">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[240px] flex-1">
                    <label class="label-sketch" for="cari">Cari kode booking / pelanggan</label>
                    <input class="field-sketch" id="cari" name="cari" value="{{ $cari }}" placeholder="RM-20261020-001 atau nama pelanggan">
                </div>
                <div class="min-w-[180px] flex-1">
                    <label class="label-sketch" for="status">Status reservasi</label>
                    <select class="field-sketch" id="status" name="status" onchange="this.form.requestSubmit()">
                        <option value="">Semua status</option>
                        @foreach ($labelStatus as $nilai => $teks)
                            <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $teks }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn-secondary" type="submit">Terapkan</button>
                @if ($filterAktif)
                    <a class="btn-secondary" href="{{ route('admin.reservasi.index') }}">Reset</a>
                @endif
            </div>

            <details class="group mt-3" @if ($filterLanjutanAktif) open @endif>
                <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-full border-2 border-[#ded6c7] bg-white px-4 py-1.5 text-sm font-bold text-ink-2 transition hover:border-ink hover:text-ink [&::-webkit-details-marker]:hidden">
                    Filter lanjutan
                    <span class="transition-transform group-open:rotate-180">&#9662;</span>
                </summary>
                <div class="card-soft mt-3 grid gap-3 p-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div><label class="label-sketch" for="tanggal">Tanggal bermain</label><input class="field-sketch" id="tanggal" name="tanggal" type="date" value="{{ request('tanggal') }}" onchange="this.form.requestSubmit()"></div>
                    <div>
                        <label class="label-sketch" for="pembayaran">Status pembayaran</label>
                        <select class="field-sketch" id="pembayaran" name="pembayaran" onchange="this.form.requestSubmit()">
                            <option value="">Semua pembayaran</option>
                            @foreach ($labelBayar as $nilai => $teks)
                                <option value="{{ $nilai }}" @selected($pembayaran === $nilai)>{{ $teks }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label-sketch" for="fasilitas">Fasilitas</label>
                        <select class="field-sketch" id="fasilitas" name="fasilitas" onchange="this.form.requestSubmit()">
                            <option value="">Semua fasilitas</option>
                            @foreach ($fasilitas as $item)
                                <option value="{{ $item->id_fasilitas }}" @selected((string) request('fasilitas') === (string) $item->id_fasilitas)>{{ $item->nama_fasilitas }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label-sketch" for="room">Room</label>
                        <select class="field-sketch" id="room" name="room" onchange="this.form.requestSubmit()">
                            <option value="">Semua room</option>
                            @foreach ($semuaRoom as $item)
                                <option value="{{ $item->id_room }}" @selected((string) request('room') === (string) $item->id_room)>{{ $item->nama_room }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </details>
        </form>

        <div class="relative mt-6 overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead class="border-b-2 border-ink text-xs uppercase tracking-[0.1em] text-ink-2">
                    <tr>
                        <th class="pr-4 pb-3 font-bold">Kode</th>
                        <th class="pr-4 pb-3 font-bold">Pelanggan</th>
                        <th class="pr-4 pb-3 font-bold">Tanggal</th>
                        <th class="pr-4 pb-3 font-bold">Room</th>
                        <th class="pr-4 pb-3 font-bold">Total</th>
                        <th class="pr-4 pb-3 font-bold">Reservasi</th>
                        <th class="pr-4 pb-3 font-bold">Bayar</th>
                        <th class="pr-4 pb-3 font-bold">Dibuat</th>
                        <th class="pb-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarReservasi as $item)
                        @php
                            $dibayar = (float) ($item->total_sukses ?? 0);
                            $statusBayar = $dibayar >= (float) $item->total_reservasi ? 'lunas' : ($dibayar > 0 ? 'dp' : 'belum');
                        @endphp
                        <tr class="border-b border-[#eee9e1] last:border-0">
                            <td class="max-w-[100px] py-3 pr-4 font-extrabold tabular-nums break-all">{{ $item->kode_booking }}</td>
                            <td class="max-w-[150px] py-3 pr-4">{{ $item->pengguna?->nama }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap">{{ $item->detailReservasi->min('tanggal')?->translatedFormat('D, d M Y') ?? '—' }}</td>
                            <td class="py-3 pr-4 tabular-nums">{{ $item->detailReservasi->count() }}</td>
                            <td class="py-3 pr-4 font-bold whitespace-nowrap tabular-nums">Rp{{ number_format((float) $item->total_reservasi, 0, ',', '.') }}</td>
                            <td class="py-3 pr-4"><span @class(['chip-status', $kelasReservasi[$item->status_reservasi] ?? 'border-[#b4aca0] text-ink-2'])>{{ $labelStatus[$item->status_reservasi] ?? $item->status_reservasi }}</span></td>
                            <td class="py-3 pr-4"><span @class(['chip-status', $kelasBayar[$statusBayar]])>{{ $labelBayar[$statusBayar] }}</span></td>
                            <td class="py-3 pr-4 whitespace-nowrap tabular-nums">
                                <span class="block">{{ $item->tanggal_dibuat?->translatedFormat('d M') ?? '—' }}</span>
                                <span class="block text-xs text-ink-2">{{ $item->tanggal_dibuat?->translatedFormat('H.i') }}</span>
                            </td>
                            <td class="py-3">
                                <div class="flex justify-end">
                                    <a class="rounded-full border-2 border-ink bg-white px-4 py-1.5 text-xs font-bold shadow-[2px_2px_0_#252525]" href="{{ route('admin.reservasi.detail', $item) }}">Detail</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-8 text-center text-ink-2" colspan="9">Reservasi tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5 flex items-center justify-between gap-4 text-sm text-ink-2">
            <span>{{ $daftarReservasi->total() }} data</span>
            <div class="flex items-center gap-2">
                @if ($daftarReservasi->onFirstPage())
                    <span class="rounded-full border-2 border-[#ded6c7] px-4 py-1.5 text-[#a89f92]">Sebelumnya</span>
                @else
                    <a class="rounded-full border-2 border-ink px-4 py-1.5 font-bold text-ink hover:bg-paper-2" href="{{ $daftarReservasi->previousPageUrl() }}">Sebelumnya</a>
                @endif
                <span class="tabular-nums"><strong class="text-ink">{{ $daftarReservasi->currentPage() }}</strong> / {{ $daftarReservasi->lastPage() }}</span>
                @if ($daftarReservasi->hasMorePages())
                    <a class="rounded-full border-2 border-ink px-4 py-1.5 font-bold text-ink hover:bg-paper-2" href="{{ $daftarReservasi->nextPageUrl() }}">Berikutnya</a>
                @else
                    <span class="rounded-full border-2 border-[#ded6c7] px-4 py-1.5 text-[#a89f92]">Berikutnya</span>
                @endif
            </div>
        </div>
    </section>
@endsection
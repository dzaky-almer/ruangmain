@extends('layouts.admin')

@section('title', 'Check-out — RUANGMAIN')

@section('content')
    @php
        $jam = fn (?string $nilai): string => $nilai ? str_replace(':', '.', substr($nilai, 0, 5)) : '—';
    @endphp

    <section>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / CHECK-OUT</p>
            <span class="text-xs font-bold text-ink-2">Waktu saat ini {{ now()->translatedFormat('d M Y, H.i') }}</span>
        </div>

        <div class="mt-6">
            <p class="font-script text-xl text-ink-2">selesai main</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Check-out</h1>
            <div class="mt-4 h-1.5 w-36 -rotate-1 bg-sun"></div>
            <p class="mt-3 text-ink-2">Room aktif · {{ $detail->count() }} room sedang digunakan.</p>
        </div>

        @if ($detail->isEmpty())
            <div class="mt-10 text-center">
                <svg class="mx-auto h-16 w-16 text-sun" viewBox="0 0 64 64" fill="currentColor" aria-hidden="true"><path d="M32 2c2 16 14 28 30 30-16 2-28 14-30 30-2-16-14-28-30-30 16-2 30-14 30-30Z"/></svg>
                <p class="mt-4 text-2xl font-extrabold uppercase tracking-[-0.04em] sm:text-3xl">Tidak ada room aktif</p>
                <svg class="mx-auto mt-1 h-3 w-44 max-w-full text-sky" viewBox="0 0 220 14" fill="none" preserveAspectRatio="none" aria-hidden="true"><path d="M3 9c45-6 130-8 214-4" stroke="currentColor" stroke-width="7" stroke-linecap="round"/></svg>
                <p class="mt-4 text-sm text-ink-2">Belum ada pelanggan yang sedang bermain. Belum ada detail reservasi yang sudah check-in — proses check-in dulu di halaman Check-in.</p>
                <a class="btn-secondary mt-5" href="{{ route('admin.checkin.index') }}">Buka halaman Check-in</a>
            </div>
        @else
            <div class="relative mt-6 overflow-x-auto">
                <table class="w-full min-w-[64rem] text-left text-sm">
                    <thead class="border-b-2 border-ink text-xs font-bold tracking-[0.12em] text-ink-2 uppercase">
                        <tr>
                            <th class="pr-4 pb-3">Room / Booking</th>
                            <th class="pr-4 pb-3">Pelanggan</th>
                            <th class="pr-4 pb-3">Mulai</th>
                            <th class="pr-4 pb-3">Selesai terjadwal</th>
                            <th class="pr-4 pb-3">Check-in aktual</th>
                            <th class="pr-4 pb-3">Kondisi</th>
                            <th class="pb-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#ede4d3]">
                        @foreach ($detail as $item)
                            @php $menit = $terlambat[$item->id_detail] ?? 0; @endphp
                            <tr>
                                <td class="py-3">
                                    <p class="font-bold">{{ $item->room?->nama_room }}</p>
                                    <p class="text-xs text-ink-2 tabular-nums">{{ $item->reservasi?->kode_booking }}</p>
                                </td>
                                <td class="py-3 font-bold">{{ $item->reservasi?->pengguna?->nama ?? '—' }}</td>
                                <td class="py-3 tabular-nums">{{ $jam($item->jam_mulai) }}</td>
                                <td class="py-3 tabular-nums">{{ $jam($item->jam_selesai) }}</td>
                                <td class="py-3 tabular-nums">{{ $item->waktu_checkin?->translatedFormat('d M, H.i') }}</td>
                                <td class="py-3">
                                    <span @class(['chip-status', 'border-warn text-warn' => $menit > 0, 'chip-aktif' => $menit === 0])>{{ $menit > 0 ? "Terlambat {$menit} menit" : 'Sedang berjalan' }}</span>
                                </td>
                                <td class="py-3 text-right">
                                    <form method="POST" action="{{ route('admin.checkout.simpan') }}">
                                        @csrf
                                        <input type="hidden" name="kode_booking" value="{{ $item->reservasi?->kode_booking }}">
                                        <input type="hidden" name="id_detail" value="{{ $item->id_detail }}">
                                        <button class="btn-primary" type="submit">Konfirmasi Check-out</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="alert-warning mt-6" role="status">
                <strong>Tindak lanjuti keterlambatan.</strong> Tidak ada denda otomatis tanpa aturan bisnis. Setelah konfirmasi, waktu aktual dicatat, jam selesai tidak bergeser, dan room kembali tersedia bila tidak ada kondisi lain yang menghalangi.
            </div>
        @endif
    </section>
@endsection

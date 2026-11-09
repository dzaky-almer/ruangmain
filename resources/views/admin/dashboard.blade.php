@extends('layouts.admin')

@section('title', 'Dashboard Admin — RUANGMAIN')

@section('content')
    @php
        $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
        $kpi = [
            ['label' => 'Reservasi hari ini', 'nilai' => $ringkasan['reservasiHariIni'], 'warna' => 'bg-[#ffd84d]'],
            ['label' => 'Reservasi mendatang', 'nilai' => $ringkasan['reservasiMendatang'], 'warna' => 'bg-[#69a7ff]'],
            ['label' => 'Room tersedia', 'nilai' => $ringkasan['roomTersedia'], 'warna' => 'bg-[#9ad9a0]'],
            ['label' => 'Room digunakan', 'nilai' => $ringkasan['roomDigunakan'], 'warna' => 'bg-[#ff985c]'],
            ['label' => 'Pelanggan', 'nilai' => $ringkasan['jumlahPelanggan'], 'warna' => 'bg-[#b7a0ff]'],
            ['label' => 'Pembayaran berhasil', 'nilai' => $rupiah($ringkasan['pendapatanHariIni']), 'warna' => 'bg-[#ff91b8]'],
        ];
        $labelReservasi = [
            'menunggu_pembayaran' => 'Menunggu Pembayaran',
            'dikonfirmasi' => 'Dikonfirmasi',
            'check_in' => 'Check-in',
            'selesai' => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
        ];
        $batang = $grafik['baris'];
        $jam = fn (?string $nilai): string => $nilai ? str_replace(':', '.', substr($nilai, 0, 5)) : '—';
        $puncak = max(1.0, (float) $batang->max('total'));
        $kunciHariIni = \Illuminate\Support\Carbon::today()->format('Y-m-d');
        $tanggalHariIni = \Illuminate\Support\Carbon::today()->translatedFormat('d M Y');
    @endphp
    <section>
        <p class="font-script text-xl text-ink-2">control room</p>

        <div class="mt-2">
            <h1 class="text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Dashboard</h1>
            <div class="mt-3 h-1.5 w-60 -rotate-1 bg-sun"></div>
            <p class="mt-3 text-base text-ink-2">Ringkasan operasional {{ $tanggalHariIni }}.</p>
        </div>

        <dl class="mt-10 grid gap-x-6 gap-y-8 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($kpi as $item)
                <div class="flex items-stretch gap-3">
                    <span class="h-14 w-1 shrink-0 {{ $item['warna'] }}"></span>
                    <div>
                        <dd class="text-3xl font-extrabold tabular-nums tracking-[-0.04em]">{{ $item['nilai'] }}</dd>
                        <dt class="mt-1 text-xs font-bold text-ink-2">{{ $item['label'] }}</dt>
                    </div>
                </div>
            @endforeach
        </dl>

        <div class="mt-12 grid grid-cols-1 gap-10 lg:grid-cols-[minmax(0,1fr)_340px] xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="min-w-0">
                <p class="text-sm font-bold text-ink-2">Pendapatan berhasil per hari, {{ $batang->first()['label'] ?? '' }} &ndash; {{ $batang->last()['label'] ?? '' }}.</p>

                <div class="mt-6 border-b-2 border-l-2 border-ink pl-3 pb-1">
                    <div class="flex h-[190px] items-end gap-1.5">
                        @forelse ($batang as $titik)
                            @php
                                $hariIniBatang = $titik['tanggal'] === $kunciHariIni;
                                $tinggi = max(2, (int) round(($titik['total'] / $puncak) * 100));
                                $sebelumnya = $loop->first ? null : (float) $batang[$loop->index - 1]['total'];
                                $arah = $sebelumnya === null || $sebelumnya === (float) $titik['total']
                                    ? 'netral'
                                    : ((float) $titik['total'] > $sebelumnya ? 'naik' : 'turun');
                            @endphp
                            <div class="flex h-full flex-1 items-end">
                                @if ($titik['total'] > 0)
                                    <div @class(['group/bar relative w-full border-ink', 'bg-sky' => $arah === 'naik', 'bg-bubble' => $arah === 'turun', 'bg-paper-2' => $arah === 'netral', 'border-[3px]' => $hariIniBatang, 'border-2' => ! $hariIniBatang]) style="height: {{ $tinggi }}%">
                                        <span @class(['pointer-events-none absolute bottom-full z-10 mb-1.5 hidden rounded-lg border-2 border-ink bg-white px-2.5 py-1.5 text-[11px] leading-tight font-bold whitespace-nowrap shadow-[2px_2px_0_#252525] group-hover/bar:block', '-translate-x-1/2' => ! $loop->first && ! $loop->last, 'left-0' => $loop->first, 'right-0' => $loop->last])>
                                            {{ $titik['label'] }}<br>
                                            <span class="font-extrabold tabular-nums">{{ $rupiah($titik['total']) }}</span>
                                            <span class="font-normal text-ink-2">· {{ $titik['transaksi'] }} transaksi</span>
                                        </span>
                                        <span class="pointer-events-none absolute inset-0 hidden bg-ink/10 group-hover/bar:block"></span>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-ink-2">Belum ada data pendapatan.</p>
                        @endforelse
                    </div>
                    <div class="mt-2 flex gap-1.5">
                        @foreach ($batang as $titik)
                            <span @class(['flex-1 text-center text-xs tabular-nums', 'font-extrabold' => $titik['tanggal'] === $kunciHariIni, 'text-ink-2' => $titik['tanggal'] !== $kunciHariIni])>{{ \Illuminate\Support\Carbon::parse($titik['tanggal'])->format('d/m') }}</span>
                        @endforeach
                    </div>
                </div>
                <p class="mt-3 text-xs text-ink-2">Sumbu Y maksimum: {{ $rupiah($puncak) }}</p>
                <p class="mt-2 text-xs text-ink-2">Warna batang: @include('admin._legend-batang')</p>
            </div>

            <div>
                <h2 class="text-xl font-extrabold tracking-[-0.02em]">Check-in perlu diproses</h2>
                @forelse ($perluDiproses['checkinMenunggu'] as $detail)
                    <div class="flex items-center justify-between gap-4 border-b border-[#eee9e1] py-3 last:border-0">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 font-extrabold tabular-nums">{{ $detail->reservasi?->kode_booking }}<span class="chip-status shrink-0 border-ink-2 font-bold text-ink-2">{{ $detail->room?->nama_room }}</span></p>
                            <p class="mt-0.5 text-sm tabular-nums text-ink-2">{{ $jam($detail->jam_mulai) }}–{{ $jam($detail->jam_selesai) }}</p>
                        </div>
                        <a class="shrink-0 rounded-full border-2 border-ink bg-sun px-4 py-1.5 text-sm font-bold shadow-[2px_2px_0_#252525]" href="{{ route('admin.checkin.detail', $detail->reservasi?->kode_booking) }}">Proses</a>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-[#766b66]">Tidak ada check-in yang menunggu diproses.</p>
                @endforelse

                <h2 class="mt-10 text-xl font-extrabold tracking-[-0.02em]">Pembayaran perlu diverifikasi</h2>
                @forelse ($perluDiproses['cashMenunggu'] as $item)
                    <div class="flex items-center justify-between gap-4 border-b border-l-4 border-b-[#eee9e1] border-l-warn py-3 pl-3 last:border-b-0">
                        <div class="min-w-0">
                            <p class="truncate font-extrabold tabular-nums">Cash TX-{{ str_pad((string) $item->id_pembayaran, 4, '0', STR_PAD_LEFT) }} menunggu. <span class="font-normal text-ink-2">·</span> {{ $item->reservasi?->pengguna?->nama ?? 'Pelanggan' }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm tabular-nums text-ink-2"><span class="chip-status shrink-0 border-warn bg-[#fdf3e3] text-warn">Cash</span> {{ $item->reservasi?->kode_booking ?? '—' }} · {{ ucfirst($item->jenis_pembayaran) }} {{ $rupiah((float) $item->jumlah) }} · Periksa penerimaan cash sebelum verifikasi.</p>
                        </div>
                        <a class="shrink-0 rounded-full border-2 border-ink bg-sun px-4 py-1.5 text-sm font-bold shadow-[2px_2px_0_#252525]" href="{{ route('admin.pembayaran.detail', $item) }}">Verifikasi</a>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-[#766b66]">Tidak ada pembayaran cash yang menunggu verifikasi.</p>
                @endforelse
            </div>
        </div>

        <div class="mt-12">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="text-xl font-extrabold tracking-[-0.02em]">Reservasi terbaru</h2>
                <a class="text-sm font-bold text-ink" href="{{ route('admin.reservasi.index') }}">Semua</a>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-[#eee9e1] text-xs text-ink-2">
                        <tr>
                            <th class="py-3 pr-4 font-bold">Kode</th><th class="py-3 pr-4 font-bold">Pelanggan</th>
                            <th class="py-3 pr-4 font-bold">Tanggal</th><th class="py-3 pr-4 font-bold">Total</th>
                            <th class="py-3 font-bold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reservasiTerbaru as $item)
                            <tr class="border-b border-[#eee9e1] last:border-0">
                                <td class="py-3 pr-4 font-extrabold tabular-nums">{{ $item->kode_booking }}</td>
                                <td class="py-3 pr-4">{{ $item->pengguna?->nama }}<span class="block text-xs text-ink-2">{{ $item->pengguna?->email }}</span></td>
                                <td class="py-3 pr-4 tabular-nums">{{ $item->tanggal_dibuat?->translatedFormat('d M Y') }}</td>
                                <td class="py-3 pr-4 font-bold tabular-nums">{{ $rupiah((float) $item->total_reservasi) }}</td>
                                <td class="py-3">
                                    <span @class(['chip-status border-warn text-warn' => $item->status_reservasi === 'menunggu_pembayaran', 'chip-status border-info text-info' => $item->status_reservasi === 'dikonfirmasi', 'chip-status border-ok text-ok' => $item->status_reservasi === 'check_in', 'chip-status border-ink-2 text-ink-2' => $item->status_reservasi === 'selesai', 'chip-status border-bad text-bad' => $item->status_reservasi === 'dibatalkan'])>
                                        {{ $labelReservasi[$item->status_reservasi] ?? $item->status_reservasi }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-8 text-center text-ink-2" colspan="5">Belum ada reservasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

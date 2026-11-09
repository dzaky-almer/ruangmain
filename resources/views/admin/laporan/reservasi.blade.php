@extends('layouts.admin')

@section('title', 'Laporan — RUANGMAIN')

@section('content')
    @php
        $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
        $labelStatus = [
            'menunggu_pembayaran' => 'Menunggu Pembayaran',
            'dikonfirmasi' => 'Dikonfirmasi',
            'check_in' => 'Check-in',
            'selesai' => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
        ];
        $warnaStatus = [
            'menunggu_pembayaran' => 'bg-sun',
            'dikonfirmasi' => 'bg-mint',
            'check_in' => 'bg-sky',
            'selesai' => 'bg-paper-2',
            'dibatalkan' => 'bg-danger',
        ];
        $hari = $hasil['perHari'];
        $puncak = max(1, (int) $hari->max('jumlah'));
        $tanggal = fn (string $kunci): ?\Illuminate\Support\Carbon => request($kunci) ? \Illuminate\Support\Carbon::parse(request($kunci)) : null;
        $rentangTeks = match (true) {
            (bool) $tanggal('mulai') && (bool) $tanggal('akhir') => $tanggal('mulai')->translatedFormat('d M Y').' – '.$tanggal('akhir')->translatedFormat('d M Y'),
            (bool) $tanggal('mulai') => 'sejak '.$tanggal('mulai')->translatedFormat('d M Y'),
            (bool) $tanggal('akhir') => 'sampai '.$tanggal('akhir')->translatedFormat('d M Y'),
            default => 'semua tanggal',
        };
    @endphp
    <section>
        <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / LAPORAN</p>

        <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="font-script text-xl text-ink-2">daftar-main</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Laporan Reservasi</h1>
                <div class="mt-4 h-1.5 w-36 -rotate-1 bg-sky"></div>
            </div>
            <a class="btn-secondary" href="{{ route('admin.laporan.reservasi.ekspor', request()->query()) }}">
                <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path d="M12 3v11m0 0 4-4m-4 4-4-4M4 19h16" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Ekspor CSV
            </a>
        </div>

        @include('admin.laporan._tab', ['aktif' => 'reservasi'])

        <form class="mt-6 flex flex-wrap items-start gap-4" method="GET">
            <div>
                <label class="label-sketch" for="mulai">Dari</label>
                <input class="field-sketch w-44 tabular-nums" id="mulai" name="mulai" type="date" value="{{ request('mulai') }}" onchange="this.form.requestSubmit()">
            </div>
            <div>
                <label class="label-sketch" for="akhir">Sampai</label>
                <input class="field-sketch w-44 tabular-nums" id="akhir" name="akhir" type="date" value="{{ request('akhir') }}" onchange="this.form.requestSubmit()">
            </div>
            <div class="relative">
                <label class="label-sketch" for="status">Status</label>
                <select class="field-sketch w-48 appearance-none pr-9" id="status" name="status" onchange="this.form.requestSubmit()">
                    <option value="">Semua status</option>
                    @foreach ($labelStatus as $nilai => $teks)
                        <option value="{{ $nilai }}" @selected(request('status') === $nilai)>{{ $teks }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 bottom-3.5 h-4 w-4 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round"/></svg>
            </div>
            <div class="relative">
                <label class="label-sketch" for="fasilitas">Fasilitas</label>
                <select class="field-sketch w-48 appearance-none pr-9" id="fasilitas" name="fasilitas" onchange="this.form.requestSubmit()">
                    <option value="">Semua fasilitas</option>
                    @foreach ($fasilitas as $item)
                        <option value="{{ $item->id_fasilitas }}" @selected((string) request('fasilitas') === (string) $item->id_fasilitas)>{{ $item->nama_fasilitas }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 bottom-3.5 h-4 w-4 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round"/></svg>
            </div>
            <div class="relative">
                <label class="label-sketch" for="room">Room</label>
                <select class="field-sketch w-52 appearance-none pr-9" id="room" name="room" onchange="this.form.requestSubmit()">
                    <option value="">Semua room</option>
                    @foreach ($room as $item)
                        <option value="{{ $item->id_room }}" @selected((string) request('room') === (string) $item->id_room)>{{ $item->nama_room }} · {{ $item->fasilitas?->nama_fasilitas }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 bottom-3.5 h-4 w-4 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round"/></svg>
            </div>
        </form>

        <div class="mt-8 flex flex-wrap gap-x-14 gap-y-6">
            <div>
                <p class="text-3xl font-extrabold tracking-[-0.04em] tabular-nums sm:text-5xl">{{ $hasil['baris']->count() }}</p>
                <p class="mt-1 text-sm text-ink-2">reservasi · {{ $rentangTeks }}</p>
            </div>
            <div>
                <p class="text-3xl font-extrabold tracking-[-0.04em] tabular-nums sm:text-5xl">{{ $rupiah($hasil['total']) }}</p>
                <p class="mt-1 text-sm text-ink-2">total tagihan · termasuk status Dibatalkan</p>
            </div>
        </div>

        <div class="mt-10 flex flex-wrap items-start gap-x-14 gap-y-6">
            <div class="min-w-[18rem] flex-1">
                <p class="text-sm text-ink-2"><strong class="font-extrabold text-ink">Reservasi per tanggal jadwal</strong>, {{ $rentangTeks }}</p>

                @if ($hari->isEmpty())
                    <p class="mt-4 text-ink-2">Grafik hanya tampil bila rentangnya 92 hari atau kurang. Persempit filter tanggal untuk melihat sebaran harian.</p>
                @else
                    <div class="mt-3 flex h-44 items-end gap-2 border-l-2 border-ink pl-2">
                        @foreach ($hari as $titik)
                            @php
                                $tinggi = max(4, (int) round(($titik['jumlah'] / $puncak) * 100));
                                $sebelumnya = $loop->first ? null : (int) $hari[$loop->index - 1]['jumlah'];
                                $arah = $sebelumnya === null || $sebelumnya === (int) $titik['jumlah']
                                    ? 'netral'
                                    : ((int) $titik['jumlah'] > $sebelumnya ? 'naik' : 'turun');
                            @endphp
                            <div @class(['group/bar relative flex-1 min-w-1 border-2 border-ink', 'bg-sky' => $arah === 'naik', 'bg-bubble' => $arah === 'turun', 'bg-paper-2' => $arah === 'netral']) style="height: {{ $tinggi }}%">
                                <span @class(['pointer-events-none absolute bottom-full z-10 mb-1.5 hidden rounded-lg border-2 border-ink bg-white px-2.5 py-1.5 text-[11px] leading-tight font-bold whitespace-nowrap shadow-[2px_2px_0_#252525] group-hover/bar:block', '-translate-x-1/2' => ! $loop->first && ! $loop->last, 'left-0' => $loop->first, 'right-0' => $loop->last])>
                                    {{ $titik['label'] }}<br>
                                    <span class="font-extrabold tabular-nums">{{ $titik['jumlah'] }} reservasi</span>
                                    <span class="font-normal text-ink-2">· {{ $rupiah($titik['total']) }}</span>
                                </span>
                                <span class="pointer-events-none absolute inset-0 hidden bg-ink/10 group-hover/bar:block"></span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-1 flex gap-2 pl-4">
                        @foreach ($hari as $titik)
                            <span class="flex-1 text-center text-[10px] text-ink-2 tabular-nums">{{ $titik['label'] }}</span>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-ink-2 tabular-nums">Puncak: {{ $puncak }} reservasi per hari</p>
                    <p class="mt-2 text-xs text-ink-2">Warna batang: @include('admin._legend-batang')</p>
                @endif
            </div>

            <div>
                <p class="text-sm text-ink-2"><strong class="font-extrabold text-ink">Sebaran status</strong></p>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @forelse ($hasil['perStatus'] as $sebaran)
                        <li class="chip-status border-2 border-ink {{ $warnaStatus[$sebaran['status']] ?? 'bg-paper-2' }} tabular-nums">
                            {{ $labelStatus[$sebaran['status']] ?? $sebaran['status'] }}
                            <span class="ml-1 font-extrabold">{{ $sebaran['jumlah'] }}</span>
                        </li>
                    @empty
                        <li class="text-ink-2">Belum ada status pada filter ini.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="relative mt-10 overflow-x-auto">
            <table class="w-full min-w-[1000px] text-left text-sm">
                <thead class="border-b-2 border-ink text-xs font-bold tracking-[0.12em] text-ink-2 uppercase">
                    <tr>
                        <th class="pr-4 pb-3">Kode</th>
                        <th class="pr-4 pb-3">Pelanggan</th>
                        <th class="pr-4 pb-3">Jadwal</th>
                        <th class="pr-4 pb-3">Room</th>
                        <th class="pr-4 pb-3">Fasilitas</th>
                        <th class="pr-4 pb-3 text-right">Total</th>
                        <th class="pb-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($hasil['baris'] as $item)
                        @php($jadwal = $item->detailReservasi->sortBy('tanggal')->first())
                        <tr class="border-b border-[#eee9e1] last:border-0">
                            <td class="py-3 pr-4 font-extrabold tabular-nums">{{ $item->kode_booking }}</td>
                            <td class="py-3 pr-4">{{ $item->pengguna?->nama }}<span class="block text-xs text-ink-2">{{ $item->pengguna?->email }}</span></td>
                            <td class="py-3 pr-4 tabular-nums">
                                {{ $jadwal?->tanggal?->translatedFormat('d M Y') }}
                                <span class="block text-xs text-ink-2">{{ $item->detailReservasi->count() }} room</span>
                            </td>
                            <td class="py-3 pr-4">{{ $item->detailReservasi->pluck('room.nama_room')->filter()->implode(', ') ?: '—' }}</td>
                            <td class="py-3 pr-4">{{ $item->detailReservasi->pluck('room.fasilitas.nama_fasilitas')->filter()->unique()->implode(', ') ?: '—' }}</td>
                            <td class="py-3 pr-4 text-right font-bold tabular-nums">{{ $rupiah((float) $item->total_reservasi) }}</td>
                            <td class="py-3">
                                <span @class(['chip-status bg-sun' => $item->status_reservasi === 'menunggu_pembayaran', 'chip-status bg-mint' => $item->status_reservasi === 'dikonfirmasi', 'chip-status bg-sky' => $item->status_reservasi === 'check_in', 'chip-status bg-paper-2' => $item->status_reservasi === 'selesai', 'chip-status bg-danger' => $item->status_reservasi === 'dibatalkan'])>
                                    {{ $labelStatus[$item->status_reservasi] ?? $item->status_reservasi }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-8 text-center text-ink-2" colspan="7">Tidak ada reservasi pada filter ini.</td></tr>
                    @endforelse
                </tbody>
                @if ($hasil['baris']->isNotEmpty())
                    <tfoot class="border-t-2 border-ink">
                        <tr>
                            <td class="py-3 pr-4 font-extrabold" colspan="5">Total</td>
                            <td class="py-3 pr-4 text-right font-extrabold tabular-nums">{{ $rupiah($hasil['total']) }}</td>
                            <td class="py-3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="mt-4 text-xs text-ink-2">Total tagihan menjumlahkan semua reservasi pada filter ini, termasuk yang masih Menunggu Pembayaran dan yang Dibatalkan.</p>
    </section>
@endsection

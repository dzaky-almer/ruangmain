@extends('layouts.admin')

@section('title', 'Laporan — RUANGMAIN')

@section('content')
    @php
        $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
        $labelPembayaran = ['pending' => 'Menunggu', 'sukses' => 'Berhasil', 'gagal' => 'Gagal', 'dibatalkan' => 'Dibatalkan'];
        $warnaStatus = ['pending' => 'bg-sun', 'sukses' => 'bg-mint', 'gagal' => 'bg-danger', 'dibatalkan' => 'bg-paper-2'];
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
                <p class="font-script text-xl text-ink-2">aliran-dana</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Laporan Transaksi</h1>
                <div class="mt-4 h-1.5 w-36 -rotate-1 bg-mint"></div>
            </div>
            <a class="btn-secondary" href="{{ route('admin.laporan.transaksi.ekspor', request()->query()) }}">
                <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path d="M12 3v11m0 0 4-4m-4 4-4-4M4 19h16" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Ekspor CSV
            </a>
        </div>

        @include('admin.laporan._tab', ['aktif' => 'transaksi'])

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
                <label class="label-sketch" for="metode">Metode</label>
                <select class="field-sketch w-40 appearance-none pr-9" id="metode" name="metode" onchange="this.form.requestSubmit()">
                    <option value="">Semua metode</option>
                    <option value="qris" @selected(request('metode') === 'qris')>QRIS</option>
                    <option value="cash" @selected(request('metode') === 'cash')>Cash</option>
                </select>
                <svg class="pointer-events-none absolute right-3 bottom-3.5 h-4 w-4 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round"/></svg>
            </div>
            <div class="relative">
                <label class="label-sketch" for="jenis">Jenis</label>
                <select class="field-sketch w-40 appearance-none pr-9" id="jenis" name="jenis" onchange="this.form.requestSubmit()">
                    <option value="">Semua jenis</option>
                    <option value="dp" @selected(request('jenis') === 'dp')>DP</option>
                    <option value="pelunasan" @selected(request('jenis') === 'pelunasan')>Pelunasan</option>
                </select>
                <svg class="pointer-events-none absolute right-3 bottom-3.5 h-4 w-4 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round"/></svg>
            </div>
            <div class="relative">
                <label class="label-sketch" for="status">Status</label>
                <select class="field-sketch w-44 appearance-none pr-9" id="status" name="status" onchange="this.form.requestSubmit()">
                    <option value="">Semua status</option>
                    @foreach ($labelPembayaran as $nilai => $teks)
                        <option value="{{ $nilai }}" @selected(request('status') === $nilai)>{{ $teks }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 bottom-3.5 h-4 w-4 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round"/></svg>
            </div>
        </form>

        <div class="mt-8 flex flex-wrap gap-x-14 gap-y-6">
            <div>
                <p class="text-3xl font-extrabold tracking-[-0.04em] tabular-nums sm:text-5xl">{{ $hasil['baris']->count() }}</p>
                <p class="mt-1 text-sm text-ink-2">transaksi tercatat · {{ $rentangTeks }}</p>
            </div>
            <div>
                <p class="text-3xl font-extrabold tracking-[-0.04em] tabular-nums sm:text-5xl">{{ $rupiah($hasil['berhasil']) }}</p>
                <p class="mt-1 text-sm text-ink-2">nominal berhasil · status Menunggu, Gagal, dan Dibatalkan tidak dihitung</p>
            </div>
        </div>

        <div class="mt-10 flex flex-wrap items-start gap-x-14 gap-y-6">
            <div class="min-w-[18rem] flex-1">
                <p class="text-sm text-ink-2"><strong class="font-extrabold text-ink">Transaksi per hari (Rp)</strong>, {{ $rentangTeks }}</p>

                @if ($hari->isEmpty())
                    <p class="mt-4 text-ink-2">Grafik hanya tampil bila rentangnya 92 hari atau kurang. Persempit filter tanggal untuk melihat sebaran harian.</p>
                @else
                    <div class="mt-3 flex h-44 items-end gap-2 border-l-2 border-ink pl-2">
                        @foreach ($hari as $titik)
                            @php
                                $tinggi = max(4, (int) round(($titik['total'] / max(1.0, (float) $hari->max('total'))) * 100));
                                $sebelumnya = $loop->first ? null : (float) $hari[$loop->index - 1]['total'];
                                $arah = $sebelumnya === null || $sebelumnya === (float) $titik['total']
                                    ? 'netral'
                                    : ((float) $titik['total'] > $sebelumnya ? 'naik' : 'turun');
                            @endphp
                            <div @class(['group/bar relative flex-1 min-w-1 border-2 border-ink', 'bg-sky' => $arah === 'naik', 'bg-bubble' => $arah === 'turun', 'bg-paper-2' => $arah === 'netral']) style="height: {{ $tinggi }}%">
                                <span @class(['pointer-events-none absolute bottom-full z-10 mb-1.5 hidden rounded-lg border-2 border-ink bg-white px-2.5 py-1.5 text-[11px] leading-tight font-bold whitespace-nowrap shadow-[2px_2px_0_#252525] group-hover/bar:block', '-translate-x-1/2' => ! $loop->first && ! $loop->last, 'left-0' => $loop->first, 'right-0' => $loop->last])>
                                    {{ $titik['label'] }}<br>
                                    <span class="font-extrabold tabular-nums">{{ $rupiah((float) $titik['total']) }}</span>
                                    <span class="font-normal text-ink-2">· {{ $titik['jumlah'] }} transaksi</span>
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
                    <p class="mt-3 text-xs text-ink-2 tabular-nums">Sumbu Y maksimum: {{ $rupiah((float) $hari->max('total')) }}</p>
                    <p class="mt-2 text-xs text-ink-2">Warna batang: @include('admin._legend-batang')</p>
                @endif
            </div>

            <div class="space-y-5">
                <div>
                    <p class="text-sm text-ink-2"><strong class="font-extrabold text-ink">Sebaran status</strong></p>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($hasil['perStatus'] as $sebaran)
                            <li class="chip-status border-2 border-ink {{ $warnaStatus[$sebaran['status']] ?? 'bg-paper-2' }} tabular-nums">
                                {{ $labelPembayaran[$sebaran['status']] ?? $sebaran['status'] }}
                                <span class="ml-1 font-extrabold">{{ $sebaran['jumlah'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <p class="text-sm text-ink-2"><strong class="font-extrabold text-ink">Metode · nominal berhasil</strong></p>
                    <ul class="mt-3 space-y-1 text-sm tabular-nums">
                        @foreach ($hasil['perMetode'] as $sebaran)
                            <li><strong class="font-extrabold uppercase">{{ $sebaran['metode'] }}</strong> · {{ $rupiah($sebaran['total']) }} <span class="text-ink-2">({{ $sebaran['jumlah'] }} transaksi)</span></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="relative mt-10 overflow-x-auto">
            <table class="w-full min-w-[1000px] text-left text-sm">
                <thead class="border-b-2 border-ink text-xs font-bold tracking-[0.12em] text-ink-2 uppercase">
                    <tr>
                        <th class="pr-4 pb-3">Referensi</th>
                        <th class="pr-4 pb-3">Booking</th>
                        <th class="pr-4 pb-3">Jenis</th>
                        <th class="pr-4 pb-3">Metode</th>
                        <th class="pr-4 pb-3 text-right">Nominal</th>
                        <th class="pr-4 pb-3">Status</th>
                        <th class="pb-3">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($hasil['baris'] as $item)
                        <tr class="border-b border-[#eee9e1] last:border-0">
                            <td class="py-3 pr-4 font-extrabold tabular-nums">TX-{{ str_pad((string) $item->id_pembayaran, 4, '0', STR_PAD_LEFT) }}</td>
                            <td class="py-3 pr-4 tabular-nums">
                                {{ $item->reservasi?->kode_booking ?? '—' }}
                                <span class="block text-xs text-ink-2">{{ $item->reservasi?->pengguna?->nama }}</span>
                            </td>
                            <td class="py-3 pr-4 uppercase">{{ $item->jenis_pembayaran }}</td>
                            <td class="py-3 pr-4 uppercase">{{ $item->metode_pembayaran }}</td>
                            <td class="py-3 pr-4 text-right font-bold tabular-nums">{{ $rupiah((float) $item->jumlah) }}</td>
                            <td class="py-3 pr-4">
                                <span @class(['chip-status bg-mint' => $item->status_pembayaran === 'sukses', 'chip-status bg-sun' => $item->status_pembayaran === 'pending', 'chip-status bg-danger' => $item->status_pembayaran === 'gagal', 'chip-status bg-paper-2' => $item->status_pembayaran === 'dibatalkan'])>
                                    {{ $labelPembayaran[$item->status_pembayaran] ?? $item->status_pembayaran }}
                                </span>
                            </td>
                            <td class="py-3 tabular-nums">
                                {{ $item->waktu_bayar?->translatedFormat('d M Y H:i') ?? '—' }}
                                @if ($item->diverifikasiOleh)
                                    <span class="block text-xs text-ink-2">Verifikasi: {{ $item->diverifikasiOleh->nama }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-8 text-center text-ink-2" colspan="7">Tidak ada transaksi pada filter ini.</td></tr>
                    @endforelse
                </tbody>
                @if ($hasil['baris']->isNotEmpty())
                    <tfoot class="border-t-2 border-ink">
                        <tr>
                            <td class="py-3 pr-4 font-extrabold" colspan="4">Total berhasil</td>
                            <td class="py-3 pr-4 text-right font-extrabold tabular-nums">{{ $rupiah($hasil['berhasil']) }}</td>
                            <td class="py-3" colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="mt-4 text-xs text-ink-2">Laporan Transaksi mencatat DP dan pelunasan sebagai baris terpisah; hanya status Berhasil yang dijumlahkan.</p>
    </section>
@endsection

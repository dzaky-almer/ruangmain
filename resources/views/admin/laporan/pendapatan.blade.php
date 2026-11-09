@extends('layouts.admin')

@section('title', 'Laporan — RUANGMAIN')

@section('content')
    @php
        $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
        $batang = $hasil['baris'];
        $puncak = max(1.0, (float) $batang->max('total'));
        $labelPeriode = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan', 'rentang' => 'Rentang khusus'];
        $rata = $hasil['transaksi'] > 0 ? $hasil['total'] / $hasil['transaksi'] : 0.0;
        $rentangTeks = $mulai->translatedFormat('D, d M Y').' – '.$akhir->translatedFormat('D, d M Y');
    @endphp
    <section>
        <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / LAPORAN</p>

        <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="font-script text-xl text-ink-2">angka-angka</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Laporan</h1>
                <div class="mt-4 h-1.5 w-36 -rotate-1 bg-sun"></div>
            </div>
            <a class="btn-secondary" href="{{ route('admin.laporan.pendapatan.ekspor', request()->query()) }}">
                <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path d="M12 3v11m0 0 4-4m-4 4-4-4M4 19h16" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Ekspor CSV
            </a>
        </div>

        @include('admin.laporan._tab', ['aktif' => 'pendapatan'])

        <form class="mt-6 flex flex-wrap items-start gap-4" method="GET">
            <input name="periode" type="hidden" value="{{ $periode }}">
            <div>
                <label class="label-sketch" for="mulai">Dari</label>
                <input class="field-sketch w-44 tabular-nums" id="mulai" name="mulai" type="date" value="{{ $mulai->format('Y-m-d') }}" onchange="this.form.requestSubmit()">
            </div>
            <div>
                <label class="label-sketch" for="akhir">Sampai</label>
                <input class="field-sketch w-44 tabular-nums" id="akhir" name="akhir" type="date" value="{{ $akhir->format('Y-m-d') }}" onchange="this.form.requestSubmit()">
            </div>
            <div class="relative">
                <label class="label-sketch" for="periode">Periode</label>
                <select class="field-sketch w-44 appearance-none pr-9 font-bold" id="periode" onchange="if (this.value) location.href = this.value">
                    @foreach ($labelPeriode as $nilai => $teks)
                        <option value="{{ route('admin.laporan.pendapatan', array_merge(request()->query(), ['periode' => $nilai])) }}" @selected($periode === $nilai)>{{ $teks }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 bottom-3.5 h-4 w-4 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round"/></svg>
            </div>
        </form>

        <div class="mt-8 flex flex-wrap gap-x-14 gap-y-6">
            <div>
                <p class="text-3xl font-extrabold tracking-[-0.04em] tabular-nums sm:text-5xl">{{ $rupiah($hasil['total']) }}</p>
                <p class="mt-1 text-sm text-ink-2">Total pembayaran berhasil · {{ $rentangTeks }}</p>
            </div>
            <div>
                <p class="text-3xl font-extrabold tracking-[-0.04em] tabular-nums sm:text-5xl">{{ $hasil['transaksi'] }}</p>
                <p class="mt-1 text-sm text-ink-2">transaksi berhasil · rata-rata {{ $rupiah($rata) }}</p>
            </div>
        </div>

        <div class="mt-10">
            <p class="text-sm text-ink-2"><strong class="font-extrabold text-ink">Pendapatan {{ mb_strtolower($labelPeriode[$periode]) }} (Rp)</strong>, {{ $rentangTeks }}</p>

            @if ($batang->isEmpty())
                <p class="mt-4 text-ink-2">Belum ada data pendapatan pada periode ini.</p>
            @else
                <div class="mt-3 flex h-44 items-end gap-2 border-l-2 border-ink pl-2">
                    @foreach ($batang as $titik)
                        @php
                            $tinggi = max(4, (int) round(((float) $titik['total'] / $puncak) * 100));
                            $sebelumnya = $loop->first ? null : (float) $batang[$loop->index - 1]['total'];
                            $arah = $sebelumnya === null || $sebelumnya === (float) $titik['total']
                                ? 'netral'
                                : ((float) $titik['total'] > $sebelumnya ? 'naik' : 'turun');
                        @endphp
                        <div @class(['group/bar relative flex-1 min-w-1 border-2 border-ink', 'bg-sky' => $arah === 'naik', 'bg-bubble' => $arah === 'turun', 'bg-paper-2' => $arah === 'netral']) style="height: {{ $tinggi }}%">
                            <span @class(['pointer-events-none absolute bottom-full z-10 mb-1.5 hidden rounded-lg border-2 border-ink bg-white px-2.5 py-1.5 text-[11px] leading-tight font-bold whitespace-nowrap shadow-[2px_2px_0_#252525] group-hover/bar:block', '-translate-x-1/2' => ! $loop->first && ! $loop->last, 'left-0' => $loop->first, 'right-0' => $loop->last])>
                                {{ $titik['label'] }}<br>
                                <span class="font-extrabold tabular-nums">{{ $rupiah($titik['total']) }}</span>
                                <span class="font-normal text-ink-2">· {{ $titik['transaksi'] }} transaksi</span>
                            </span>
                            <span class="pointer-events-none absolute inset-0 hidden bg-ink/10 group-hover/bar:block"></span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex gap-2 pl-4">
                    @foreach ($batang as $titik)
                        <span class="flex-1 text-center text-[10px] text-ink-2 tabular-nums">
                            {{ in_array($periode, ['harian', 'rentang'], true) ? \Illuminate\Support\Carbon::parse($titik['tanggal'])->format('d/m') : $titik['label'] }}
                        </span>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-ink-2 tabular-nums">Sumbu Y maksimum: {{ $rupiah($puncak) }}</p>
                <p class="mt-2 text-xs text-ink-2">Warna batang: @include('admin._legend-batang')</p>
            @endif
        </div>

        <div class="relative mt-10 max-w-xl overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b-2 border-ink text-xs font-bold tracking-[0.12em] text-ink-2 uppercase">
                    <tr>
                        <th class="pr-4 pb-3">Periode</th>
                        <th class="pr-4 pb-3">Transaksi</th>
                        <th class="pb-3 text-right">Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batang as $titik)
                        <tr class="border-b border-[#eee9e1] last:border-0">
                            <td class="py-3 pr-4 font-bold tabular-nums">{{ $titik['label'] }}</td>
                            <td class="py-3 pr-4 tabular-nums">{{ $titik['transaksi'] }}</td>
                            <td class="py-3 text-right font-bold tabular-nums">{{ $rupiah($titik['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-8 text-center text-ink-2" colspan="3">Tidak ada pendapatan pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                @if ($batang->isNotEmpty())
                    <tfoot class="border-t-2 border-ink">
                        <tr>
                            <td class="py-3 pr-4 font-extrabold">Total</td>
                            <td class="py-3 pr-4 font-extrabold tabular-nums">{{ $hasil['transaksi'] }}</td>
                            <td class="py-3 text-right font-extrabold tabular-nums">{{ $rupiah($hasil['total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="mt-4 text-xs text-ink-2">Hanya transaksi Berhasil yang dijumlahkan. DP dan pelunasan tercatat sebagai transaksi terpisah.</p>
    </section>
@endsection

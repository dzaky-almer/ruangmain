@extends('layouts.admin')

@section('title', 'Manajemen Pembayaran — RUANGMAIN')

@section('content')
    @php
        $labelStatus = ['pending' => 'Menunggu', 'sukses' => 'Berhasil', 'gagal' => 'Gagal', 'dibatalkan' => 'Dibatalkan'];
        $kelasStatus = ['pending' => 'border-warn text-warn', 'sukses' => 'border-ok text-ok', 'gagal' => 'border-bad text-bad', 'dibatalkan' => 'border-[#b4aca0] text-ink-2'];
        $labelJenis = ['dp' => 'DP', 'pelunasan' => 'Pelunasan'];
        $labelMetode = ['qris' => 'QRIS', 'cash' => 'Cash'];
        $filterMetode = ['' => 'Semua metode'] + $labelMetode;
        $filterJenis = ['' => 'Semua jenis'] + $labelJenis;
        $filterStatus = ['' => 'Semua status'] + $labelStatus;
        $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
        $verifikator = fn ($item): string => $item->diverifikasiOleh?->nama
            ?? ($item->metode_pembayaran === 'qris' && $item->status_pembayaran === 'sukses' ? 'Gateway (sandbox)' : '—');
    @endphp
    <section>
        <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / PEMBAYARAN</p>

        <div class="mt-6">
            <p class="font-script text-xl text-ink-2">uang masuk</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Pembayaran</h1>
            <div class="mt-4 h-1.5 w-72 -rotate-1 bg-ink"></div>
        </div>

        <form class="mt-6 flex flex-wrap items-center gap-3" method="GET">
            <div class="min-w-[220px] flex-1">
                <label class="sr-only" for="cari">Referensi / kode booking</label>
                <input class="field-sketch mt-0" id="cari" name="cari" value="{{ $cari }}" placeholder="Cari referensi / kode booking">
            </div>
            <div class="w-[150px]">
                <label class="sr-only" for="dari">Periode dari</label>
                <input class="field-sketch mt-0" id="dari" name="dari" type="date" value="{{ request('dari') }}" title="Saring memakai waktu bayar" onchange="this.form.requestSubmit()">
            </div>
            <div class="w-[150px]">
                <label class="sr-only" for="sampai">Periode sampai</label>
                <input class="field-sketch mt-0" id="sampai" name="sampai" type="date" value="{{ request('sampai') }}" title="Saring memakai waktu bayar" onchange="this.form.requestSubmit()">
            </div>
            <div class="w-[165px]">
                <label class="sr-only" for="metode">Metode</label>
                <select class="field-sketch mt-0" id="metode" name="metode" onchange="this.form.requestSubmit()">
                    @foreach ($filterMetode as $nilai => $teks)
                        <option value="{{ $nilai }}" @selected($metode === $nilai)>{{ $teks }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-[165px]">
                <label class="sr-only" for="jenis">Jenis</label>
                <select class="field-sketch mt-0" id="jenis" name="jenis" onchange="this.form.requestSubmit()">
                    @foreach ($filterJenis as $nilai => $teks)
                        <option value="{{ $nilai }}" @selected($jenis === $nilai)>{{ $teks }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-[165px]">
                <label class="sr-only" for="status">Status</label>
                <select class="field-sketch mt-0" id="status" name="status" onchange="this.form.requestSubmit()">
                    @foreach ($filterStatus as $nilai => $teks)
                        <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $teks }}</option>
                    @endforeach
                </select>
            </div>
            <a class="btn-secondary ml-auto" href="{{ route('admin.pembayaran.index') }}">Reset</a>
        </form>

        <div class="relative mt-8 overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b-2 border-ink text-xs uppercase tracking-[0.08em] text-ink-2">
                    <tr>
                        <th class="pr-4 pb-3 font-bold">Referensi</th>
                        <th class="pr-4 pb-3 font-bold">Kode booking</th>
                        <th class="pr-4 pb-3 font-bold">Pelanggan</th>
                        <th class="pr-4 pb-3 font-bold">Jenis</th>
                        <th class="pr-4 pb-3 font-bold">Metode</th>
                        <th class="pr-4 pb-3 font-bold">Nominal</th>
                        <th class="pr-4 pb-3 font-bold">Status</th>
                        <th class="pr-4 pb-3 font-bold">Waktu bayar</th>
                        <th class="pb-3 font-bold">Diverifikasi</th>
                        <th class="pb-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarPembayaran as $item)
                        <tr class="border-b border-[#eee9e1] last:border-0">
                            <td class="max-w-[110px] py-4 pr-4 font-extrabold tabular-nums break-all">TX-{{ str_pad((string) $item->id_pembayaran, 4, '0', STR_PAD_LEFT) }}</td>
                            <td class="max-w-[110px] py-4 pr-4 break-all">{{ $item->reservasi?->kode_booking ?? '—' }}</td>
                            <td class="max-w-[130px] py-4 pr-4">{{ $item->reservasi?->pengguna?->nama ?? '—' }}</td>
                            <td class="py-4 pr-4 whitespace-nowrap">{{ $labelJenis[$item->jenis_pembayaran] ?? $item->jenis_pembayaran }}</td>
                            <td class="py-4 pr-4 whitespace-nowrap">{{ $labelMetode[$item->metode_pembayaran] ?? $item->metode_pembayaran }}</td>
                            <td class="py-4 pr-4 font-bold whitespace-nowrap tabular-nums">{{ $rupiah((float) $item->jumlah) }}</td>
                            <td class="py-4 pr-4"><span class="chip-status {{ $kelasStatus[$item->status_pembayaran] ?? 'border-[#b4aca0] text-ink-2' }}">{{ $labelStatus[$item->status_pembayaran] ?? $item->status_pembayaran }}</span></td>
                            <td class="py-4 pr-4 whitespace-nowrap tabular-nums">{{ $item->waktu_bayar?->translatedFormat('d M, H.i') ?? '—' }}</td>
                            <td class="py-4 pr-4 text-ink-2">{{ $verifikator($item) }}</td>
                            <td class="py-4">
                                <div class="flex justify-end">
                                    <button class="rounded-full border-2 border-ink bg-white px-4 py-1.5 text-xs font-bold whitespace-nowrap shadow-[2px_2px_0_#252525]" type="button" onclick="document.getElementById('detail-{{ $item->id_pembayaran }}').showModal()">{{ $item->metode_pembayaran === 'cash' && $item->status_pembayaran === 'pending' ? 'Verifikasi' : 'Detail' }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-8 text-center text-ink-2" colspan="10">Pembayaran tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @foreach ($daftarPembayaran as $item)
            @if ($item->reservasi)
                <dialog id="detail-{{ $item->id_pembayaran }}" class="m-auto max-h-[90vh] w-[min(860px,calc(100%-2rem))] overflow-y-auto rounded-[22px] border-2 border-ink bg-white p-5 text-ink shadow-[6px_6px_0_#252525] backdrop:bg-ink/40">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / PEMBAYARAN</p>
                            <h2 class="mt-1 text-xl font-extrabold">TX-{{ str_pad((string) $item->id_pembayaran, 4, '0', STR_PAD_LEFT) }} · {{ $item->reservasi->kode_booking }}</h2>
                            <p class="mt-1 text-sm text-ink-2">{{ $item->reservasi->pengguna?->nama ?? '—' }}</p>
                        </div>
                        <button class="text-2xl leading-none text-ink-2 hover:text-ink" type="button" aria-label="Tutup detail pembayaran" onclick="this.closest('dialog').close()">×</button>
                    </div>

                    <div class="mt-5">
                        @include('admin.pembayaran._detail', ['pembayaran' => $item, 'reservasi' => $item->reservasi])
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button class="btn-secondary" type="button" onclick="this.closest('dialog').close()">Tutup</button>
                    </div>
                </dialog>
            @endif
        @endforeach
    </section>

    @if ($buka = old('_dialog'))
        <script>document.getElementById(@json($buka))?.showModal()</script>
    @endif
@endsection

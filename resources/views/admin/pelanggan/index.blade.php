@extends('layouts.admin')

@section('title', 'Manajemen Pelanggan — RUANGMAIN')

@section('content')
    @php
        $statusAkun = ['' => 'Semua status', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];
    @endphp
    <section>
        <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / PELANGGAN</p>

        <div class="mt-6">
            <p class="font-script text-xl text-ink-2">komunitas</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Manajemen Pelanggan</h1>
            <div class="mt-4 h-1.5 w-36 -rotate-1 bg-sun"></div>
            <p class="mt-3 text-ink-2">Untuk mengelola data pelanggan, status akun, dan riwayat reservasinya.</p>
        </div>

        <form class="mt-6 flex flex-wrap items-center gap-3" method="GET">
            <div class="relative w-full sm:max-w-md">
                <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4" stroke-linecap="round"/></svg>
                <label class="sr-only" for="cari">Cari pelanggan</label>
                <input class="block min-h-11 w-full rounded-full border-2 border-ink bg-white py-2.5 pr-4 pl-9 text-sm text-ink outline-none placeholder:text-ink-2/70 focus:ring-3 focus:ring-info/25" id="cari" name="cari" type="search" value="{{ $cari }}" placeholder="Cari nama, email, telepon...">
            </div>
            <div class="relative">
                <label class="sr-only" for="status">Status akun</label>
                <select class="block min-h-11 appearance-none rounded-full border-2 border-ink bg-white py-2.5 pr-9 pl-4 text-sm font-bold text-ink outline-none focus:ring-3 focus:ring-info/25" id="status" name="status" onchange="this.form.requestSubmit()">
                    @foreach ($statusAkun as $nilai => $teks)
                        <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $teks }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round"/></svg>
            </div>
        </form>

        <div class="relative mt-6 overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="border-b-2 border-ink text-xs uppercase tracking-[0.1em] text-ink-2">
                    <tr>
                        <th class="pr-4 pb-3 font-bold">Nama</th>
                        <th class="pr-4 pb-3 font-bold">Email</th>
                        <th class="pr-4 pb-3 font-bold">Telepon</th>
                        <th class="pr-4 pb-3 font-bold">Status</th>
                        <th class="pr-4 pb-3 font-bold">Reservasi</th>
                        <th class="pr-4 pb-3 font-bold">Terdaftar</th>
                        <th class="pb-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarPelanggan as $item)
                        <tr class="border-b border-[#eee9e1] last:border-0">
                            <td class="py-3 pr-4 font-extrabold">{{ $item->nama }}</td>
                            <td class="py-3 pr-4 text-ink-2">{{ $item->email }}</td>
                            <td class="py-3 pr-4 tabular-nums">{{ $item->no_hp }}</td>
                            <td class="py-3 pr-4">
                                <span @class([$item->status_akun === 'aktif' ? 'chip-aktif' : 'chip-nonaktif'])>{{ $item->status_akun === 'aktif' ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td class="py-3 pr-4 tabular-nums">{{ $item->reservasi_count }}<span class="sr-only"> reservasi</span></td>
                            <td class="py-3 pr-4 tabular-nums whitespace-nowrap">
                                {{ $item->reservasi_min_tanggal_dibuat ? \Illuminate\Support\Carbon::parse($item->reservasi_min_tanggal_dibuat)->translatedFormat('d M, H.i') : '—' }}
                            </td>
                            <td class="py-3">
                                <div class="flex justify-end">
                                    <a class="rounded-full border-2 border-ink bg-white px-4 py-1.5 text-xs font-bold shadow-[2px_2px_0_#252525]" href="{{ route('admin.pelanggan.detail', $item) }}">Detail</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-8 text-center text-ink-2" colspan="7">Pelanggan tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5 flex items-center justify-between gap-4 text-sm text-ink-2">
            <span>{{ $daftarPelanggan->total() }} data</span>
            <div class="flex items-center gap-2">
                @if ($daftarPelanggan->onFirstPage())
                    <span class="rounded-full border-2 border-[#ded6c7] px-4 py-1.5 text-[#a89f92]">Sebelumnya</span>
                @else
                    <a class="rounded-full border-2 border-ink px-4 py-1.5 font-bold text-ink hover:bg-paper-2" href="{{ $daftarPelanggan->previousPageUrl() }}">Sebelumnya</a>
                @endif
                <span class="tabular-nums"><strong class="text-ink">{{ $daftarPelanggan->currentPage() }}</strong> / {{ $daftarPelanggan->lastPage() }}</span>
                @if ($daftarPelanggan->hasMorePages())
                    <a class="rounded-full border-2 border-ink px-4 py-1.5 font-bold text-ink hover:bg-paper-2" href="{{ $daftarPelanggan->nextPageUrl() }}">Berikutnya</a>
                @else
                    <span class="rounded-full border-2 border-[#ded6c7] px-4 py-1.5 text-[#a89f92]">Berikutnya</span>
                @endif
            </div>
        </div>
    </section>
@endsection

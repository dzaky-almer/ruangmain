@extends('layouts.admin')

@section('title', 'Manajemen Room — RUANGMAIN')

@section('content')
    @php
        $labelStatus = ['tersedia' => 'Tersedia', 'dibooking' => 'Dibooking', 'digunakan' => 'Digunakan', 'tidak_aktif' => 'Tidak aktif'];
        $kelasStatus = ['tersedia' => 'chip-aktif', 'tidak_aktif' => 'chip-nonaktif', 'dibooking' => 'chip-status border-warn text-warn', 'digunakan' => 'chip-status border-info text-info'];
        $labelReservasi = ['menunggu_pembayaran' => 'Menunggu', 'dikonfirmasi' => 'Terjadwal', 'check_in' => 'Berlangsung', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
        $kelasReservasi = ['dikonfirmasi' => 'border-info text-info', 'menunggu_pembayaran' => 'border-warn text-warn', 'check_in' => 'border-ok text-ok', 'selesai' => 'border-[#b4aca0] text-ink-2', 'dibatalkan' => 'border-bad text-bad'];
        $jam = fn ($nilai) => str_replace(':', '.', substr((string) $nilai, 0, 5));
    @endphp

    <section>
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="font-script text-xl text-ink-2">ruang main</p>
                <h1 class="mt-2 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Room</h1>
                <div class="mt-4 h-1.5 w-28 -rotate-1 bg-sun"></div>
            </div>
            <button class="btn-primary" type="button" onclick="document.getElementById('tambah-room').showModal()">+ Tambah room</button>
        </div>

        @if ($errors->any())
            <div class="alert-error mt-6" role="alert">
                <strong>Periksa kembali isian.</strong>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $pesan)
                        <li>{{ $pesan }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form class="mt-6 flex flex-wrap items-end gap-3" method="GET">
            <div><label class="label-sketch" for="cari">Cari room</label><input class="field-sketch" id="cari" name="cari" value="{{ request('cari') }}" placeholder="Nama room..."></div>
            <div><label class="label-sketch" for="fasilitas">Fasilitas</label><select class="field-sketch" id="fasilitas" name="fasilitas"><option value="">Semua fasilitas</option>@foreach ($semuaFasilitas as $item)<option value="{{ $item->id_fasilitas }}" @selected((string) request('fasilitas') === (string) $item->id_fasilitas)>{{ $item->nama_fasilitas }}</option>@endforeach</select></div>
            <div><label class="label-sketch" for="status">Status operasional</label><select class="field-sketch" id="status" name="status"><option value="">Semua status</option>@foreach ($labelStatus as $nilai => $label)<option value="{{ $nilai }}" @selected(request('status') === $nilai)>{{ $label }}</option>@endforeach</select></div>
            <button class="btn-secondary" type="submit">Terapkan</button>
        </form>

        <div class="relative mt-6 overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="border-b-2 border-ink text-xs uppercase tracking-[0.1em] text-ink-2">
                    <tr>
                        <th class="pr-4 pb-3 font-bold">Room</th>
                        <th class="pr-4 pb-3 font-bold">Fasilitas</th>
                        <th class="pr-4 pb-3 font-bold">Harga / jam</th>
                        <th class="pr-4 pb-3 font-bold">Status operasional</th>
                        <th class="pr-4 pb-3 font-bold">Sedang dipakai</th>
                        <th class="pb-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($room as $item)
                        @php $dipakai = $item->detailReservasi->first(fn ($detail) => $detail->waktu_checkin && ! $detail->waktu_checkout); @endphp
                        <tr class="border-b border-[#eee9e1] last:border-0">
                            <td class="py-3 pr-4">
                                <span class="block font-extrabold">{{ $item->nama_room }}</span>
                                <span class="text-xs text-ink-2">maks {{ $item->kapasitas }} orang</span>
                            </td>
                            <td class="py-3 pr-4">{{ $item->fasilitas->nama_fasilitas }}</td>
                            <td class="py-3 pr-4 tabular-nums">
                                <span class="font-bold">Rp{{ number_format($item->hargaEfektif(), 0, ',', '.') }}</span>/jam
                            </td>
                            <td class="py-3 pr-4"><span @class([$kelasStatus[$item->status_room] ?? 'chip-status'])>{{ $labelStatus[$item->status_room] ?? $item->status_room }}</span></td>
                            <td class="py-3 pr-4">{{ $dipakai ? $dipakai->reservasi->kode_booking : '—' }}</td>
                            <td class="py-3">
                                <div class="flex justify-end gap-2">
                                    <button class="rounded-full border-2 border-ink bg-white px-4 py-1.5 text-xs font-bold shadow-[2px_2px_0_#252525]" type="button" onclick="document.getElementById('detail-{{ $item->id_room }}').showModal()">Detail</button>
                                    <button class="rounded-full border-2 border-ink bg-sun px-4 py-1.5 text-xs font-bold shadow-[2px_2px_0_#252525]" type="button" onclick="document.getElementById('edit-{{ $item->id_room }}').showModal()">Edit</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-8 text-center text-ink-2" colspan="6">Room tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5 flex items-center justify-between gap-4 text-sm text-ink-2">
            <span>{{ $room->total() }} data</span>
            <div class="flex items-center gap-2">
                @if ($room->onFirstPage())
                    <span class="rounded-full border-2 border-[#ded6c7] px-4 py-1.5 text-[#a89f92]">Sebelumnya</span>
                @else
                    <a class="rounded-full border-2 border-ink px-4 py-1.5 font-bold text-ink hover:bg-paper-2" href="{{ $room->previousPageUrl() }}">Sebelumnya</a>
                @endif
                <span class="tabular-nums"><strong class="text-ink">{{ $room->currentPage() }}</strong> / {{ $room->lastPage() }}</span>
                @if ($room->hasMorePages())
                    <a class="rounded-full border-2 border-ink px-4 py-1.5 font-bold text-ink hover:bg-paper-2" href="{{ $room->nextPageUrl() }}">Berikutnya</a>
                @else
                    <span class="rounded-full border-2 border-[#ded6c7] px-4 py-1.5 text-[#a89f92]">Berikutnya</span>
                @endif
            </div>
        </div>

        <dialog id="tambah-room" class="m-auto w-[min(480px,calc(100%-2rem))] rounded-[22px] border-2 border-ink bg-white p-5 text-ink shadow-[6px_6px_0_#252525] backdrop:bg-ink/40">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-xl font-extrabold">Tambah room</h2>
                <button class="text-2xl leading-none text-ink-2 hover:text-ink" type="button" aria-label="Tutup form tambah" onclick="this.closest('dialog').close()">×</button>
            </div>
            <form class="mt-4" action="{{ route('admin.room.store') }}" method="POST">
                @csrf
                <input type="hidden" name="_form" value="tambah">
                @include('admin.room._field', ['room' => new App\Models\Room, 'daftarFasilitas' => $semuaFasilitas, 'awalan' => 'baru'])
                <div class="mt-6 flex justify-end gap-3">
                    <button class="btn-secondary" type="button" onclick="this.closest('dialog').close()">Batal</button>
                    <button class="btn-primary" type="submit">Simpan</button>
                </div>
            </form>
        </dialog>

        @foreach ($room as $item)
            <dialog id="detail-{{ $item->id_room }}" class="m-auto max-h-[90vh] w-[min(560px,calc(100%-2rem))] overflow-y-auto rounded-[22px] border-2 border-ink bg-white p-5 text-ink shadow-[6px_6px_0_#252525] backdrop:bg-ink/40">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-extrabold">{{ $item->nama_room }}</h2>
                    <button class="text-2xl leading-none text-ink-2 hover:text-ink" type="button" aria-label="Tutup detail" onclick="this.closest('dialog').close()">×</button>
                </div>
                <p class="mt-4 flex items-center gap-3 text-sm">
                    <span @class([$kelasStatus[$item->status_room] ?? 'chip-status'])>{{ $labelStatus[$item->status_room] ?? $item->status_room }}</span>
                    <span>{{ $item->fasilitas->nama_fasilitas }}</span>
                </p>
                <h3 class="mt-6 font-extrabold">Jadwal reservasi &amp; penggunaan</h3>
                <div class="mt-2 divide-y divide-[#eee9e1] text-sm">
                    @forelse ($item->detailReservasi->sortByDesc('tanggal')->take(6) as $detail)
                        <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <span class="font-bold">{{ $detail->reservasi->kode_booking }}</span>
                            <span class="text-ink-2">{{ $detail->tanggal?->translatedFormat('D, d M Y') }}</span>
                            <span class="tabular-nums">{{ $jam($detail->jam_mulai) }}–{{ $jam($detail->jam_selesai) }}</span>
                            <span @class(['chip-status', $kelasReservasi[$detail->reservasi->status_reservasi] ?? 'border-[#b4aca0] text-ink-2'])>{{ $labelReservasi[$detail->reservasi->status_reservasi] ?? $detail->reservasi->status_reservasi }}</span>
                        </div>
                    @empty
                        <p class="py-3 text-ink-2">Belum ada reservasi untuk room ini.</p>
                    @endforelse
                </div>
                <p class="mt-4 text-xs text-ink-2">Status operasional bukan ketersediaan interval reservasi.</p>
            </dialog>

            <dialog id="edit-{{ $item->id_room }}" class="m-auto w-[min(480px,calc(100%-2rem))] rounded-[22px] border-2 border-ink bg-white p-5 text-ink shadow-[6px_6px_0_#252525] backdrop:bg-ink/40">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-extrabold">Edit room</h2>
                    <button class="text-2xl leading-none text-ink-2 hover:text-ink" type="button" aria-label="Tutup form edit" onclick="this.closest('dialog').close()">×</button>
                </div>
                <form class="mt-4" action="{{ route('admin.room.update', $item) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('admin.room._field', ['room' => $item, 'daftarFasilitas' => $semuaFasilitas, 'awalan' => 'edit-'.$item->id_room])
                    <div class="mt-6 flex justify-end gap-3">
                        <button class="btn-secondary" type="button" onclick="this.closest('dialog').close()">Batal</button>
                        <button class="btn-primary" type="submit">Simpan</button>
                    </div>
                </form>

                <form class="mt-4 border-t-2 border-[#eee9e1] pt-4" action="{{ route('admin.room.destroy', $item) }}" method="POST" onsubmit="return confirm('Hapus room ini?');">
                    @csrf
                    @method('DELETE')
                    <p class="text-xs text-ink-2">Room yang sudah memiliki reservasi tidak dapat dihapus.</p>
                    <button class="btn-danger mt-2" type="submit">Hapus Room</button>
                </form>
            </dialog>
        @endforeach
    </section>

    @if (old('_form') === 'tambah')
        <script>document.getElementById('tambah-room').showModal()</script>
    @endif
@endsection
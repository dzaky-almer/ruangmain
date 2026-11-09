@extends('layouts.admin')

@section('title', 'Fasilitas — RUANGMAIN')

@section('content')
    <section>
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="font-script text-xl text-ink-2">katalog</p>
                <h1 class="mt-2 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Fasilitas</h1>
                <div class="mt-4 h-1.5 w-44 -rotate-1 bg-sun"></div>
            </div>
            <button class="btn-primary" type="button" onclick="document.getElementById('tambah-fasilitas').showModal()">+ Tambah fasilitas</button>
        </div>

                    <dialog id="tambah-fasilitas" class="m-auto max-h-[90vh] w-[min(620px,calc(100%-2rem))] overflow-y-auto rounded-[22px] border-2 border-ink bg-white p-5 text-ink shadow-[6px_6px_0_#252525] backdrop:bg-ink/40">
                        <div class="flex items-start justify-between gap-4">
                            <h2 class="text-xl font-extrabold">Tambah fasilitas</h2>
                            <button class="text-2xl leading-none text-ink-2 hover:text-ink" type="button" aria-label="Tutup form tambah" onclick="this.closest('dialog').close()">×</button>
                        </div>
                        <form class="mt-5" action="{{ route('admin.fasilitas.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="_form" value="tambah">
                            <div class="grid gap-4">
                                                            <div>
                                                                <label class="label-sketch" for="nama-baru">Nama</label>
                                                                <input class="field-sketch" id="nama-baru" name="nama_fasilitas" value="{{ old('nama_fasilitas') }}" maxlength="50" required>
                                                            </div>
                                                        </div>
                            <div class="mt-4">
                                <label class="label-sketch" for="tagline-baru">Tagline</label>
                                <input class="field-sketch" id="tagline-baru" name="tagline" value="{{ old('tagline') }}" maxlength="100">
                            </div>
                            <div class="mt-4">
                                <label class="label-sketch" for="deskripsi-baru">Deskripsi</label>
                                <textarea class="field-sketch min-h-28" id="deskripsi-baru" name="deskripsi" required>{{ old('deskripsi') }}</textarea>
                            </div>
                            <div class="mt-4">
                                <label class="label-sketch" for="foto-url-baru">Foto (URL atau unggah PNG/JPG/WEBP)</label>
                                <input class="field-sketch" id="foto-url-baru" name="foto_url" type="url" value="{{ old('foto_url') }}" placeholder="https://...">
                                <input class="mt-2 block w-full text-xs text-ink-2 file:mr-3 file:rounded-full file:border-2 file:border-ink file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-ink" id="foto-baru" name="foto" type="file" accept="image/png,image/jpeg,image/webp">
                            </div>
                            <div class="mt-5">
                                <input type="hidden" name="status_fasilitas" value="nonaktif">
                                <label class="flex items-center gap-2 text-sm font-bold" for="aktif-baru">
                                    <input class="h-4 w-4 accent-ink" id="aktif-baru" name="status_fasilitas" type="checkbox" value="aktif" @checked(old('status_fasilitas', 'aktif') === 'aktif')>
                                    Aktif (fasilitas nonaktif tidak bisa dipesan)
                                </label>
                            </div>
                            <div class="mt-6 flex justify-end gap-3">
                                <button class="btn-secondary" type="button" onclick="this.closest('dialog').close()">Batal</button>
                                <button class="btn-primary" type="submit">Simpan</button>
                            </div>
                        </form>
                    </dialog>

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

        <form class="mt-6" method="GET">
            <div class="relative max-w-xs">
                <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="m20 20-3.5-3.5"></path>
                </svg>
                <input class="min-h-11 w-full rounded-full border-2 border-ink bg-white py-2.5 pr-4 pl-9 text-sm outline-none placeholder:text-ink-2 focus:ring-3 focus:ring-info/25" name="cari" value="{{ request('cari') }}" placeholder="Cari fasilitas..." aria-label="Cari fasilitas">
            </div>
        </form>

        <div class="relative mt-6 overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b-2 border-ink text-xs text-ink-2 uppercase">
                    <tr>
                        <th class="pr-4 pb-3 font-bold">Foto</th>
                        <th class="pr-4 pb-3 font-bold">Nama</th>
                                                <th class="pr-4 pb-3 font-bold">Room</th>
                        <th class="pr-4 pb-3 font-bold">Status</th>
                        <th class="pb-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fasilitas as $item)
                        <tr class="border-b border-[#eee9e1] last:border-0">
                            <td class="py-3 pr-4">
                                <img class="h-12 w-16 rounded-xl border-2 border-ink object-cover" src="{{ $item->gambar() }}" alt="Foto {{ $item->nama_fasilitas }}">
                            </td>
                            <td class="py-3 pr-4 font-extrabold">{{ $item->nama_fasilitas }}</td>
                                                        <td class="py-3 pr-4">{{ $item->room_count }}</td>
                            <td class="py-3 pr-4"><span @class([$item->status_fasilitas === 'aktif' ? 'chip-aktif' : 'chip-nonaktif'])>{{ ucfirst($item->status_fasilitas) }}</span></td>
                            <td class="py-3">
                                <div class="flex justify-end gap-2">
                                    <button class="rounded-full border-2 border-ink bg-white px-4 py-1.5 text-xs font-bold shadow-[2px_2px_0_#252525]" type="button" onclick="document.getElementById('detail-{{ $item->id_fasilitas }}').showModal()">Detail</button>
                                    <button class="rounded-full border-2 border-ink bg-sun px-4 py-1.5 text-xs font-bold shadow-[2px_2px_0_#252525]" type="button" onclick="document.getElementById('edit-{{ $item->id_fasilitas }}').showModal()">Edit</button>
                                </div>

                                <dialog id="detail-{{ $item->id_fasilitas }}" class="m-auto w-[min(430px,calc(100%-2rem))] rounded-[22px] border-2 border-ink bg-white p-5 text-ink shadow-[6px_6px_0_#252525] backdrop:bg-ink/40">
                                    <div class="flex items-start justify-between gap-4">
                                        <h2 class="text-xl font-extrabold">{{ $item->nama_fasilitas }}</h2>
                                        <button class="text-2xl leading-none text-ink-2 hover:text-ink" type="button" aria-label="Tutup detail" onclick="this.closest('dialog').close()">×</button>
                                    </div>
                                    <div class="mt-4 aspect-video overflow-hidden rounded-2xl border-2 border-ink bg-sky">
                                        <img class="h-full w-full object-cover" src="{{ $item->gambar() }}" alt="Foto {{ $item->nama_fasilitas }}">
                                    </div>
                                    <p class="mt-4 text-sm">{{ $item->deskripsi ?: 'Belum ada deskripsi.' }}</p>
                                    <dl class="mt-4 text-sm">
                                                                            <div class="flex items-baseline justify-between gap-4 py-3">
                                                                                <dt class="text-ink-2">Room</dt>
                                            <dd class="text-right font-bold">{{ $item->room->pluck('nama_room')->join(', ') ?: 'Belum ada room' }}</dd>
                                        </div>
                                    </dl>
                                    <p class="mt-2 flex gap-2 rounded-xl bg-[#e1edff] px-4 py-3 text-sm">
                                        <span class="font-extrabold" aria-hidden="true">i</span>
                                                                            <span>Harga per jam diatur per room (Reguler/VIP/VVIP), bukan per fasilitas.</span>
                                    </p>
                                </dialog>

                                <dialog id="edit-{{ $item->id_fasilitas }}" class="m-auto max-h-[90vh] w-[min(620px,calc(100%-2rem))] overflow-y-auto rounded-[22px] border-2 border-ink bg-white p-5 text-ink shadow-[6px_6px_0_#252525] backdrop:bg-ink/40">
                                    <div class="flex items-start justify-between gap-4">
                                        <h2 class="text-xl font-extrabold">Edit fasilitas</h2>
                                        <button class="text-2xl leading-none text-ink-2 hover:text-ink" type="button" aria-label="Tutup form edit" onclick="this.closest('dialog').close()">×</button>
                                    </div>
                                    <form class="mt-5" action="{{ route('admin.fasilitas.update', $item) }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        @method('PUT')
                                        <div class="grid gap-4 sm:grid-cols-2">
                                                                                    <div>
                                                                                        <label class="label-sketch" for="nama-{{ $item->id_fasilitas }}">Nama</label>
                                                                                        <input class="field-sketch" id="nama-{{ $item->id_fasilitas }}" name="nama_fasilitas" value="{{ $item->nama_fasilitas }}" maxlength="50" required>
                                                                                    </div>
                                                                                </div>
                                        <div class="mt-4">
                                                                                    <label class="label-sketch" for="tagline-{{ $item->id_fasilitas }}">Tagline</label>
                                                                                    <input class="field-sketch" id="tagline-{{ $item->id_fasilitas }}" name="tagline" value="{{ $item->tagline }}" maxlength="100">
                                        </div>
                                        <div class="mt-4">
                                                                                    <label class="label-sketch" for="deskripsi-{{ $item->id_fasilitas }}">Deskripsi</label>
                                                                                    <textarea class="field-sketch min-h-28" id="deskripsi-{{ $item->id_fasilitas }}" name="deskripsi" required>{{ $item->deskripsi }}</textarea>
                                        </div>
                                                                                <div class="mt-4">
                                                                                    <label class="label-sketch" for="foto-url-{{ $item->id_fasilitas }}">Foto (URL atau unggah PNG/JPG/WEBP)</label>
                                                                                    <input class="field-sketch" id="foto-url-{{ $item->id_fasilitas }}" name="foto_url" type="url" value="{{ str_starts_with((string) $item->foto, 'http') ? $item->foto : '' }}" placeholder="https://...">
                                                                                    <input class="mt-2 block w-full text-xs text-ink-2 file:mr-3 file:rounded-full file:border-2 file:border-ink file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-ink" id="foto-{{ $item->id_fasilitas }}" name="foto" type="file" accept="image/png,image/jpeg,image/webp">
                                                                                </div>
                                        <div class="mt-5">
                                            <input type="hidden" name="status_fasilitas" value="nonaktif">
                                            <label class="flex items-center gap-2 text-sm font-bold" for="aktif-{{ $item->id_fasilitas }}">
                                                <input class="h-4 w-4 accent-ink" id="aktif-{{ $item->id_fasilitas }}" name="status_fasilitas" type="checkbox" value="aktif" @checked($item->status_fasilitas === 'aktif')>
                                                Aktif (fasilitas nonaktif tidak bisa dipesan)
                                            </label>
                                        </div>
                                        <div class="mt-6 flex justify-end gap-3">
                                            <button class="btn-secondary" type="button" onclick="this.closest('dialog').close()">Batal</button>
                                            <button class="btn-primary" type="submit">Simpan</button>
                                        </div>
                                    </form>

                                    <form class="mt-4 border-t-2 border-[#eee9e1] pt-4" action="{{ route('admin.fasilitas.destroy', $item) }}" method="POST" onsubmit="return confirm('Hapus fasilitas ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <p class="text-xs text-ink-2">Fasilitas yang masih dipakai room tidak dapat dihapus.</p>
                                        <button class="btn-danger mt-2" type="submit">Hapus Fasilitas</button>
                                    </form>
                                </dialog>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-8 text-center text-ink-2" colspan="5">Fasilitas tidak ditemukan. Coba ubah kata kunci pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-xs text-ink-2">{{ $fasilitas->count() }} data</p>
    </section>
            @if (old('_form') === 'tambah')
                <script>document.getElementById('tambah-fasilitas').showModal()</script>
            @endif
        @endsection
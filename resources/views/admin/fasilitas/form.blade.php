@extends('layouts.admin')

@section('title', ($fasilitas->exists ? 'Edit' : 'Tambah').' Fasilitas — RUANGMAIN')

@section('content')
    <section>
        <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / FASILITAS</p>
        <h1 class="mt-6 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">{{ $fasilitas->exists ? 'Form Fasilitas' : 'Tambah Fasilitas' }}</h1>
        <p class="mt-3 text-ink-2">{{ $fasilitas->exists ? 'Edit data '.$fasilitas->nama_fasilitas.'.' : 'Tambahkan kategori fasilitas baru untuk reservasi.' }}</p>
        <div class="mt-4 h-1.5 w-36 -rotate-1 bg-bubble"></div>

        <form class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]" action="{{ $fasilitas->exists ? route('admin.fasilitas.update', $fasilitas) : route('admin.fasilitas.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if ($fasilitas->exists) @method('PUT') @endif
            <div class="card-paper p-5 sm:p-6">
                <h2 class="text-xl font-extrabold">Informasi fasilitas</h2>
                <div class="mt-6 space-y-5">
                    <div>
                        <label class="label-sketch" for="nama_fasilitas">Nama fasilitas <span class="text-bad">*</span></label>
                        <input class="field-sketch @error('nama_fasilitas') !border-bad @enderror" id="nama_fasilitas" name="nama_fasilitas" value="{{ old('nama_fasilitas', $fasilitas->nama_fasilitas) }}" maxlength="50" required>
                        @error('nama_fasilitas') <p class="mt-2 text-sm font-semibold text-bad" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label-sketch" for="tagline">Tagline</label>
                        <input class="field-sketch @error('tagline') !border-bad @enderror" id="tagline" name="tagline" value="{{ old('tagline', $fasilitas->tagline) }}" maxlength="100">
                        <p class="mt-2 text-xs text-ink-2">Muncul sebagai kalimat pembuka di beranda.</p>
                        @error('tagline') <p class="mt-2 text-sm font-semibold text-bad" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label-sketch" for="deskripsi">Deskripsi <span class="text-bad">*</span></label>
                        <textarea class="field-sketch min-h-32 @error('deskripsi') !border-bad @enderror" id="deskripsi" name="deskripsi" required>{{ old('deskripsi', $fasilitas->deskripsi) }}</textarea>
                        @error('deskripsi') <p class="mt-2 text-sm font-semibold text-bad" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label-sketch" for="status_fasilitas">Status <span class="text-bad">*</span></label>
                        <select class="field-sketch" id="status_fasilitas" name="status_fasilitas" required>
                            <option value="aktif" @selected(old('status_fasilitas', $fasilitas->status_fasilitas ?: 'aktif') === 'aktif')>Aktif</option>
                            <option value="nonaktif" @selected(old('status_fasilitas', $fasilitas->status_fasilitas) === 'nonaktif')>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="alert-warning mt-6"><strong>Jika fasilitas dinonaktifkan.</strong> Fasilitas tidak dapat digunakan untuk reservasi baru. Tinjau reservasi aktif sebelum mengubah status.</div>
                <div class="mt-6 flex flex-wrap gap-3"><button class="btn-primary" type="submit">Simpan Fasilitas</button><a class="btn-secondary" href="{{ route('admin.fasilitas.index') }}">Kembali</a></div>
            </div>
            <aside class="space-y-5">
                <div class="card-paper overflow-hidden p-4">
                    <img class="aspect-[4/3] w-full rounded-2xl border-2 border-ink object-cover" src="{{ $fasilitas->gambar() }}" alt="Foto {{ $fasilitas->nama_fasilitas }}">
                    <h2 class="mt-5 text-xl font-extrabold">Foto fasilitas</h2>
                    <label class="label-sketch" for="foto_url">URL foto</label>
                    <input class="field-sketch" id="foto_url" name="foto_url" type="url" value="{{ old('foto_url', str_starts_with((string) $fasilitas->foto, 'http') ? $fasilitas->foto : '') }}" placeholder="https://...">
                    @error('foto_url') <p class="mt-2 text-sm font-semibold text-bad" role="alert">{{ $message }}</p> @enderror
                    <label class="btn-secondary mt-4 cursor-pointer" for="foto">Pilih Foto PNG/JPG/WEBP</label>
                    <input class="sr-only" id="foto" name="foto" type="file" accept="image/png,image/jpeg,image/webp">
                    <p class="mt-3 text-xs text-ink-2">PNG, JPG, atau WebP · Maksimal 2 MB. Unggahan diprioritaskan daripada URL.</p>
                    @error('foto') <p class="mt-2 text-sm font-semibold text-bad" role="alert">{{ $message }}</p> @enderror
                </div>
            </aside>
        </form>
        @if ($fasilitas->exists)
            <div class="card-soft mt-6 max-w-xl p-4">
                <h2 class="font-extrabold">Hapus fasilitas</h2>
                <p class="mt-2 text-sm text-ink-2">Fasilitas yang masih dipakai room tidak dapat dihapus.</p>
                <form class="mt-4" action="{{ route('admin.fasilitas.destroy', $fasilitas) }}" method="POST" onsubmit="return confirm('Hapus fasilitas ini?');">
                    @csrf @method('DELETE')
                    <button class="btn-danger" type="submit">Hapus Fasilitas</button>
                </form>
            </div>
        @endif
    </section>
@endsection

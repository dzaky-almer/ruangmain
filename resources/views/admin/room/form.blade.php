@extends('layouts.admin')

@section('title', ($room->exists ? 'Edit' : 'Tambah').' Room — RUANGMAIN')

@section('content')
    <section>
        <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / ROOM</p>
        <h1 class="mt-6 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">{{ $room->exists ? 'Form & Detail Room' : 'Tambah Room' }}</h1>
        <p class="mt-3 text-ink-2">Status operasional bukan ketersediaan interval reservasi.</p><div class="mt-4 h-1.5 w-36 -rotate-1 bg-bubble"></div>
        <form class="card-paper mt-6 max-w-3xl p-5 sm:p-6" action="{{ $room->exists ? route('admin.room.update', $room) : route('admin.room.store') }}" method="POST">
            @csrf @if ($room->exists) @method('PUT') @endif
            <h2 class="text-xl font-extrabold">{{ $room->exists ? 'Edit '.$room->nama_room : 'Informasi room' }}</h2>
            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div><label class="label-sketch" for="nama_room">Nama room <span class="text-bad">*</span></label><input class="field-sketch @error('nama_room') !border-bad @enderror" id="nama_room" name="nama_room" value="{{ old('nama_room', $room->nama_room) }}" maxlength="50" required>@error('nama_room')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror</div>
                <div><label class="label-sketch" for="kapasitas">Kapasitas <span class="text-bad">*</span></label><input class="field-sketch @error('kapasitas') !border-bad @enderror" id="kapasitas" name="kapasitas" type="number" min="1" value="{{ old('kapasitas', $room->kapasitas) }}" required>@error('kapasitas')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror</div>
                <div><label class="label-sketch" for="harga_per_jam">Harga per jam <span class="text-bad">*</span></label><input class="field-sketch @error('harga_per_jam') !border-bad @enderror" id="harga_per_jam" name="harga_per_jam" type="number" min="1" step="any" value="{{ old('harga_per_jam', $room->harga_per_jam) }}" placeholder="20000" required>@error('harga_per_jam')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror</div>
                <div><label class="label-sketch" for="id_fasilitas">Fasilitas <span class="text-bad">*</span></label><select class="field-sketch" id="id_fasilitas" name="id_fasilitas" required><option value="">Pilih fasilitas</option>@foreach ($fasilitas as $item)<option value="{{ $item->id_fasilitas }}" @selected((string) old('id_fasilitas', $room->id_fasilitas) === (string) $item->id_fasilitas)>{{ $item->nama_fasilitas }}</option>@endforeach</select>@error('id_fasilitas')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror</div>
                <div><label class="label-sketch" for="status_room">Status operasional <span class="text-bad">*</span></label><select class="field-sketch" id="status_room" name="status_room" required>@foreach (['tersedia' => 'Tersedia secara operasional', 'dibooking' => 'Dibooking', 'digunakan' => 'Digunakan', 'tidak_aktif' => 'Tidak aktif'] as $nilai => $label)<option value="{{ $nilai }}" @selected(old('status_room', $room->status_room ?: 'tersedia') === $nilai)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <div class="alert-warning mt-6"><strong>Penonaktifan dibatasi.</strong> Tinjau reservasi aktif sebelum mengubah status room.</div>
            <div class="mt-6 flex flex-wrap gap-3"><button class="btn-primary" type="submit">Simpan Room</button><a class="btn-secondary" href="{{ route('admin.room.index') }}">Kembali</a></div>
        </form>
        @if ($room->exists)<div class="card-soft mt-6 max-w-3xl p-4"><h2 class="font-extrabold">Hapus room</h2><p class="mt-2 text-sm text-ink-2">Room yang sudah memiliki reservasi tidak dapat dihapus.</p><form class="mt-4" action="{{ route('admin.room.destroy', $room) }}" method="POST" onsubmit="return confirm('Hapus room ini?');">@csrf @method('DELETE')<button class="btn-danger" type="submit">Hapus Room</button></form></div>@endif
    </section>
@endsection

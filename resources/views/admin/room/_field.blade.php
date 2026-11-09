<div>
    <label class="label-sketch" for="nama-{{ $awalan }}">Nama room</label>
    <input class="field-sketch @error('nama_room') !border-bad @enderror" id="nama-{{ $awalan }}" name="nama_room" value="{{ old('nama_room', $room->nama_room) }}" maxlength="50" required>
</div>
<div class="mt-4">
    <label class="label-sketch" for="fasilitas-{{ $awalan }}">Fasilitas</label>
    <select class="field-sketch @error('id_fasilitas') !border-bad @enderror" id="fasilitas-{{ $awalan }}" name="id_fasilitas" required>
        @foreach ($daftarFasilitas as $item)
            <option value="{{ $item->id_fasilitas }}" @selected((string) old('id_fasilitas', $room->id_fasilitas) === (string) $item->id_fasilitas)>{{ $item->nama_fasilitas }}</option>
        @endforeach
    </select>
    <p class="mt-1.5 text-xs text-ink-2">Setiap room hanya terhubung dengan satu fasilitas.</p>
</div>
<div class="mt-4">
    <label class="label-sketch" for="kapasitas-{{ $awalan }}">Kapasitas</label>
    <input class="field-sketch @error('kapasitas') !border-bad @enderror" id="kapasitas-{{ $awalan }}" name="kapasitas" type="number" min="1" value="{{ old('kapasitas', $room->kapasitas ?: 4) }}" required>
</div>
<div class="mt-4">
    <label class="label-sketch" for="harga-{{ $awalan }}">Harga per jam</label>
    <input class="field-sketch @error('harga_per_jam') !border-bad @enderror" id="harga-{{ $awalan }}" name="harga_per_jam" type="number" min="1" step="any" value="{{ old('harga_per_jam', $room->harga_per_jam) }}" placeholder="20000" required>
    <p class="mt-1.5 text-xs text-ink-2">Harga melekat di room, jadi Reguler/VIP/VVIP bisa beda harga.</p>
    @error('harga_per_jam') <p class="mt-2 text-sm font-semibold text-bad" role="alert">{{ $message }}</p> @enderror
</div>
<div class="mt-4">
    @if (in_array(old('status_room', $room->status_room), ['dibooking', 'digunakan'], true))
        <input type="hidden" name="status_room" value="{{ old('status_room', $room->status_room) }}">
        <p class="text-sm font-bold">Aktif secara operasional</p>
        <p class="mt-1.5 text-xs text-ink-2">Status <strong>{{ old('status_room', $room->status_room) }}</strong> dikelola otomatis lewat check-in/check-out.</p>
    @else
        <input type="hidden" name="status_room" value="tidak_aktif">
        <label class="flex items-center gap-2 text-sm font-bold" for="aktif-{{ $awalan }}">
            <input class="h-4 w-4 accent-ink" id="aktif-{{ $awalan }}" name="status_room" type="checkbox" value="tersedia" @checked(old('status_room', $room->status_room ?: 'tersedia') === 'tersedia')>
            Aktif secara operasional
        </label>
    @endif
</div>
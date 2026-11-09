@extends('layouts.app')
@section('title', 'Reservasi Baru — RUANGMAIN')
@section('content')
@php
    $formatJam = fn (string $jam): string => str_replace(':', '.', substr($jam, 0, 5));
    $pilihanRoom = $daftarFasilitas->flatMap(fn ($fasilitas) => $fasilitas->room->map(fn ($room) => [
        'id_room' => $room->id_room,
        'nama_room' => $room->nama_room,
        'nama_fasilitas' => $fasilitas->nama_fasilitas,
        'id_fasilitas' => $fasilitas->id_fasilitas,
        'harga' => $room->hargaEfektif(),
        'kapasitas' => $room->kapasitas,
        'kosong' => (int) ($slotKosong[$room->id_room] ?? 0),
    ]))->values();
    // Room yang datang dari detail fasilitas sudah dipilih, jadi dikunci dan form langsung ke langkah jadwal.
    $barisAwal = $terpilih->isNotEmpty()
        ? $terpilih->map(fn ($room) => ['id_room' => $room->id_room, 'jam_mulai' => $jamMulai, 'kunci' => true])
        : collect([['id_room' => $pilihanRoom->first()['id_room'] ?? null, 'jam_mulai' => $jamMulai]]);
    $langkah = ['1 · Room', '2 · Jadwal', '3 · Ringkasan', '4 · Konfirmasi'];
@endphp
<section class="mx-auto max-w-5xl px-5 py-8 sm:px-8">
    <p class="text-xs font-bold tracking-[.16em] text-ink-2">RUANGMAIN / PELANGGAN / RESERVASI BARU</p>
    <h1 class="mt-6 text-3xl font-extrabold tracking-[-.04em] sm:text-5xl">Reservasi Baru</h1>
    <p class="mt-3 text-ink-2" data-teks-langkah>Langkah 1 dari 4 · Pilih room yang mau kamu mainkan.</p>
    <div class="mt-4 h-1.5 w-36 -rotate-1 bg-bubble"></div>

    <ol class="mt-6 flex flex-wrap gap-2 sm:mt-8 text-sm font-bold">
        @foreach ($langkah as $label)
            <li class="rounded-full border-2 border-ink px-4 py-2 bg-paper-2" data-langkah-chip="{{ $loop->iteration }}">{{ $label }}</li>
        @endforeach
    </ol>

    @if ($pilihanRoom->isEmpty())
        <div class="alert-warning mt-8">Belum ada room aktif yang dapat dipesan.</div>
    @endif

    @if ($errors->any())
        <div class="alert-error mt-6" role="alert">
            <strong>Reservasi tidak dapat dibuat.</strong>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="mt-6 space-y-5 sm:mt-8 sm:space-y-6" method="POST" action="{{ route('pelanggan.reservasi.simpan') }}" data-form-reservasi data-maks-room="{{ \App\Services\ReservasiService::MAKS_ROOM }}">
        @csrf

        <div class="card-paper p-5 sm:p-6" data-wadah-item>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-extrabold" data-judul-panel>1 · Pilih Room</h2>
                <button class="btn-secondary !min-h-0 !px-4 !py-2" type="button" data-tambah-room>Tambah room</button>
            </div>
            <p class="mt-1 text-sm text-ink-2">Satu reservasi dapat memuat beberapa room. Maksimal {{ \App\Services\ReservasiService::MAKS_ROOM }} room. Pakai <strong class="text-ink">Tambah room</strong> untuk memesan room lain sekaligus.</p>

            <div class="mt-5 space-y-4" data-daftar-item></div>

            <template data-templat-item>
                <fieldset class="card-soft p-4" data-item>
                    <legend class="px-2 text-sm font-extrabold" data-judul-item>Room 1</legend>
                    <div data-bagian="room">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="label-sketch">Room</label>
                                <p class="hidden font-extrabold" data-room-terkunci>—</p>
                                <select class="field-sketch" data-pilih-room>
                                    @foreach ($daftarFasilitas as $fasilitas)
                                        @if ($fasilitas->room->isNotEmpty())
                                            <optgroup label="{{ $fasilitas->nama_fasilitas }}">
                                                @foreach ($fasilitas->room as $room)
                                                    @php $kosong = (int) ($slotKosong[$room->id_room] ?? 0); @endphp
                                                    <option value="{{ $room->id_room }}" @disabled($kosong === 0)>{{ $room->nama_room }} · {{ $room->kapasitas }} orang · Rp{{ number_format($room->hargaEfektif(), 0, ',', '.') }} / jam{{ $kosong === 0 ? ' · penuh' : '' }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button class="mt-3 font-bold text-bad underline underline-offset-4" type="button" data-hapus-item>Hapus room ini</button>
                    </div>

                    <div data-bagian="jadwal">
                        <p class="text-sm font-extrabold" data-nama-room-jadwal>—</p>
                        <div class="mt-4">
                            <label class="label-sketch">Tanggal bermain</label>
                            <input class="field-sketch max-w-xs" data-pilih-tanggal type="date" value="{{ $tanggal }}" min="{{ now()->toDateString() }}">
                        </div>
                        <div class="mt-5 flex flex-wrap items-end justify-between gap-3">
                            <p class="text-sm font-extrabold">Pilih jam mulai</p>
                            <p class="text-sm font-bold tabular-nums text-ink-2" data-jam-terpilih>Belum dipilih</p>
                        </div>
                        <p class="mt-1 text-sm text-ink-2">Hijau masih kosong, merah muda sudah dipesan. Klik salah satu.</p>
                        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6" data-slot-grid-item></div>
                        <div class="mt-5">
                            <label class="label-sketch">Durasi / jam</label>
                            <div class="flex flex-wrap gap-2" data-pilih-durasi>
                                @for ($jam = 1; $jam <= \App\Services\ReservasiService::MAKS_DURASI; $jam++)
                                    <button class="rounded-full border-2 border-ink px-4 py-2 font-bold tabular-nums {{ $jam === 2 ? 'bg-sun' : 'bg-paper-2' }}" type="button" data-durasi="{{ $jam }}">{{ $jam }}</button>
                                @endfor
                            </div>
                        </div>
                        <p class="mt-4 text-sm text-ink-2">Jam selesai otomatis: <strong class="tabular-nums" data-jam-selesai>—</strong> · Subtotal: <strong class="tabular-nums" data-subtotal-item>Rp0</strong></p>
                        <input type="hidden" data-pilih-mulai value="{{ $jamMulai }}">
                    </div>

                    <input type="hidden" data-input-id_room name="items[0][id_room]">
                    <input type="hidden" data-input-tanggal name="items[0][tanggal]">
                    <input type="hidden" data-input-jam_mulai name="items[0][jam_mulai]">
                    <input type="hidden" data-input-durasi name="items[0][durasi]">
                </fieldset>
            </template>
        </div>

        <div class="card-paper p-5 sm:p-6" data-panel-langkah="2">
            <h2 class="text-xl font-extrabold">2 · Pilih Jadwal &amp; Durasi</h2>
            <p class="mt-1 text-sm text-ink-2">Tanggal, jam mulai, dan durasi tiap room diatur di kartu atas.</p>
            <div class="mt-5 grid gap-6 lg:grid-cols-3">
                <div class="card-soft p-5 lg:col-span-2">
                    <p class="font-script text-lg text-ink-2">jadwalmu</p>
                    <p class="mt-1 text-2xl font-extrabold tabular-nums" data-panel-jadwal>—</p>
                    <p class="mt-1 text-sm text-ink-2" data-panel-tanggal>—</p>
                </div>
                <div class="lg:col-span-1 lg:pt-2">
                    <p class="text-sm font-bold">Catatan jam</p>
                                    <p class="mt-1 text-sm leading-6 text-ink-2">Operasional <strong class="text-ink">10.00–21.00</strong>. 21.00 adalah batas selesai, bukan jam mulai. Slot merah muda sudah dipesan room lain.</p>
                </div>
            </div>
        </div>

        <div class="card-paper p-5 sm:p-6" data-panel-langkah="3">
            <h2 class="text-xl font-extrabold">3 · Ringkasan &amp; Skema Bayar</h2>
            <table class="mt-5 w-full text-left text-sm">
                <thead class="border-b-2 border-ink text-xs uppercase tracking-[.12em] text-ink-2">
                    <tr><th class="py-3">Room</th><th class="py-3">Jadwal</th><th class="py-3">Durasi</th><th class="py-3">Harga / jam</th><th class="py-3">Subtotal</th></tr>
                </thead>
                <tbody data-ringkasan-baris>
                    <tr><td class="py-3 text-ink-2" colspan="5">Belum ada room dipilih.</td></tr>
                </tbody>
            </table>

            <p class="mt-6 text-sm font-bold">Pilih skema pembayaran</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <button class="rounded-full border-2 border-ink px-4 py-2 font-bold bg-sun" type="button" data-skema="dp">DP 50%</button>
                <button class="rounded-full border-2 border-ink px-4 py-2 font-bold bg-paper-2" type="button" data-skema="lunas">Lunas</button>
            </div>
            <p class="mt-2 text-xs text-ink-2">Skema ini hanya perkiraan. Jenis pembayaran dipilih di halaman pembayaran setelah reservasi dibuat.</p>

            <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-ink-2">Total tagihan</dt><dd class="text-2xl font-extrabold tabular-nums" data-total>Rp0</dd></div>
                <div><dt class="text-ink-2">DP sekarang</dt><dd class="text-2xl font-extrabold tabular-nums" data-dp-jumlah>Rp0</dd></div>
                <div><dt class="text-ink-2">Sisa pelunasan</dt><dd class="text-2xl font-extrabold tabular-nums" data-sisa-jumlah>Rp0</dd></div>
            </dl>
            <p class="mt-3 text-xs text-ink-2">Nominal selalu dihitung ulang di server.</p>
        </div>

        <div class="card-paper p-5 sm:p-6" data-panel-langkah="4">
            <h2 class="text-xl font-extrabold">4 · Konfirmasi Reservasi</h2>
            <p class="mt-1 text-sm text-ink-2">Periksa sekali lagi sebelum dikirim.</p>
            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-ink-2">Pelanggan</dt><dd class="font-bold">{{ auth()->user()->nama }}</dd></div>
                <div><dt class="text-ink-2">Email</dt><dd class="font-bold">{{ auth()->user()->email }}</dd></div>
                <div><dt class="text-ink-2">Room</dt><dd class="font-bold" data-konfirmasi-room>—</dd></div>
                <div><dt class="text-ink-2">Tanggal</dt><dd class="font-bold" data-konfirmasi-tanggal>—</dd></div>
                <div><dt class="text-ink-2">Waktu</dt><dd class="font-bold tabular-nums" data-konfirmasi-waktu>—</dd></div>
                <div><dt class="text-ink-2">Total</dt><dd class="text-xl font-extrabold tabular-nums" data-konfirmasi-total>Rp0</dd></div>
                <div><dt class="text-ink-2">Jumlah room</dt><dd class="font-bold tabular-nums" data-konfirmasi-jumlah>0</dd></div>
            </dl>
            <p class="mt-4 text-xs text-ink-2">Batas pembayaran: {{ now()->addMinutes(\App\Services\ReservasiService::MENIT_BATAS_PEMBAYARAN)->translatedFormat('d M Y, H.i') }} ({{ \App\Services\ReservasiService::MENIT_BATAS_PEMBAYARAN }} menit setelah reservasi dibuat).</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button class="btn-secondary" type="button" data-nav="kembali">Kembali</button>
            <button class="btn-primary" type="button" data-nav="lanjut">Lanjut</button>
            <button class="btn-primary" type="submit" data-nav="simpan" @disabled($pilihanRoom->isEmpty())>Konfirmasi Reservasi</button>
            <a class="btn-secondary" href="{{ route('pelanggan.katalog') }}">Batal</a>
        </div>
    </form>
</section>

<script type="application/json" data-konfigurasi-reservasi>{!! json_encode([
    'room' => $pilihanRoom,
    'awal' => $barisAwal,
    'maksRoom' => \App\Services\ReservasiService::MAKS_ROOM,
    'durasi' => $durasi,
    'mulaiLangkah' => $terpilih->isNotEmpty() ? 2 : 1,
    'jamTutup' => \App\Services\KetersediaanService::JAM_TUTUP,
    'jamBuka' => \App\Services\KetersediaanService::JAM_BUKA,
    'url' => route('pelanggan.ketersediaan'),
    'token' => csrf_token(),
], JSON_UNESCAPED_UNICODE) !!}</script>
<script>
    (() => {
        const konfigurasi = JSON.parse(document.querySelector('[data-konfigurasi-reservasi]').textContent);
        const petaRoom = Object.fromEntries(konfigurasi.room.map((room) => [String(room.id_room), room]));
        const form = document.querySelector('[data-form-reservasi]');
        const wadah = form.querySelector('[data-daftar-item]');
        const templat = form.querySelector('[data-templat-item]');
        const durasiBawaan = konfigurasi.durasi || 2;
        const judulLangkah = ['Pilih room yang mau kamu mainkan.', 'Atur tanggal, jam mulai, dan durasi.', 'Cek ringkasan dan perkiraan skema bayar.', 'Periksa sekali lagi sebelum dikirim.'];
        let langkah = 1;
        let totalTerakhir = 0;

        const rupiah = (nilai) => 'Rp' + Math.round(nilai).toLocaleString('id-ID');
        const formatTanggal = (nilai) => {
            const nama = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const [tahun, bulan, hari] = nilai.split('-');
            return `${parseInt(hari, 10)} ${nama[parseInt(bulan, 10) - 1]} ${tahun}`;
        };
        const formatJam = (jam) => jam.replace(':', '.');
        const jadwalSelesai = (mulai, durasi) => `${String(parseInt(mulai, 10) + durasi).padStart(2, '0')}:00`;
        const tampil = (elemen, terlihat) => { if (elemen) elemen.style.display = terlihat ? '' : 'none'; };

        const aturLangkah = (baru) => {
            langkah = Math.min(4, Math.max(1, baru));

            document.querySelectorAll('[data-langkah-chip]').forEach((chip) => {
                const aktif = Number(chip.dataset.langkahChip) === langkah;
                chip.classList.toggle('bg-sun', aktif);
                chip.classList.toggle('bg-paper-2', !aktif);
            });

            form.querySelectorAll('[data-panel-langkah]').forEach((panel) => {
                tampil(panel, Number(panel.dataset.panelLangkah) === langkah);
            });

            tampil(form.querySelector('[data-wadah-item]'), langkah <= 2);
            form.querySelectorAll('[data-bagian]').forEach((bagian) => {
                tampil(bagian, bagian.dataset.bagian === (langkah === 1 ? 'room' : 'jadwal'));
            });

            form.querySelector('[data-judul-panel]').textContent = langkah === 1 ? '1 · Pilih Room' : '2 · Pilih Jadwal & Durasi';
                        document.querySelector('[data-teks-langkah]').textContent = `Langkah ${langkah} dari 4 · ${judulLangkah[langkah - 1]}`;
            tampil(form.querySelector('[data-nav="kembali"]'), langkah > 1);
            tampil(form.querySelector('[data-nav="lanjut"]'), langkah < 4);
            tampil(form.querySelector('[data-nav="simpan"]'), langkah === 4);
        };

        // Jam mulai tidak boleh melewati (jam tutup - durasi) agar jam selesai tetap valid.
        const batasiMulai = (simpul) => {
            const batas = konfigurasi.jamTutup - parseInt(simpul.dataset.durasi, 10);
            const input = simpul.querySelector('[data-pilih-mulai]');
            if (parseInt(input.value, 10) > batas) input.value = `${String(batas).padStart(2, '0')}:00`;
        };

        const tandaiRoomPenuh = (slotKosong = {}) => {
            wadah.querySelectorAll('[data-pilih-room]').forEach((pilih) => {
                if (pilih.disabled) return;

                [...pilih.options].forEach((opsi) => {
                    const kosong = slotKosong[opsi.value];
                    if (kosong !== undefined) opsi.disabled = Number(kosong) === 0;
                });

                // Room terpilih bisa jadi penuh setelah tanggal diganti: pindah ke room yang masih ada slot.
                if (pilih.selectedOptions[0]?.disabled) {
                    const lain = [...pilih.options].find((opsi) => !opsi.disabled);
                    if (lain) pilih.value = lain.value;
                }
            });
        };

        // Jam terdekat yang masih bisa dipesan: untuk hari ini, jam yang sudah berjalan dianggap lewat.
        const jamTerdekat = (tanggal) => {
            const sekarang = new Date();
            const hariIni = `${sekarang.getFullYear()}-${String(sekarang.getMonth() + 1).padStart(2, '0')}-${String(sekarang.getDate()).padStart(2, '0')}`;
            return tanggal === hariIni ? Math.max(konfigurasi.jamBuka, sekarang.getHours() + 1) : konfigurasi.jamBuka;
        };

        const jamKe = (nilai) => `${String(nilai).padStart(2, '0')}:00`;

        // Satu jam mulai baru bisa dipilih kalau seluruh rentang durasinya kosong,
        // jadi slot yang menempel dengan jadwal terpesan ikut dimatikan — tetap tampil.
        const bolehMulai = (status, jam, durasi, batasBawah, batasAtas) => {
            const awal = parseInt(jam, 10);
            if (awal < batasBawah || awal > batasAtas) return false;

            for (let langkah = 0; langkah < durasi; langkah++) {
                if (status[jamKe(awal + langkah)] !== 'tersedia') return false;
            }

            return true;
        };

        const segarkanSlot = async (simpul) => {
            const grid = simpul.querySelector('[data-slot-grid-item]');
            const idRoom = simpul.querySelector('[data-pilih-room]').value;
            const tanggal = simpul.querySelector('[data-pilih-tanggal]').value;
            const durasi = parseInt(simpul.dataset.durasi, 10);
            const batasMulai = konfigurasi.jamTutup - durasi;
            const inputMulai = simpul.querySelector('[data-pilih-mulai]');
            const terdekat = jamTerdekat(tanggal);
            // Jam yang sudah lewat tidak boleh dipakai query, jadi pakai batas atas supaya endpoint tetap valid.
            const kueri = Math.min(terdekat, batasMulai);

            const badan = new URLSearchParams({
                id_room: idRoom,
                tanggal,
                jam_mulai: jamKe(kueri),
                jam_selesai: jadwalSelesai(jamKe(kueri), durasi),
            });

            try {
                const respons = await fetch(konfigurasi.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': konfigurasi.token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: badan,
                });
                const data = await respons.json();
                const status = data.status_slot ?? {};

                tandaiRoomPenuh(data.slot_kosong);

                // Tanggal baru bisa membuat room terpilih penuh; ambil ulang slot untuk room pengganti.
                if (simpul.querySelector('[data-pilih-room]').value !== idRoom) {
                    perbarui();
                    return segarkanSlot(simpul);
                }

                const jamAwal = Number(data.jamAwal ?? konfigurasi.jamBuka);
                const boleh = (jam) => bolehMulai(status, jam, durasi, jamAwal, batasMulai);
                const daftarJam = Object.keys(status).filter((jam) => parseInt(jam, 10) <= batasMulai);

                // Jam pilihan sebelumnya bisa jadi tidak muat lagi (durasi berubah / sudah terisi):
                // kosongkan supaya form tidak mengirim jam yang pasti ditolak server.
                if (! boleh(inputMulai.value)) {
                    inputMulai.value = daftarJam.find(boleh) ?? '';
                }

                const mulai = inputMulai.value;

                grid.innerHTML = daftarJam
                    .map((jam) => {
                        // Jam yang sudah lewat atau tidak muat tetap tampil, tapi tidak bisa diklik.
                        const lewat = parseInt(jam, 10) < jamAwal;
                        const bebas = boleh(jam);
                        const kelas = lewat
                            ? 'border-ink/30 bg-paper-2 text-ink-2 line-through'
                            : (bebas ? 'bg-mint hover:bg-sun' : 'bg-bubble opacity-50 line-through');
                        const aktif = jam === mulai ? 'bg-sun shadow-[3px_3px_0_#252525] -translate-y-0.5' : '';
                        return `<button class="rounded-xl border-2 border-ink px-2 py-3 text-base font-extrabold tabular-nums transition ${kelas} ${aktif}" type="button" data-slot-item="${jam}" ${bebas ? '' : 'disabled'}>${formatJam(jam)}</button>`;
                    })
                    .join('') + (daftarJam.some(boleh) ? '' : '<p class="col-span-full text-xs font-bold text-bad">Tidak ada jam yang muat untuk durasi ini. Pilih durasi lebih pendek atau tanggal lain.</p>');

                perbarui();
            } catch (galat) {
                grid.innerHTML = '<p class="col-span-full text-xs text-bad">Gagal memuat slot. Ubah tanggal untuk mencoba lagi.</p>';
            }
        };

        const tambahItem = (awal = {}) => {
            if (wadah.children.length >= konfigurasi.maksRoom) return;

            const simpul = templat.content.firstElementChild.cloneNode(true);
            simpul.dataset.durasi = String(awal.durasi || durasiBawaan);
            wadah.appendChild(simpul);

            if (awal.id_room !== undefined) simpul.querySelector('[data-pilih-room]').value = String(awal.id_room);
            if (awal.jam_mulai) simpul.querySelector('[data-pilih-mulai]').value = awal.jam_mulai;

            // Room dari detail fasilitas sudah pasti: select diganti label, room lain tetap bisa ditambah.
            if (awal.kunci) {
                const pilih = simpul.querySelector('[data-pilih-room]');
                const room = petaRoom[String(awal.id_room)];
                const label = simpul.querySelector('[data-room-terkunci]');
                pilih.classList.add('hidden');
                pilih.disabled = true;
                if (room) {
                    label.textContent = `${room.nama_room} · ${room.nama_fasilitas} · ${rupiah(room.harga)} / jam`;
                    label.classList.remove('hidden');
                }
            }

            simpul.querySelectorAll('[data-durasi]').forEach((tombol) => {
                const aktif = tombol.dataset.durasi === simpul.dataset.durasi;
                tombol.classList.toggle('bg-sun', aktif);
                tombol.classList.toggle('bg-paper-2', !aktif);
                tombol.addEventListener('click', () => {
                    simpul.dataset.durasi = tombol.dataset.durasi;
                    simpul.querySelectorAll('[data-durasi]').forEach((lain) => {
                        const dipilih = lain === tombol;
                        lain.classList.toggle('bg-sun', dipilih);
                        lain.classList.toggle('bg-paper-2', !dipilih);
                    });
                    batasiMulai(simpul);
                    perbarui();
                    segarkanSlot(simpul);
                });
            });

            simpul.querySelector('[data-slot-grid-item]').addEventListener('click', (peristiwa) => {
                const tombol = peristiwa.target.closest('[data-slot-item]');
                if (! tombol || tombol.disabled) return;
                simpul.querySelector('[data-pilih-mulai]').value = `${tombol.dataset.slotItem}:00`.slice(0, 5);
                perbarui();
                segarkanSlot(simpul);
            });

            simpul.querySelector('[data-hapus-item]').addEventListener('click', () => {
                simpul.remove();
                if (wadah.children.length === 0) tambahItem();
                perbarui();
            });
            simpul.querySelector('[data-pilih-room]').addEventListener('change', () => { perbarui(); segarkanSlot(simpul); });
            simpul.querySelector('[data-pilih-tanggal]').addEventListener('change', () => { perbarui(); segarkanSlot(simpul); });

            simpul.querySelector('[data-bagian="jadwal"]').style.display = 'none';
            batasiMulai(simpul);
            perbarui();
            aturLangkah(langkah);
            segarkanSlot(simpul);
        };

        const barisAktif = () => [...wadah.children].map((simpul) => {
            const room = petaRoom[simpul.querySelector('[data-pilih-room]').value];
            const durasi = parseInt(simpul.dataset.durasi, 10);
            const mulai = simpul.querySelector('[data-pilih-mulai]').value;
            return { simpul, room, durasi, mulai, tanggal: simpul.querySelector('[data-pilih-tanggal]').value };
        });

        const perbarui = () => {
            // Nama field di-renumber setiap perubahan supaya payload POST rapi: items[0], items[1], ...
            let total = 0;
            const baris = barisAktif();

            baris.forEach(({ simpul, room, durasi, mulai, tanggal }, indeks) => {
                simpul.querySelector('[data-judul-item]').textContent = `Room ${indeks + 1}`;
                simpul.querySelector('[data-hapus-item]').hidden = baris.length <= 1;

                ['id_room', 'tanggal', 'jam_mulai', 'durasi'].forEach((nama) => {
                    simpul.querySelector(`[data-input-${nama}]`).name = `items[${indeks}][${nama}]`;
                });

                if (! room) return;

                const selesai = mulai ? jadwalSelesai(mulai, durasi) : null;
                const subtotal = room.harga * durasi;
                total += subtotal;

                simpul.querySelector('[data-nama-room-jadwal]').textContent = `${room.nama_room} · ${room.nama_fasilitas}`;
                simpul.querySelector('[data-jam-terpilih]').textContent = mulai ? `${formatJam(mulai)} · ${durasi} jam` : 'Belum dipilih';
                simpul.querySelector('[data-input-id_room]').value = room.id_room;
                simpul.querySelector('[data-input-tanggal]').value = tanggal;
                simpul.querySelector('[data-input-jam_mulai]').value = mulai;
                simpul.querySelector('[data-input-durasi]').value = durasi;
                simpul.querySelector('[data-jam-selesai]').textContent = selesai ? `${formatJam(mulai)}–${formatJam(selesai)}` : '—';
                simpul.querySelector('[data-subtotal-item]').textContent = rupiah(subtotal);
            });

            const dipilih = baris.filter((item) => item.room);
            const pertama = dipilih[0];
            const jumlahJam = dipilih.reduce((jumlah, item) => jumlah + item.durasi, 0);
            const jadwal = dipilih.filter((item) => item.mulai).map((item) => `${formatJam(item.mulai)}–${formatJam(jadwalSelesai(item.mulai, item.durasi))}`);
            const namaRoom = dipilih.map((item) => item.room.nama_room).join(', ');

            form.querySelector('[data-panel-jadwal]').textContent = jadwal.length === 0 ? '—' : jadwal.join(' · ');
            form.querySelector('[data-panel-tanggal]').textContent = pertama
                ? `${formatTanggal(pertama.tanggal)} · ${jumlahJam} jam (jam selesai dihitung otomatis)`
                : '—';

            form.querySelector('[data-ringkasan-baris]').innerHTML = dipilih.length === 0
                ? '<tr><td class="py-3 text-ink-2" colspan="5">Belum ada room dipilih.</td></tr>'
                : dipilih.map((item) => `<tr class="border-b border-[#e5dccb]">
                        <td class="py-3 font-bold">${item.room.nama_room} · ${item.room.nama_fasilitas}</td>
                        <td class="py-3 tabular-nums">${formatJam(item.mulai)}–${formatJam(jadwalSelesai(item.mulai, item.durasi))}</td>
                        <td class="py-3 tabular-nums">${item.durasi} jam</td>
                        <td class="py-3 tabular-nums">${rupiah(item.room.harga)}</td>
                        <td class="py-3 font-bold tabular-nums">${rupiah(item.room.harga * item.durasi)}</td>
                    </tr>`).join('');

            totalTerakhir = total;
            form.querySelector('[data-total]').textContent = rupiah(total);
            form.querySelector('[data-konfirmasi-total]').textContent = rupiah(total);
            form.querySelector('[data-konfirmasi-jumlah]').textContent = dipilih.length;
            form.querySelector('[data-konfirmasi-room]').textContent = namaRoom || '—';
            form.querySelector('[data-konfirmasi-tanggal]').textContent = pertama ? formatTanggal(pertama.tanggal) : '—';
            form.querySelector('[data-konfirmasi-waktu]').textContent = jadwal.length === 0 ? '—' : jadwal.join(' · ');
            form.querySelector('[data-tambah-room]').disabled = baris.length >= konfigurasi.maksRoom;
            perbaruiSkema();
        };

        let skema = 'dp';

        function perbaruiSkema() {
            const dp = skema === 'dp' ? totalTerakhir / 2 : totalTerakhir;
            form.querySelector('[data-dp-jumlah]').textContent = rupiah(dp);
            form.querySelector('[data-sisa-jumlah]').textContent = rupiah(totalTerakhir - dp);
        }

        form.querySelectorAll('[data-skema]').forEach((tombol) => {
            tombol.addEventListener('click', () => {
                skema = tombol.dataset.skema;
                form.querySelectorAll('[data-skema]').forEach((lain) => {
                    const dipilih = lain === tombol;
                    lain.classList.toggle('bg-sun', dipilih);
                    lain.classList.toggle('bg-paper-2', !dipilih);
                });
                perbaruiSkema();
            });
        });

        form.querySelector('[data-tambah-room]').addEventListener('click', () => { tambahItem(); if (langkah > 1) aturLangkah(1); });
        form.querySelector('[data-nav="lanjut"]').addEventListener('click', () => aturLangkah(langkah + 1));
        form.querySelector('[data-nav="kembali"]').addEventListener('click', () => aturLangkah(langkah - 1));

        // Jam yang sudah terisi tidak bisa diklik, jadi jangan biarkan form terkirim tanpa jam valid.
        form.addEventListener('submit', (peristiwa) => {
            const kosong = barisAktif().filter((baris) => baris.room && ! baris.mulai);

            if (kosong.length === 0) return;

            peristiwa.preventDefault();
            aturLangkah(2);

            const grid = kosong[0].simpul.querySelector('[data-slot-grid-item]');
            grid.querySelector('[data-pesan-jam]')?.remove();
            grid.insertAdjacentHTML(
                'beforeend',
                '<p class="col-span-full text-xs font-bold text-bad" data-pesan-jam>Pilih jam main dulu — jam yang tampil pudar sudah dipesan atau sudah lewat.</p>',
            );
        });

        konfigurasi.awal.forEach((awal) => tambahItem({ ...awal, durasi: durasiBawaan }));
        aturLangkah(konfigurasi.mulaiLangkah || 1);
    })();
</script>
@endsection

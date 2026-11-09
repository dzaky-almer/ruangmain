@extends('layouts.admin')

@section('title', $pelanggan->nama.' — RUANGMAIN')

@section('content')
    @php
        $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
        $referensi = fn ($item): string => 'TX-'.str_pad((string) $item->id_pembayaran, 4, '0', STR_PAD_LEFT);
        $labelReservasi = [
            'menunggu_pembayaran' => 'Menunggu Pembayaran',
            'dikonfirmasi' => 'Dikonfirmasi',
            'check_in' => 'Check-in',
            'selesai' => 'Selesai',
            'dibatalkan' => 'Dibatalkan',
        ];
        $labelPembayaran = ['pending' => 'Menunggu', 'sukses' => 'Berhasil', 'gagal' => 'Gagal', 'dibatalkan' => 'Dibatalkan'];
        $kelasReservasi = ['menunggu_pembayaran' => 'border-warn text-warn', 'dikonfirmasi' => 'border-info text-info', 'check_in' => 'border-ok text-ok', 'selesai' => 'border-[#b4aca0] text-ink-2', 'dibatalkan' => 'border-bad text-bad'];
        $kelasBayar = ['pending' => 'border-warn text-warn', 'sukses' => 'border-ok text-ok', 'gagal' => 'border-bad text-bad', 'dibatalkan' => 'border-[#b4aca0] text-ink-2'];
    @endphp
    <section>
        <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / PELANGGAN</p>

        <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="font-script text-xl text-ink-2">profil pelanggan</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">{{ $pelanggan->nama }}</h1>
                <div class="mt-4 h-1.5 w-36 -rotate-1 bg-sun"></div>
            </div>

            <button class="{{ $pelanggan->status_akun === 'aktif' ? 'border-danger bg-danger' : 'border-ink bg-mint' }} rounded-full border-2 px-4 py-2 text-sm font-bold text-ink shadow-[2px_2px_0_#252525]" type="button" onclick="document.getElementById('ubah-status').showModal()">
                {{ $pelanggan->status_akun === 'aktif' ? 'Nonaktifkan akun' : 'Aktifkan akun' }}
            </button>
        </div>

        @if ($errors->any())
            <div class="alert-error mt-6" role="alert">
                <strong>Perubahan tidak dapat disimpan.</strong> {{ $errors->first() }}
            </div>
        @endif

        <div class="my-5 flex flex-wrap justify-between gap-x-10 gap-y-3 text-sm">
            <div class="flex items-center gap-3">
                <dt class="text-ink-2">Email</dt>
                <dd class="font-bold">{{ $pelanggan->email }}</dd>
            </div>
            <div class="flex items-center gap-3">
                <dt class="text-ink-2">Telepon</dt>
                <dd class="font-bold tabular-nums">{{ $pelanggan->no_hp }}</dd>
            </div>
            <div class="flex items-center gap-3">
                <dt class="text-ink-2">Status</dt>
                <dd>
                    <span @class(['chip-status border-ok text-ok' => $pelanggan->status_akun === 'aktif', 'chip-status border-bad text-bad' => $pelanggan->status_akun !== 'aktif'])>
                        {{ ucfirst($pelanggan->status_akun) }}
                    </span>
                </dd>
            </div>
        </div>

        <p class="text-xs text-ink-2">
            Terdaftar {{ $terdaftar ? \Illuminate\Support\Carbon::parse($terdaftar)->translatedFormat('d F Y') : '—' }} · {{ $daftarReservasi->count() }} reservasi · Data ilustratif, password tidak ditampilkan.
        </p>

        <div class="mt-10">
            <h2 class="text-xl font-extrabold tracking-[-0.02em]">Riwayat reservasi</h2>
            <div class="relative mt-3 overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b-2 border-ink text-xs uppercase tracking-[0.1em] text-ink-2">
                        <tr>
                            <th class="pr-4 pb-3 font-bold">Kode</th><th class="pr-4 pb-3 font-bold">Tanggal</th>
                            <th class="pr-4 pb-3 font-bold">Room</th><th class="pr-4 pb-3 font-bold">Total</th>
                            <th class="pr-4 pb-3 font-bold">Reservasi</th><th class="pb-3 font-bold">Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftarReservasi as $item)
                            @php
                                $sudahDibayar = (float) $item->pembayaran->where('status_pembayaran', 'sukses')->sum('jumlah');
                                $lunas = $sudahDibayar >= (float) $item->total_reservasi && (float) $item->total_reservasi > 0;
                            @endphp
                            <tr class="border-b border-[#eee9e1] last:border-0">
                                <td class="py-3 pr-4 font-extrabold tabular-nums">{{ $item->kode_booking }}</td>
                                <td class="py-3 pr-4 tabular-nums">{{ $item->tanggal_dibuat?->translatedFormat('d M Y') }}</td>
                                <td class="py-3 pr-4">
                                    {{ $item->detailReservasi->pluck('room.nama_room')->filter()->implode(', ') ?: '—' }}
                                    @php($detail = $item->detailReservasi->sortBy('jam_mulai')->first())
                                    @if ($detail)
                                        <span class="block text-xs text-ink-2 tabular-nums">{{ substr($detail->jam_mulai, 0, 5) }}-{{ substr($detail->jam_selesai, 0, 5) }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 font-bold tabular-nums">{{ $rupiah((float) $item->total_reservasi) }}</td>
                                <td class="py-3 pr-4">
                                    <span @class(['chip-status '.($kelasReservasi[$item->status_reservasi] ?? 'border-[#b4aca0] text-ink-2')])>
                                        {{ $labelReservasi[$item->status_reservasi] ?? $item->status_reservasi }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <span @class(['chip-status border-ok text-ok' => $lunas, 'chip-status border-bad text-bad' => ! $lunas])>{{ $lunas ? 'Lunas' : 'Belum lunas' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-6 text-center text-ink-2" colspan="6">Pelanggan ini belum memiliki reservasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-10">
            <h2 class="text-xl font-extrabold tracking-[-0.02em]">Riwayat pembayaran</h2>
            <div class="relative mt-3 overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b-2 border-ink text-xs uppercase tracking-[0.1em] text-ink-2">
                        <tr>
                            <th class="pr-4 pb-3 font-bold">Referensi</th><th class="pr-4 pb-3 font-bold">Booking</th>
                            <th class="pr-4 pb-3 font-bold">Jenis</th><th class="pr-4 pb-3 font-bold">Metode</th>
                            <th class="pr-4 pb-3 font-bold">Nominal</th><th class="pb-3 font-bold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftarPembayaran as $item)
                            <tr class="border-b border-[#eee9e1] last:border-0">
                                <td class="py-3 pr-4 font-extrabold tabular-nums">{{ $referensi($item) }}</td>
                                <td class="py-3 pr-4 tabular-nums">{{ $item->reservasi?->kode_booking ?? '—' }}</td>
                                <td class="py-3 pr-4 uppercase">{{ $item->jenis_pembayaran }}</td>
                                <td class="py-3 pr-4 uppercase">{{ $item->metode_pembayaran }}</td>
                                <td class="py-3 pr-4 font-bold tabular-nums">{{ $rupiah((float) $item->jumlah) }}</td>
                                <td class="py-3">
                                    <span @class(['chip-status '.($kelasBayar[$item->status_pembayaran] ?? 'border-[#b4aca0] text-ink-2')])>
                                        {{ $labelPembayaran[$item->status_pembayaran] ?? $item->status_pembayaran }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-6 text-center text-ink-2" colspan="6">Pelanggan ini belum memiliki transaksi pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-6"><a class="btn-secondary inline-block" href="{{ route('admin.pelanggan.index') }}">Kembali ke daftar pelanggan</a></p>

        <dialog class="m-auto max-h-[90vh] w-[min(520px,calc(100%-2rem))] overflow-y-auto rounded-[22px] border-2 border-ink bg-white p-5 text-ink shadow-[6px_6px_0_#252525] backdrop:bg-ink/40" id="ubah-status">
            <div class="flex items-start justify-between gap-4">
                <p class="text-lg font-extrabold">{{ $pelanggan->status_akun === 'aktif' ? 'Nonaktifkan akun' : 'Aktifkan akun' }}</p>
                <button class="rounded-full border-2 border-ink px-2.5 py-0.5 text-sm font-bold" type="button" onclick="this.closest('dialog').close()">✕</button>
            </div>

            <p class="mt-3 text-sm text-ink-2">
                @if ($pelanggan->status_akun === 'aktif')
                    Akun {{ $pelanggan->nama }} tidak dapat dipakai untuk reservasi baru. Riwayat tetap tersimpan untuk audit.
                @else
                    Akun {{ $pelanggan->nama }} dapat dipakai kembali untuk reservasi baru.
                @endif
            </p>

            <form class="mt-5 flex flex-wrap gap-2" method="POST" action="{{ route('admin.pelanggan.status', $pelanggan) }}">
                @csrf
                @method('PATCH')
                <input name="status_akun" type="hidden" value="{{ $pelanggan->status_akun === 'aktif' ? 'nonaktif' : 'aktif' }}">
                <button class="{{ $pelanggan->status_akun === 'aktif' ? 'btn-danger' : 'btn-primary' }}" type="submit">
                    {{ $pelanggan->status_akun === 'aktif' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                </button>
                <button class="btn-secondary" type="button" onclick="this.closest('dialog').close()">Batal</button>
            </form>
        </dialog>
    </section>
@endsection

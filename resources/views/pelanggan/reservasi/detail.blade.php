@extends('layouts.app')
@section('title', $reservasi->kode_booking.' — RUANGMAIN')
@section('content')
@php
    $labelStatus = [
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'dikonfirmasi' => 'Dikonfirmasi',
        'check_in' => 'Sedang Berlangsung',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
    $kelasStatus = [
        'menunggu_pembayaran' => 'bg-sun',
        'dikonfirmasi' => 'bg-mint',
        'check_in' => 'bg-sky',
        'selesai' => 'bg-paper-2',
        'dibatalkan' => 'bg-danger',
    ];
    $jadwalPertama = $detail->sortBy('jam_mulai')->first();
    $jam = fn (?string $nilai): string => $nilai ? str_replace(':', '.', substr($nilai, 0, 5)) : '—';
    $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
    $labelBayar = ['pending' => 'Menunggu', 'sukses' => 'Berhasil', 'gagal' => 'Gagal', 'dibatalkan' => 'Dibatalkan'];
    $kelasBayar = ['pending' => 'bg-sun', 'sukses' => 'bg-mint', 'gagal' => 'bg-danger', 'dibatalkan' => 'bg-paper-2'];
@endphp
<section class="mx-auto max-w-6xl px-5 py-8 sm:px-8">
    <p class="text-xs font-bold tracking-[.16em] text-ink-2">RUANGMAIN / PELANGGAN</p>
    <p class="mt-6 font-script text-2xl text-ink-2">Detail Reservasi</p>
    <h1 class="text-3xl font-extrabold tracking-[-.04em] tabular-nums sm:text-5xl">{{ $reservasi->kode_booking }}</h1>
    <div class="mt-3 h-1.5 w-40 -rotate-1 bg-bubble"></div>

    <div class="mt-5 flex flex-wrap items-center gap-3">
        <span class="chip-status {{ $kelasStatus[$reservasi->status_reservasi] ?? 'bg-paper-2' }}">{{ $labelStatus[$reservasi->status_reservasi] ?? $reservasi->status_reservasi }}</span>
        <span class="chip-status {{ $lunas ? 'bg-mint' : 'bg-danger' }}">{{ $lunas ? 'Lunas' : 'Belum Dibayar' }}</span>
        <span class="font-bold tabular-nums">{{ $reservasi->batas_pembayaran?->translatedFormat('d M, H.i') }}</span>
    </div>

    <div class="mt-8 grid gap-8 lg:grid-cols-[1.35fr_1fr]">
        <div class="min-w-0">
            <h2 class="text-2xl font-extrabold">Detail room</h2>
            <div class="card-paper mt-4 overflow-x-auto p-5">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="border-b-2 border-ink text-xs uppercase tracking-[.12em] text-ink-2">
                        <tr>
                            <th class="py-3">Room</th><th class="py-3">Jadwal</th>
                            <th class="py-3">Durasi</th><th class="py-3">Harga / jam</th><th class="py-3">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detail as $baris)
                            <tr class="border-b border-[#e5dccb]">
                                <td class="py-3">
                                    <span class="font-bold">{{ $baris->room?->nama_room ?? '—' }}</span>
                                    <span class="block text-xs text-ink-2">{{ $baris->room?->fasilitas?->nama_fasilitas ?? '—' }}</span>
                                </td>
                                <td class="py-3">
                                    <span>{{ $baris->tanggal?->translatedFormat('D, d M Y') }}</span>
                                    <span class="block tabular-nums text-ink-2">{{ $jam($baris->jam_mulai) }}–{{ $jam($baris->jam_selesai) }}</span>
                                </td>
                                <td class="py-3 tabular-nums">{{ $baris->durasi_jam }} jam</td>
                                <td class="py-3 tabular-nums">{{ $rupiah((float) $baris->harga_per_jam) }}</td>
                                <td class="py-3 font-bold tabular-nums">{{ $rupiah((float) $baris->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <dl class="mt-5 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="font-bold">Total tagihan</dt><dd class="font-bold tabular-nums">{{ $rupiah((float) $reservasi->total_reservasi) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Sudah dibayar</dt><dd class="tabular-nums">{{ $rupiah($sudahDibayar) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Sisa tagihan</dt><dd class="font-bold tabular-nums text-bad">{{ $rupiah($sisaTagihan) }}</dd></div>
                <p class="pt-1 text-xs text-ink-2">Status bayar dihitung dari transaksi berhasil, bukan dari tombol.</p>
            </dl>

            <h2 class="mt-8 text-2xl font-extrabold">Transaksi pembayaran</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[520px] text-left text-sm">
                    <thead class="border-b-2 border-ink text-xs uppercase tracking-[.12em] text-ink-2">
                        <tr>
                            <th class="py-3">Referensi</th><th class="py-3">Jenis</th><th class="py-3">Metode</th>
                            <th class="py-3">Nominal</th><th class="py-3">Status</th><th class="py-3">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reservasi->pembayaran->sortBy('id_pembayaran') as $bayar)
                            <tr class="border-b border-[#e5dccb]">
                                <td class="py-3 font-bold tabular-nums">TX-{{ str_pad((string) $bayar->id_pembayaran, 4, '0', STR_PAD_LEFT) }}</td>
                                <td class="py-3">{{ strtoupper($bayar->jenis_pembayaran) }}</td>
                                <td class="py-3 uppercase">{{ $bayar->metode_pembayaran }}</td>
                                <td class="py-3 font-bold tabular-nums">{{ $rupiah((float) $bayar->jumlah) }}</td>
                                <td class="py-3"><span class="chip-status {{ $kelasBayar[$bayar->status_pembayaran] ?? 'bg-paper-2' }}">{{ $labelBayar[$bayar->status_pembayaran] ?? $bayar->status_pembayaran }}</span></td>
                                <td class="py-3 tabular-nums text-ink-2">{{ $bayar->waktu_bayar?->translatedFormat('d M, H.i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-4 text-ink-2" colspan="6">Belum ada transaksi untuk reservasi ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card-paper overflow-hidden border-4 border-ink p-0 shadow-[6px_6px_0_#252525]">
            <div class="rounded-t-[18px] border-b-2 border-dashed border-ink bg-sun px-6 py-4">
                <p class="font-script text-2xl">booking pass</p>
            </div>
            <div class="px-6 py-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.14em] text-ink-2">Kode Booking</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ $reservasi->kode_booking }}</p>
                    </div>
                    <div class="rounded-md border-2 border-ink bg-white p-2">
                        {!! $qr !!}
                    </div>
                </div>
                <dl class="mt-5 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Nama</dt><dd class="font-bold">{{ $reservasi->pengguna?->nama }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Fasilitas</dt><dd class="font-bold">{{ $jadwalPertama?->room?->fasilitas?->nama_fasilitas ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Room</dt><dd class="font-bold">{{ $detail->pluck('room.nama_room')->filter()->implode(', ') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Tanggal</dt><dd class="font-bold">{{ $jadwalPertama?->tanggal?->translatedFormat('d M Y') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Jadwal</dt><dd class="font-bold tabular-nums">{{ $jam($jadwalPertama?->jam_mulai) }}–{{ $jam($jadwalPertama?->jam_selesai) }}</dd></div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="chip-status {{ $kelasStatus[$reservasi->status_reservasi] ?? 'bg-paper-2' }}">Reservasi: {{ $labelStatus[$reservasi->status_reservasi] ?? $reservasi->status_reservasi }}</span>
                    <span class="chip-status {{ $lunas ? 'bg-mint' : 'bg-danger' }}">{{ $lunas ? 'Lunas' : 'Belum lunas' }}</span>
                </div>
                <p class="mt-4 text-xs text-ink-2">Tunjukkan QR ini ke petugas saat check-in.</p>

                @if (! $lunas && in_array($reservasi->status_reservasi, ['menunggu_pembayaran', 'dikonfirmasi'], true))
                    <a class="btn-primary mt-5 w-full" href="{{ route('pelanggan.pembayaran.form', $reservasi) }}">{{ $sudahDibayar > 0 ? 'Bayar Sisa Tagihan' : 'Bayar Sekarang' }}</a>
                @endif
                <a class="btn-secondary mt-3 w-full" href="{{ route('pelanggan.pembayaran.index') }}">Riwayat Pembayaran</a>
            </div>
        </aside>
    </div>

    <a class="btn-secondary mt-8 inline-block" href="{{ route('pelanggan.reservasi.index') }}">Kembali ke Riwayat</a>
</section>
@endsection

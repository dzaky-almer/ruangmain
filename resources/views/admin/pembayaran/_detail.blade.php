@php
    $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
    $kasMenunggu = $pembayaran->metode_pembayaran === 'cash' && $pembayaran->status_pembayaran === 'pending';
    $qrisMenunggu = $pembayaran->metode_pembayaran === 'qris' && $pembayaran->status_pembayaran === 'pending';
    $chipStatus = ['pending' => 'Menunggu', 'sukses' => 'Berhasil', 'gagal' => 'Gagal', 'dibatalkan' => 'Dibatalkan'];
    $kelasChip = ['pending' => 'chip-status bg-sun', 'sukses' => 'chip-status bg-mint', 'gagal' => 'chip-status bg-danger', 'dibatalkan' => 'chip-status bg-paper-2'];
    $sudahDibayar = (float) $reservasi->pembayaran->where('status_pembayaran', 'sukses')->sum('jumlah');
    $ringkasan = ['sudahDibayar' => $sudahDibayar, 'sisaTagihan' => max(0, (float) $reservasi->total_reservasi - $sudahDibayar)];
    $prefiks = 'pembayaran-'.$pembayaran->id_pembayaran;
@endphp
<div class="grid gap-5 lg:grid-cols-[1.2fr_1fr]">
    <article class="card-paper p-5">
        <h2 class="text-xl font-extrabold">Rincian transaksi</h2>
        <p class="mt-3"><span class="{{ $kelasChip[$pembayaran->status_pembayaran] ?? 'chip-status bg-paper-2' }}">Transaksi: {{ $chipStatus[$pembayaran->status_pembayaran] ?? $pembayaran->status_pembayaran }}</span></p>
        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-ink-2">Kode booking</dt><dd class="font-bold tabular-nums">{{ $reservasi->kode_booking }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-2">Pelanggan</dt><dd class="font-bold">{{ $reservasi->pengguna?->nama ?? '—' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-2">Jenis pembayaran</dt><dd class="font-bold uppercase">{{ $pembayaran->jenis_pembayaran }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-2">Metode</dt><dd class="font-bold uppercase">{{ $pembayaran->metode_pembayaran }}</dd></div>
            <div class="flex justify-between gap-4 border-t border-[#e5dccb] pt-2"><dt class="text-ink-2">Nominal</dt><dd class="text-xl font-extrabold tabular-nums">{{ $rupiah((float) $pembayaran->jumlah) }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-2">Total tagihan</dt><dd class="font-bold tabular-nums">{{ $rupiah((float) $reservasi->total_reservasi) }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-2">Total berhasil</dt><dd class="font-bold tabular-nums">{{ $rupiah($ringkasan['sudahDibayar']) }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-2">Sisa</dt><dd class="font-bold tabular-nums">{{ $rupiah($ringkasan['sisaTagihan']) }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-2">Waktu pembayaran</dt><dd class="font-bold tabular-nums">{{ $pembayaran->waktu_bayar?->translatedFormat('d M Y · H.i') ?? 'Belum diverifikasi' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-2">Admin verifikator</dt><dd class="font-bold">{{ $pembayaran->diverifikasiOleh?->nama ?? 'Belum ada' }}</dd></div>
            @if ($pembayaran->metode_pembayaran === 'qris')
                <div class="flex justify-between gap-4"><dt class="text-ink-2">Referensi gateway</dt><dd class="font-bold">{{ $pembayaran->id_transaksi_gateway ?? 'Belum tersedia' }}</dd></div>
            @endif
        </dl>
        <a class="mt-5 inline-block font-bold text-info underline underline-offset-4" href="{{ route('admin.reservasi.detail', $reservasi) }}">Lihat reservasi →</a>
    </article>

    <aside class="space-y-5">
        @if ($kasMenunggu)
            <form class="card-paper p-5" method="POST" action="{{ route('admin.pembayaran.verifikasi', $pembayaran) }}">
                @csrf
                <input type="hidden" name="_dialog" value="detail-{{ $pembayaran->id_pembayaran }}">
                <h2 class="text-xl font-extrabold">Verifikasi penerimaan cash</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="label-sketch" for="{{ $prefiks }}-nominal">Nominal diterima</label>
                        <input class="field-sketch" id="{{ $prefiks }}-nominal" value="{{ $rupiah((float) $pembayaran->jumlah) }}" readonly>
                    </div>
                    <div>
                        <label class="label-sketch" for="{{ $prefiks }}-catatan">Catatan verifikasi *</label>
                        <input class="field-sketch" id="{{ $prefiks }}-catatan" name="catatan_verifikasi" value="{{ old('catatan_verifikasi') }}" placeholder="cash diterima penuh di kasir." required>
                        @error('catatan_verifikasi')<p class="mt-1 text-xs font-bold text-bad">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-ink-2">Catatan hanya menjadi jejak audit pada notifikasi; tabel pembayaran tidak memiliki kolom catatan.</p>
                    </div>
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="konfirmasi_terima" value="1" required>
                        <span>Saya telah menerima cash sesuai nominal transaksi.</span>
                    </label>
                    @error('konfirmasi_terima')<p class="text-xs font-bold text-bad">{{ $message }}</p>@enderror
                </div>
                <button class="btn-primary mt-5 w-full" type="submit">Verifikasi Pembayaran Cash</button>
            </form>
            <div class="alert-warning" role="note"><strong>Periksa sebelum memverifikasi.</strong> Hanya verifikasi cash yang benar-benar diterima. Setelah berhasil, transaksi dikunci dan menyimpan nama petugas serta waktu.</div>
        @elseif ($qrisMenunggu)
            <div class="card-paper p-5">
                <h2 class="text-xl font-extrabold">Konfirmasi gateway</h2>
                <p class="mt-2 text-sm text-ink-2">Referensi gateway — {{ $pembayaran->id_transaksi_gateway ?? 'Belum tersedia' }}</p>
                <button class="btn-secondary mt-4 w-full" type="button" disabled>Menunggu Konfirmasi Gateway</button>
            </div>
            <div class="alert-warning" role="note"><strong>Gateway QRIS belum dikonfigurasi.</strong> Status QRIS harus mengikuti konfirmasi gateway. Admin tidak dapat menandainya berhasil secara manual.</div>
        @else
            @if (! in_array($pembayaran->status_pembayaran, ['sukses'], true))
                <div class="alert-error" role="alert"><strong>Transaksi berstatus {{ $chipStatus[$pembayaran->status_pembayaran] ?? $pembayaran->status_pembayaran }}.</strong> Status final — pelanggan dapat membuat transaksi baru dari halaman bayar.</div>
            @endif
            <div class="card-soft p-5">
                <h2 class="text-xl font-extrabold">Jejak verifikasi</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Diverifikasi</dt><dd class="font-bold tabular-nums">{{ $pembayaran->waktu_verifikasi?->translatedFormat('d M Y · H.i') ?? 'Belum ada' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Petugas</dt><dd class="font-bold">{{ $pembayaran->diverifikasiOleh?->nama ?? ($pembayaran->metode_pembayaran === 'qris' ? 'Gateway QRIS' : 'Belum ada') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Metode</dt><dd class="font-bold uppercase">{{ $pembayaran->metode_pembayaran }}</dd></div>
                </dl>
            </div>
        @endif
    </aside>
</div>

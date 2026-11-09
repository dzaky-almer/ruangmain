@extends('layouts.app')
@section('title', 'Bayar '.$reservasi->kode_booking.' — RUANGMAIN')
@section('content')
@php
    $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
        $opsiTerpilih = collect($opsi)->firstWhere('jenis', $jenisTerpilih) ?? $opsi[0];
@endphp
<section class="mx-auto max-w-3xl px-5 py-8 sm:px-8">
    <p class="text-xs font-bold tracking-[.16em] text-ink-2">RUANGMAIN / PELANGGAN</p>
    <p class="mt-6 font-script text-2xl text-ink-2">hampir siap</p>
        <h1 class="text-3xl font-extrabold tracking-[-.04em] sm:text-5xl">Pembayaran</h1>
            <div class="mt-3 h-1.5 w-72 -rotate-1 bg-sun"></div>

            @if ($errors->any())
                <div class="alert-error mt-6" role="alert"><strong>Pembayaran tidak dapat dibuat.</strong> {{ $errors->first() }}</div>
            @endif

            <form class="mt-8 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]" method="POST" action="{{ route('pelanggan.pembayaran.simpan', $reservasi) }}">
        @csrf

                <div class="space-y-4">
            <div class="card-soft p-5 text-sm">
                <dl class="space-y-3">
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Kode booking</dt><dd class="font-bold tabular-nums">{{ $reservasi->kode_booking }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Total tagihan</dt><dd class="font-bold tabular-nums">{{ $rupiah((float) $reservasi->total_reservasi) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Pembayaran berhasil</dt><dd class="font-bold tabular-nums">{{ $rupiah($ringkasan['sudahDibayar']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Sisa tagihan</dt><dd class="text-lg font-extrabold tabular-nums">{{ $rupiah($ringkasan['sisaTagihan']) }}</dd></div>
                </dl>
            </div>

            <p class="card-soft px-5 py-4 text-xs leading-relaxed text-ink-2" data-catatan-metode="qris">
                            <strong class="text-ink">Bayar lewat Midtrans.</strong> Menekan tombol mengarahkan ke halaman pembayaran Snap (QRIS, e-wallet, VA). Status berubah dari notifikasi Midtrans, bukan dari tombol ini.
                        </p>
                        <p class="card-soft hidden px-5 py-4 text-xs leading-relaxed text-ink-2" data-catatan-metode="cash">
                            <strong class="text-ink">Menunggu verifikasi admin.</strong> Datang ke kasir dan sampaikan kode booking {{ $reservasi->kode_booking }}. Admin akan mencatat nominal dan memverifikasi penerimaan cash.
                        </p>
                    </div>

        <div class="space-y-5">
            <div>
                <p class="text-sm font-bold">Jenis pembayaran</p>
                <div class="mt-2 space-y-2">
                    @foreach ($opsi as $pilihan)
                        <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border-2 border-ink/20 bg-white px-4 py-3 has-checked:border-ink has-checked:bg-paper-2">
                            <span class="flex items-center gap-3">
                                <input type="radio" name="jenis_pembayaran" value="{{ $pilihan['jenis'] }}" data-opsi-jenis="{{ $pilihan['jenis'] }}" data-opsi-label="{{ $pilihan['label'] }}" data-opsi-jumlah="{{ $pilihan['jumlah'] }}" @checked($pilihan['jenis'] === $opsiTerpilih['jenis']) required>
                                <span class="font-bold">{{ $pilihan['label'] }} · {{ $rupiah($pilihan['jumlah']) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="text-sm font-bold">Metode pembayaran</p>
                <div class="mt-2 space-y-2">
                    @foreach (['qris' => ['QRIS', 'Dikonfirmasi otomatis oleh payment gateway'], 'cash' => ['Cash', 'Bayar langsung ke admin; status berubah setelah admin memverifikasi']] as $nilai => [$judul, $ket])
                                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-ink/20 bg-white px-4 py-3 has-checked:border-ink has-checked:bg-sky/25">
                                                                        <input class="peer sr-only" type="radio" name="metode_pembayaran" value="{{ $nilai }}" data-opsi-metode="{{ $nilai }}" @checked($nilai === 'qris') required>
                                                                        <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border-2 border-ink" aria-hidden="true"><span class="size-1.5 rounded-full bg-ink opacity-0 peer-checked:opacity-100"></span></span>
                            <span class="min-w-0">
                                <span class="block font-bold">{{ $judul }}</span>
                                <span class="block text-xs text-ink-2">{{ $ket }}</span>
                            </span>
                                                                    </label>
                    @endforeach
                </div>
            </div>

            <button class="btn-primary w-full bg-bubble text-base" type="submit">Bayar <span data-nominal-transaksi>{{ $rupiah($opsiTerpilih['jumlah']) }}</span></button>
            <p class="text-xs leading-relaxed text-ink-2">Menekan tombol hanya membuat transaksi. Pembayaran dianggap berhasil setelah gateway/admin mengonfirmasi.</p>
        </div>
            </form>
        </section>

<script>
(() => {
    const format = (nilai) => 'Rp' + Math.round(nilai).toLocaleString('id-ID');

    const radio = document.querySelectorAll('[data-opsi-jenis]');
    const terapkan = (terpilih) => {
        const jumlah = format(Number(terpilih.dataset.opsiJumlah));
        document.querySelectorAll('[data-nominal-transaksi]').forEach((el) => { el.textContent = jumlah; });
        document.querySelectorAll('[data-label-jenis]').forEach((el) => { el.textContent = terpilih.dataset.opsiLabel; });
    };
    radio.forEach((input) => input.addEventListener('change', () => terapkan(input)));

    document.querySelectorAll('[data-opsi-metode]').forEach((input) => {
        input.addEventListener('change', () => {
            document.querySelectorAll('[data-catatan-metode]').forEach((el) => {
                el.classList.toggle('hidden', el.dataset.catatanMetode !== input.value);
            });
        });
    });
})();
</script>
@endsection

@extends('layouts.admin')

@section('title', 'Check-in — RUANGMAIN')

@section('content')
    @php
        $labelStatus = [
            'menunggu_pembayaran' => 'Menunggu Pembayaran',
            'dikonfirmasi' => 'Dikonfirmasi',
            'check_in' => 'Check-in',
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
                            $jam = fn (?string $nilai): string => $nilai ? str_replace(':', '.', substr($nilai, 0, 5)) : '—';
                            $lunas = $ringkasan['lunas'] ?? false;
        $rupiah = fn (float $nilai): string => 'Rp'.number_format($nilai, 0, ',', '.');
    @endphp

    <section>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / CHECK-IN</p>
            <span class="text-xs font-bold text-ink-2">Hari ini {{ now()->translatedFormat('d M Y, H.i') }}</span>
        </div>

        <div class="mt-6">
            <p class="font-script text-xl text-ink-2">pintu masuk</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] sm:text-4xl">Check-in{{ $reservasi ? ($lunas ? ' · Lunas' : ' · Belum Lunas') : '' }}</h1>
            <div class="mt-4 h-1.5 w-36 -rotate-1 bg-sun"></div>
            <p class="mt-3 text-ink-2">Cari dengan kode booking dari booking pass. Pemindai QR kamera dan pembaca barcode eksternal sama-sama hanya mengisi kolom ini.</p>
        </div>

        <form class="mt-6 flex flex-wrap items-center gap-3" method="GET" action="{{ route('admin.checkin.index') }}" data-form-cari>
            <label class="sr-only" for="kode_booking">Kode booking</label>
            <div class="relative w-full sm:max-w-md">
                <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4" stroke-linecap="round"/></svg>
                <input class="block min-h-11 w-full rounded-full border-2 border-ink bg-white py-2.5 pr-4 pl-9 text-sm text-ink tabular-nums outline-none placeholder:text-ink-2/70 focus:ring-3 focus:ring-info/25" id="kode_booking" name="kode_booking" value="{{ $kode }}" placeholder="RM-XXXXXX" autocomplete="off" data-input-kode>
            </div>
            <button class="btn-primary" type="submit">Cari Reservasi</button>
        </form>

        <div class="card-soft mt-4 p-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-bold">Scan QR booking pass</p>
                    <p class="text-xs text-ink-2">QR hanya mengisi kode booking. QR bukan bukti pembayaran — pemeriksaan lunas tetap dilakukan di halaman ini.</p>
                </div>
                <div class="flex gap-2">
                    <button class="btn-secondary" type="button" data-mulai-scan>Mulai Scan</button>
                    <button class="btn-secondary hidden" type="button" data-stop-scan>Hentikan</button>
                </div>
            </div>
            <div class="mt-3 hidden" id="pembaca-qr" data-pembaca></div>
            <p class="alert-warning mt-3 hidden" data-pesan-scan></p>
        </div>

        @if ($errors->any())
            <div class="alert-error mt-4" role="alert">
                <strong>Aksi check-in tidak dapat dilakukan.</strong>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $galat)<li>{{ $galat }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($reservasi === null && $kode !== '')
            <div class="alert-warning mt-6" role="status">
                <strong>Booking tidak ditemukan.</strong> Tidak ada reservasi dengan kode {{ $kode }}. Periksa kembali kode pada booking pass.
            </div>
        @endif

        @if ($reservasi)
            <div class="mt-6 grid gap-8 xl:grid-cols-[1fr_1.3fr]">
                <div>
                    <div class="card-paper p-5">
                        <p class="font-script text-xl text-ink-2">Ringkasan reservasi</p>
                        <h2 class="mt-1 text-xl font-extrabold">{{ $reservasi->pengguna?->nama }} · <span class="tabular-nums">{{ $reservasi->kode_booking }}</span></h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="chip-status {{ $kelasStatus[$reservasi->status_reservasi] ?? 'bg-paper-2' }}">Reservasi: {{ $labelStatus[$reservasi->status_reservasi] ?? $reservasi->status_reservasi }}</span>
                            <span @class(['chip-status', 'chip-aktif bg-mint' => $lunas, 'border-warn text-warn' => ! $lunas])>Pembayaran: {{ $lunas ? 'Lunas' : 'DP' }}</span>
                        </div>
                        <dl class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-ink-2">Tanggal reservasi</dt><dd class="font-bold">{{ $reservasi->tanggal_dibuat?->translatedFormat('d M Y') }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-ink-2">Total tagihan</dt><dd class="font-bold tabular-nums">{{ $rupiah((float) $reservasi->total_reservasi) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-ink-2">Pembayaran berhasil</dt><dd class="font-bold tabular-nums">{{ $rupiah($ringkasan['sudahDibayar']) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-ink-2">Sisa tagihan</dt><dd class="font-bold tabular-nums {{ $lunas ? 'text-ok' : 'text-warn' }}">{{ $rupiah($ringkasan['sisaTagihan']) }}</dd></div>
                        </dl>
                        <div class="mt-5 flex flex-wrap gap-4">
                            <a class="text-sm font-bold text-info underline underline-offset-4" href="{{ route('admin.reservasi.detail', $reservasi) }}">Buka detail reservasi →</a>
                            @unless ($lunas)
                                <a class="text-sm font-bold text-info underline underline-offset-4" href="{{ route('admin.pembayaran.index', ['cari' => $reservasi->kode_booking]) }}">Catat pelunasan cash →</a>
                            @endunless
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-xl font-extrabold">Detail room</h2>
                    <p class="mt-2 text-sm text-ink-2">Untuk beberapa room, tiap detail dipilih dan diproses sendiri. Waktu aktual dicatat saat aksi berhasil.</p>

                    <div class="mt-4 space-y-5">
                        @foreach ($reservasi->detailReservasi as $detail)
                            @php
                                $pesan = $alasan[$detail->id_detail] ?? [];
                                $siap = $pesan === [];
                                $menit = $terlambat[$detail->id_detail] ?? 0;
                                $sudahCheckin = $detail->waktu_checkin !== null;
                                $sudahCheckout = $detail->waktu_checkout !== null;
                            @endphp
                            <div @class(['card-paper p-5', 'bg-sun' => $siap])>
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-xl font-extrabold">{{ $detail->room?->nama_room }} · {{ $detail->room?->fasilitas?->nama_fasilitas }}</h3>
                                        <p class="mt-1 text-xs text-ink-2">Kapasitas {{ $detail->room?->kapasitas }} orang</p>
                                    </div>
                                    <span @class(['chip-status', 'border-warn text-warn' => $menit > 0, 'chip-aktif' => $menit === 0])>{{ $menit > 0 ? "Terlambat {$menit} menit" : 'Sesuai jadwal' }}</span>
                                </div>
                                <dl class="mt-4 space-y-2 text-sm">
                                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Tanggal</dt><dd class="font-bold">{{ $detail->tanggal?->translatedFormat('d M Y') }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Jadwal</dt><dd class="font-bold tabular-nums">{{ $jam($detail->jam_mulai) }}–{{ $jam($detail->jam_selesai) }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Durasi</dt><dd class="font-bold tabular-nums">{{ $detail->durasi_jam }} jam</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Status room</dt><dd class="font-bold">{{ ucfirst($detail->room?->status_room ?? '—') }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-ink-2">Waktu check-in aktual</dt><dd class="font-bold tabular-nums">{{ $detail->waktu_checkin?->translatedFormat('d M Y, H.i') ?? 'Belum tercatat' }}</dd></div>
                                </dl>

                                @if ($sudahCheckin && ! $sudahCheckout)
                                    <div class="alert-success mt-4" role="status">
                                        <strong>Room sudah check-in.</strong> Waktu aktual tercatat pada {{ $detail->waktu_checkin->translatedFormat('d M Y, H.i') }}. Room sedang digunakan — proses berikutnya ada di halaman check-out.
                                    </div>
                                    <a class="btn-secondary mt-4 inline-flex" href="{{ route('admin.checkout.index') }}">Buka halaman check-out →</a>
                                @elseif ($siap)
                                    <div class="alert-success mt-4"><strong>Syarat check-in terpenuhi.</strong> Pelunasan telah diverifikasi. Admin tetap memeriksa jadwal dan memilih room sebelum mencatat waktu aktual.</div>
                                    <form class="mt-4" method="POST" action="{{ route('admin.checkin.simpan') }}">
                                        @csrf
                                        <input type="hidden" name="kode_booking" value="{{ $reservasi->kode_booking }}">
                                        <input type="hidden" name="id_detail" value="{{ $detail->id_detail }}">
                                        <button class="btn-secondary" type="submit">Proses Check-in {{ $detail->room?->nama_room }}</button>
                                    </form>
                                @else
                                    <div class="alert-error mt-4" role="alert">
                                        <strong>Check-in ditolak.</strong>
                                        <ul class="mt-1 list-inside list-disc">@foreach ($pesan as $satu)<li>{{ $satu }}</li>@endforeach</ul>
                                    </div>
                                    <button class="btn-secondary mt-4 cursor-not-allowed opacity-60" type="button" disabled aria-disabled="true">Aksi check-in dinonaktifkan</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </section>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        (() => {
            const input = document.querySelector('[data-input-kode]');
            const form = document.querySelector('[data-form-cari]');
            const pembaca = document.querySelector('[data-pembaca]');
            const pesan = document.querySelector('[data-pesan-scan]');
            const mulai = document.querySelector('[data-mulai-scan]');
            const stop = document.querySelector('[data-stop-scan]');

            if (!input || !form || !pembaca || !mulai || !stop) {
                return;
            }

            const keluhkan = (teks) => {
                pesan.textContent = teks;
                pesan.classList.remove('hidden');
            };

            let pemindai = null;
            let selesai = false;

            const hentikan = async () => {
                if (pemindai) {
                    await pemindai.stop().catch(() => {});
                    await pemindai.clear().catch(() => {});
                    pemindai = null;
                }
                pembaca.classList.add('hidden');
                mulai.classList.remove('hidden');
                stop.classList.add('hidden');
            };

            mulai.addEventListener('click', async () => {
                if (typeof Html5Qrcode === 'undefined') {
                    keluhkan('Pustaka pemindai tidak dapat dimuat (periksa koneksi). Gunakan pencarian kode booking.');
                    return;
                }

                pesan.classList.add('hidden');
                pembaca.classList.remove('hidden');
                mulai.classList.add('hidden');
                stop.classList.remove('hidden');
                selesai = false;

                pemindai = new Html5Qrcode('pembaca-qr');

                try {
                    await pemindai.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, async (teks) => {
                        if (selesai) {
                            return;
                        }

                        // Kirim dulu supaya data muncul segera; matikan kamera tidak perlu ditunggu.
                        selesai = true;
                        input.value = teks.trim().toUpperCase();
                        form.submit();
                        hentikan();
                    }, () => {});
                } catch (galat) {
                    await hentikan();
                    keluhkan('Kamera tidak dapat diakses. Gunakan pencarian kode booking.');
                }
            });

            stop.addEventListener('click', hentikan);
        })();
    </script>
@endsection

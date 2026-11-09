@extends('layouts.admin')

@section('title', $reservasi->kode_booking.' — RUANGMAIN')

@section('content')
    @php
        $labelStatus = ['menunggu_pembayaran' => 'Menunggu', 'dikonfirmasi' => 'Dikonfirmasi', 'check_in' => 'Check-in', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
        $kelasStatus = ['menunggu_pembayaran' => 'border-warn text-warn', 'dikonfirmasi' => 'border-info text-info', 'check_in' => 'border-ok text-ok', 'selesai' => 'border-[#b4aca0] text-ink-2', 'dibatalkan' => 'border-bad text-bad'];
        $labelBayar = ['pending' => 'Menunggu', 'sukses' => 'Berhasil', 'gagal' => 'Gagal', 'dibatalkan' => 'Dibatalkan'];
        $kelasBayar = ['pending' => 'border-warn text-warn', 'sukses' => 'border-ok text-ok', 'gagal' => 'border-bad text-bad', 'dibatalkan' => 'border-[#b4aca0] text-ink-2'];
        $labelJenis = ['dp' => 'DP', 'pelunasan' => 'Pelunasan'];
        $jam = fn (?string $nilai): string => $nilai ? str_replace(':', '.', substr($nilai, 0, 5)) : '—';
        $opsiBayar = ! in_array($reservasi->status_reservasi, ['menunggu_pembayaran', 'dikonfirmasi'], true) || $lunas ? [] : ($sudahDibayar > 0
            ? [['jenis' => 'pelunasan', 'label' => 'Pelunasan', 'jumlah' => max(0, (float) $reservasi->total_reservasi - $sudahDibayar)]]
            : [['jenis' => 'dp', 'label' => 'DP', 'jumlah' => round((float) $reservasi->total_reservasi * 0.5, 2)]]);
        $opsiAktif = $opsiBayar[0] ?? null;
        $bisaUbahStatus = in_array($reservasi->status_reservasi, ['menunggu_pembayaran', 'dikonfirmasi'], true);
    @endphp

    <section>
        <p class="text-xs font-bold tracking-[0.16em] text-ink-2">RUANGMAIN / ADMIN / RESERVASI</p>

        <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="font-script text-xl text-ink-2">detail reservasi</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] tabular-nums sm:text-4xl">{{ $reservasi->kode_booking }}</h1>
                <div class="mt-4 h-1.5 w-40 -rotate-1 bg-sun"></div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($opsiAktif)
                    <button class="rounded-full border-2 border-ink bg-mint px-4 py-2 text-sm font-bold shadow-[2px_2px_0_#252525]" type="button" onclick="document.getElementById('catat-cash').showModal()">Catat pembayaran cash</button>
                @endif
                @if ($bisaUbahStatus)
                    <button class="rounded-full border-2 border-ink bg-danger px-4 py-2 text-sm font-bold shadow-[2px_2px_0_#252525]" type="button" onclick="document.getElementById('batalkan-reservasi').showModal()">Batalikan reservasi</button>
                @endif
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-5 text-xs font-bold tracking-[0.1em] text-ink-2">
            <div class="flex items-center gap-2">
                <span>RESERVASI</span>
                <span @class(['chip-status', $kelasStatus[$reservasi->status_reservasi] ?? 'border-[#b4aca0] text-ink-2'])>{{ $labelStatus[$reservasi->status_reservasi] ?? $reservasi->status_reservasi }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span>PEMBAYARAN</span>
                <span @class(['chip-status', $sisaTagihan <= 0 ? 'border-ok text-ok' : ($sudahDibayar > 0 ? 'border-warn text-warn' : 'border-bad text-bad')])>
                    {{ $sisaTagihan <= 0 ? 'Lunas' : ($sudahDibayar > 0 ? 'DP dibayar' : 'Belum dibayar') }}
                </span>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_260px]">
            <div class="min-w-0 space-y-5">
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[620px] text-left text-sm">
                        <thead class="border-b-2 border-ink text-xs uppercase tracking-[0.1em] text-ink-2">
                            <tr>
                                <th class="pr-4 pb-3 font-bold">Room</th>
                                <th class="pr-4 pb-3 font-bold">Jadwal</th>
                                <th class="pr-4 pb-3 font-bold">Durasi</th>
                                <th class="pr-4 pb-3 font-bold">Harga/jam</th>
                                <th class="pb-3 font-bold">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reservasi->detailReservasi as $detail)
                                <tr class="border-b border-[#eee9e1] last:border-0">
                                    <td class="py-3 pr-4">
                                        <span class="font-extrabold">{{ $detail->room?->nama_room ?? '—' }}</span>
                                        <span class="block text-xs text-ink-2">{{ $detail->room?->fasilitas?->nama_fasilitas }}</span>
                                    </td>
                                    <td class="py-3 pr-4 whitespace-nowrap">
                                        <span class="block">{{ $detail->tanggal?->translatedFormat('d M Y') }}</span>
                                        <span class="block text-xs text-ink-2 tabular-nums">{{ $jam($detail->jam_mulai) }}–{{ $jam($detail->jam_selesai) }}</span>
                                    </td>
                                    <td class="py-3 pr-4 whitespace-nowrap tabular-nums">{{ $detail->durasi_jam }} jam</td>
                                    <td class="py-3 pr-4 whitespace-nowrap tabular-nums">Rp{{ number_format((float) $detail->harga_per_jam, 0, ',', '.') }}</td>
                                    <td class="py-3 font-bold whitespace-nowrap tabular-nums">Rp{{ number_format((float) $detail->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    <h2 class="text-lg font-extrabold">Check-in / check-out</h2>
                    <div class="relative mt-3 overflow-x-auto">
                        <table class="w-full min-w-[520px] text-left text-sm">
                            <thead class="border-b-2 border-ink text-xs uppercase tracking-[0.1em] text-ink-2">
                                <tr>
                                    <th class="pr-4 pb-3 font-bold">Room</th>
                                    <th class="pr-4 pb-3 font-bold">Check-in</th>
                                    <th class="pb-3 font-bold">Check-out</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reservasi->detailReservasi as $detail)
                                    <tr class="border-b border-[#eee9e1] last:border-0">
                                        <td class="py-3 pr-4 font-extrabold">{{ $detail->room?->nama_room ?? '—' }}</td>
                                        <td class="py-3 pr-4 whitespace-nowrap tabular-nums">{{ $detail->waktu_checkin?->translatedFormat('d M Y, H.i') ?? '—' }}</td>
                                        <td class="py-3 whitespace-nowrap tabular-nums">{{ $detail->waktu_checkout?->translatedFormat('d M Y, H.i') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="py-6 text-ink-2" colspan="3">Belum ada room.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <a class="mt-3 inline-flex items-center rounded-full border-2 border-ink bg-sun px-4 py-2 text-sm font-bold shadow-[2px_2px_0_#252525]" href="{{ route('admin.checkin.detail', $reservasi->kode_booking) }}">Ke halaman check-in</a>
                </div>

                <div>
                    <h2 class="text-lg font-extrabold">Riwayat pembayaran</h2>
                    <div class="relative mt-3 overflow-x-auto">
                        <table class="w-full min-w-[620px] text-left text-sm">
                            <thead class="border-b-2 border-ink text-xs uppercase tracking-[0.1em] text-ink-2">
                                <tr>
                                    <th class="pr-4 pb-3 font-bold">Referensi</th>
                                    <th class="pr-4 pb-3 font-bold">Jenis</th>
                                    <th class="pr-4 pb-3 font-bold">Metode</th>
                                    <th class="pr-4 pb-3 font-bold">Nominal</th>
                                    <th class="pr-4 pb-3 font-bold">Status</th>
                                    <th class="pb-3 font-bold">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reservasi->pembayaran as $bayar)
                                    <tr class="border-b border-[#eee9e1] last:border-0">
                                        <td class="py-3 pr-4 font-bold whitespace-nowrap tabular-nums">
                                            <a class="underline underline-offset-4" href="{{ route('admin.pembayaran.detail', $bayar) }}">{{ $bayar->id_transaksi_gateway ?: 'TRX-'.str_pad((string) $bayar->id_pembayaran, 6, '0', STR_PAD_LEFT) }}</a>
                                        </td>
                                        <td class="py-3 pr-4">{{ $labelJenis[$bayar->jenis_pembayaran] ?? $bayar->jenis_pembayaran }}</td>
                                        <td class="py-3 pr-4 uppercase">{{ $bayar->metode_pembayaran }}</td>
                                        <td class="py-3 pr-4 whitespace-nowrap tabular-nums">Rp{{ number_format((float) $bayar->jumlah, 0, ',', '.') }}</td>
                                        <td class="py-3 pr-4"><span @class(['chip-status', $kelasBayar[$bayar->status_pembayaran] ?? 'border-[#b4aca0] text-ink-2'])>{{ $labelBayar[$bayar->status_pembayaran] ?? $bayar->status_pembayaran }}</span></td>
                                        <td class="py-3 whitespace-nowrap tabular-nums">{{ ($bayar->waktu_verifikasi ?? $bayar->waktu_bayar)?->translatedFormat('d M, H.i') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="py-6 text-ink-2" colspan="6">Belum ada pembayaran tercatat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <aside class="min-w-0 space-y-5">
                <div class="card-soft p-3">
                    <h2 class="text-lg font-extrabold">Pelanggan</h2>
                    <p class="mt-3 text-lg font-extrabold break-all">{{ $reservasi->pengguna?->nama }}</p>
                    <p class="text-sm break-all text-ink-2">{{ $reservasi->pengguna?->email }}</p>
                    <p class="text-sm tabular-nums text-ink-2">{{ $reservasi->pengguna?->no_hp ?? '—' }}</p>
                    @if ($reservasi->pengguna)
                        <a class="mt-3 inline-block text-sm font-bold underline underline-offset-4" href="{{ route('admin.pelanggan.detail', $reservasi->pengguna) }}">Lihat profil</a>
                    @endif
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4 text-base font-extrabold">
                        <span>Total tagihan</span>
                        <span class="tabular-nums">Rp{{ number_format((float) $reservasi->total_reservasi, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between gap-4 text-ink-2">
                        <span>Sudah dibayar</span>
                        <span class="font-bold tabular-nums text-ink">Rp{{ number_format($sudahDibayar, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between gap-4 text-ink-2">
                        <span>Sisa tagihan</span>
                        <span @class(['font-bold tabular-nums', $sisaTagihan > 0 ? 'text-bad' : 'text-ok'])>Rp{{ number_format($sisaTagihan, 0, ',', '.') }}</span>
                    </div>
                    <p class="pt-3 text-xs text-ink-2">Status bayar dihitung dari transaksi berhasil, bukan dari tombol.</p>
                    <p class="text-xs tabular-nums text-ink-2">
                        Dibuat {{ $reservasi->tanggal_dibuat?->translatedFormat('d M, H.i') }} · skema DP 50% (Rp{{ number_format(round((float) $reservasi->total_reservasi * 0.5, 2), 0, ',', '.') }})
                    </p>
                </div>
            </aside>
        </div>

        @if ($opsiAktif)
            <dialog class="m-auto max-h-[90vh] w-[min(560px,calc(100%-2rem))] overflow-y-auto rounded-[22px] border-2 border-ink bg-white p-5 text-ink shadow-[6px_6px_0_#252525] backdrop:bg-ink/40" id="catat-cash">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-extrabold">Catat pembayaran cash</h2>
                    <button class="rounded-full border-2 border-ink px-2.5 py-0.5 text-sm font-bold" type="button" onclick="this.closest('dialog').close()">✕</button>
                </div>
                <p class="mt-3 text-sm text-ink-2">Terima uang tunai dari pelanggan, lalu catat. Transaksi akan langsung diverifikasi atas nama <strong class="text-ink">Admin RUANGMAIN</strong>.</p>
                <form class="mt-5 space-y-4" method="POST" action="{{ route('admin.pembayaran.cash') }}">
                    @csrf
                    <input type="hidden" name="id_reservasi" value="{{ $reservasi->id_reservasi }}">
                    <input type="hidden" name="jenis_pembayaran" value="{{ $opsiAktif['jenis'] }}">
                    <div>
                        <label class="label-sketch" for="nominal_cash">Nominal</label>
                        <input class="field-sketch tabular-nums" id="nominal_cash" value="Rp{{ number_format((float) $opsiAktif['jumlah'], 0, ',', '.') }}" readonly>
                        <p class="mt-1 text-xs text-ink-2">{{ $opsiAktif['label'] }} · nominal dihitung server.</p>
                    </div>
                    <div>
                        <label class="label-sketch" for="catatan_verifikasi">Catatan (wajib)</label>
                        <textarea class="field-sketch" id="catatan_verifikasi" name="catatan_verifikasi" rows="3" minlength="3" maxlength="255" placeholder="mis. diterima di kasir, no. kuitansi 0123" required oninput="this.form.querySelector('[type=submit]').disabled = this.value.trim().length < 3"></textarea>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2 pt-2">
                        <button class="btn-secondary" type="button" onclick="this.closest('dialog').close()">Batal</button>
                        <button class="btn-primary" type="submit" disabled>Catat &amp; verifikasi</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($bisaUbahStatus)
            <dialog class="m-auto max-h-[90vh] w-[min(520px,calc(100%-2rem))] overflow-y-auto rounded-[22px] border-2 border-ink bg-white p-5 text-ink shadow-[6px_6px_0_#252525] backdrop:bg-ink/40" id="batalkan-reservasi">
                <h2 class="text-xl font-extrabold">Batalkan reservasi?</h2>
                <p class="mt-3 text-sm text-ink-2">
                    @if ($sudahDibayar > 0)
                        Reservasi sudah ada pembayaran. Refund harus diproses manual di luar sistem.
                    @else
                        Slot room akan dilepas dan reservasi tidak dapat dikembalikan.
                    @endif
                </p>
                <form class="mt-6 flex flex-wrap justify-end gap-2" method="POST" action="{{ route('admin.reservasi.status', $reservasi) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status_reservasi" value="dibatalkan">
                    <button class="btn-secondary" type="button" onclick="this.closest('dialog').close()">Batal</button>
                    <button class="btn-danger" type="submit">Ya, lanjutkan</button>
                </form>
            </dialog>
        @endif

        <p class="mt-8"><a class="btn-secondary inline-block" href="{{ route('admin.reservasi.index') }}">Kembali ke daftar reservasi</a></p>
    </section>
@endsection
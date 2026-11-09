@php
    $tab = [
        'pendapatan' => ['label' => 'Pendapatan', 'route' => 'admin.laporan.pendapatan'],
        'reservasi' => ['label' => 'Reservasi', 'route' => 'admin.laporan.reservasi'],
        'transaksi' => ['label' => 'Transaksi', 'route' => 'admin.laporan.transaksi'],
    ];
@endphp
<nav class="mt-6 inline-flex items-center gap-1 rounded-2xl border-2 border-ink bg-white p-1 shadow-[3px_3px_0_#252525]" aria-label="Pilih halaman laporan">
    @foreach ($tab as $kunci => $item)
        <a href="{{ route($item['route'], request()->except('periode')) }}" @class([
            'rounded-xl border-2 px-4 py-2 text-sm font-bold whitespace-nowrap transition-colors duration-150',
            'border-ink bg-sun' => $aktif === $kunci,
            'border-transparent text-ink-2 hover:bg-paper-2 hover:text-ink' => $aktif !== $kunci,
        ])>{{ $item['label'] }}</a>
    @endforeach
</nav>
<p class="mt-4 text-xs text-ink-2">Semua nominal dan grafik dihitung dari transaksi berhasil.</p>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin — RUANGMAIN')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    {{-- ponytail: collapse via checkbox + :has(), murni CSS tanpa JS dan tanpa persistensi. Ganti ke Alpine + localStorage kalau state harus diingat antar reload. --}}
    {{-- ponytail: jangan kasih transition-[grid-template-columns] di sini. Chrome gagal interpolasi 250px minmax(0,1fr) -> 72px minmax(0,1fr), jadi nilainya nyangkut di 250px dan collapse desktop mati. Animasi cukup di elemen dalam (label, ikon). --}}
    <div class="group min-h-screen lg:grid lg:grid-cols-[250px_minmax(0,1fr)] lg:has-[#sidenav:checked]:grid-cols-[72px_minmax(0,1fr)]">
        <input id="sidenav" type="checkbox" class="sr-only" aria-label="Buka tutup sidebar">
        {{-- max-lg: penting. Tanpa itu, group-has-[#sidenav:checked]:block menang lawan lg:hidden, overlay tetap tampil di desktop dan menutupi seluruh layar. --}}
        <label for="sidenav" class="fixed inset-0 z-20 hidden cursor-default bg-ink/20 max-lg:group-has-[#sidenav:checked]:block lg:hidden" aria-hidden="true"></label>
        <aside class="border-b-2 border-ink bg-paper lg:sticky lg:top-0 lg:h-screen lg:min-w-0 lg:self-start lg:overflow-x-hidden lg:overflow-y-auto lg:border-r-2 lg:border-b-0 lg:border-ink">
            <div class="relative flex h-full w-full flex-col p-4">
            <div class="relative z-40 flex items-center gap-2 lg:group-has-[#sidenav:checked]:flex-col lg:group-has-[#sidenav:checked]:items-center lg:group-has-[#sidenav:checked]:gap-3">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex min-w-0 items-center gap-2 text-xl font-extrabold tracking-tight" title="Dashboard">
                    <img class="h-6 w-6 shrink-0 object-contain" src="{{ asset('img/consoleicon.webp') }}" alt="" aria-hidden="true">
                    <span class="bg-linear-to-t from-sun/0 to-sun bg-no-repeat px-1 [background-size:100%_45%] [background-position:0_85%] group-has-[#sidenav:checked]:hidden">RUANGMAIN</span>
                </a>
                <label for="sidenav" class="ml-auto flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-xl border-2 border-ink bg-white shadow-[2px_2px_0_#252525] transition-[translate,box-shadow] duration-150 ease-out select-none hover:translate-x-0.5 hover:translate-y-0.5 hover:shadow-[0_2px_0_#252525] active:translate-x-1 active:translate-y-1 active:shadow-none lg:group-has-[#sidenav:checked]:ml-0" title="Buka tutup menu">
                    <span class="h-4 w-4 lg:hidden group-has-[#sidenav:checked]:hidden" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                    </span>
                    <span class="hidden h-4 w-4 lg:hidden group-has-[#sidenav:checked]:block" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 6 12 12M18 6 6 18" /></svg>
                    </span>
                    <span class="hidden lg:flex group-has-[#sidenav:checked]:hidden" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m14 6-6 6 6 6" /></svg>
                    </span>
                    <span class="hidden lg:group-has-[#sidenav:checked]:flex" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m10 6 6 6-6 6" /></svg>
                    </span>
                    <span class="sr-only">Buka tutup menu</span>
                </label>
            </div>
            <svg viewBox="0 0 120 10" class="mt-0.5 ml-8 hidden h-2.5 w-24 text-[#4a7fd4] group-has-[#sidenav:checked]:hidden lg:block" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M2 6q6-5 12 0t12 0 12 0 12 0 12 0 12 0 12 0 12 0" /></svg>
            <p class="mt-1 hidden font-script text-lg text-ink-2 group-has-[#sidenav:checked]:hidden lg:block">control room</p>

            @php
                $navigasi = [
                    ['admin.dashboard', 'Dashboard', 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z'],
                    ['admin.fasilitas.index', 'Fasilitas', 'M3 9l1.5-5h15L21 9M4 9v11h16V9M9 20v-6h6v6'],
                    ['admin.room.index', 'Room', 'M3 21h18M6 21V4h11v17M14 12h.01'],
                    ['admin.reservasi.index', 'Reservasi', 'M8 2v4M16 2v4M3 6h18v15H3zM3 10h18'],
                    ['admin.pembayaran.index', 'Pembayaran', 'M2 6h20v12H2zM2 10h20M6 15h2'],
                    ['admin.checkin.index', 'Check-in', 'M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3'],
                    ['admin.checkout.index', 'Check-out', 'M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4M16 17l5-5-5-5M21 12H9'],
                    ['admin.pelanggan.index', 'Pelanggan', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8M22 21v-2a4 4 0 0 0-3-3.87'],
                    ['admin.laporan.pendapatan', 'Laporan', 'M3 21h18M7 21V10M12 21V4M17 21v-7'],
                ];
            @endphp

            <nav class="absolute top-full right-5 left-5 z-30 mt-2 hidden grid-cols-2 gap-1 rounded-2xl border-2 border-ink bg-white p-2 text-sm shadow-[4px_4px_0_#252525] group-has-[#sidenav:checked]:grid lg:static lg:mt-6 lg:grid lg:w-auto lg:grid-cols-1 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none lg:group-has-[#sidenav:checked]:justify-items-center" aria-label="Navigasi Admin">
                @foreach ($navigasi as [$rute, $label, $ikon])
                    @php $aktif = request()->routeIs(str_replace('.index', '.*', $rute)); @endphp
                    <a href="{{ route($rute) }}" title="{{ $label }}" @class([
                        'flex items-center gap-3 rounded-2xl px-3.5 py-3 font-bold lg:group-has-[#sidenav:checked]:justify-center lg:group-has-[#sidenav:checked]:px-0',
                        'border-2 border-ink bg-sun shadow-[3px_3px_0_#252525]' => $aktif,
                        'hover:bg-paper-2' => ! $aktif,
                    ])>
                        <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $ikon }}" /></svg>
                        <span class="lg:group-has-[#sidenav:checked]:hidden">{{ $label }}</span>
                    </a>
                @endforeach
                <div class="col-span-full mt-2 flex items-center gap-3 border-t border-[#e5dccb] pt-3 text-sm lg:mt-4 lg:pt-4 lg:group-has-[#sidenav:checked]:justify-center">
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1" /></svg>
                    <div class="flex flex-col gap-1 leading-tight font-bold lg:group-has-[#sidenav:checked]:hidden">
                        <span>{{ auth()->user()->nama }}</span>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="font-bold text-ink-2 hover:text-bad">Keluar</button>
                        </form>
                    </div>
                </div>
            </nav>
            </div>
        </aside>
        <div class="min-w-0">
            <header class="flex items-center justify-between gap-4 border-b border-[#ede4d3] px-5 py-3 text-sm sm:px-8">
                <div class="flex items-center gap-3">
                    <p class="text-ink-2">Operasional RUANGMAIN</p>
                </div>
                <p class="font-bold">{{ auth()->user()->nama }} <span aria-hidden="true">◉</span></p>
            </header>

            <main class="mx-auto max-w-7xl px-5 py-6 sm:px-8 lg:px-10">
                @include('partials.toast')
                @if (session('galat'))
                    <div class="alert-error mb-6" role="alert"><strong>Aksi tidak dapat dilakukan.</strong> {{ session('galat') }}</div>
                @endif

                @yield('content')
            </main>

            <footer class="mx-5 border-t border-[#e5dccb] px-0 py-5 text-xs text-ink-2 sm:mx-8 lg:mx-12">
                <div class="flex justify-between gap-4"><span>© 2026 RUANGMAIN</span><strong class="text-ink">10.00–21.00</strong></div>
            </footer>
        </div>
    </div>
</body>
</html>

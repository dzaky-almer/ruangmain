<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    <header class="group border-b-2 border-ink bg-paper">
        <input id="navmenu" type="checkbox" class="sr-only" aria-label="Buka tutup menu">
        <label for="navmenu" class="fixed inset-0 z-20 hidden cursor-default bg-ink/20 group-has-[#navmenu:checked]:block lg:hidden" aria-hidden="true"></label>
        <nav class="relative mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-5 py-3 sm:px-8 sm:py-4">
            <a href="{{ route('beranda') }}" class="inline-flex shrink-0 items-center gap-2 text-lg font-extrabold tracking-tight sm:text-xl"><img class="h-6 w-6 object-contain" src="{{ asset('img/consoleicon.webp') }}" alt="" aria-hidden="true">RUANGMAIN</a>
            <label for="navmenu" class="relative z-40 ml-auto flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-xl border-2 border-ink bg-white shadow-[2px_2px_0_#252525] transition-[translate,box-shadow] duration-150 ease-out select-none hover:translate-x-0.5 hover:translate-y-0.5 hover:shadow-[0_2px_0_#252525] active:translate-x-1 active:translate-y-1 active:shadow-none lg:hidden" title="Buka tutup menu">
                <span class="h-4 w-4 group-has-[#navmenu:checked]:hidden" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                </span>
                <span class="hidden h-4 w-4 group-has-[#navmenu:checked]:block" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 6 12 12M18 6 6 18" /></svg>
                </span>
                <span class="sr-only">Buka tutup menu</span>
            </label>
            <div class="absolute top-full right-5 left-5 z-30 mt-2 hidden flex-col gap-1 rounded-2xl border-2 border-ink bg-white p-2 text-sm font-bold shadow-[4px_4px_0_#252525] group-has-[#navmenu:checked]:flex lg:static lg:inset-auto lg:mt-0 lg:flex lg:w-auto lg:flex-1 lg:flex-row lg:flex-wrap lg:items-center lg:justify-end lg:gap-3 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none">
                @auth
                    @if (auth()->user()->peran === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="btn-primary !min-h-0 !px-4 !py-2">Area Admin</a>
                    @else
                        <a href="{{ route('beranda') }}" @class(['flex items-center rounded-full border-2 px-3 py-2 max-lg:rounded-lg', 'border-ink bg-sun shadow-[3px_3px_0_#252525]' => request()->routeIs('beranda'), 'border-transparent hover:bg-paper-2' => !request()->routeIs('beranda')])>Beranda</a>
                        <a href="{{ route('pelanggan.katalog') }}" @class(['flex items-center rounded-full border-2 px-3 py-2 max-lg:rounded-lg', 'border-ink bg-sun shadow-[3px_3px_0_#252525]' => request()->routeIs('pelanggan.katalog', 'pelanggan.room.*'), 'border-transparent hover:bg-paper-2' => !request()->routeIs('pelanggan.katalog', 'pelanggan.room.*')])>Fasilitas</a>
                        <a href="{{ route('pelanggan.reservasi.baru') }}" @class(['flex items-center rounded-full border-2 px-3 py-2 max-lg:rounded-lg', 'border-ink bg-sun shadow-[3px_3px_0_#252525]' => request()->routeIs('pelanggan.reservasi.baru'), 'border-transparent hover:bg-paper-2' => !request()->routeIs('pelanggan.reservasi.baru')])>Reservasi Baru</a>
                        <a href="{{ route('pelanggan.reservasi.index') }}" @class(['flex items-center rounded-full border-2 px-3 py-2 max-lg:rounded-lg', 'border-ink bg-sun shadow-[3px_3px_0_#252525]' => request()->routeIs('pelanggan.reservasi.index', 'pelanggan.reservasi.detail', 'pelanggan.reservasi.bayar'), 'border-transparent hover:bg-paper-2' => !request()->routeIs('pelanggan.reservasi.index', 'pelanggan.reservasi.detail', 'pelanggan.reservasi.bayar')])>Riwayat Reservasi</a>
                        <a href="{{ route('pelanggan.pembayaran.index') }}" @class(['flex items-center rounded-full border-2 px-3 py-2 max-lg:rounded-lg', 'border-ink bg-sun shadow-[3px_3px_0_#252525]' => request()->routeIs('pelanggan.pembayaran.*'), 'border-transparent hover:bg-paper-2' => !request()->routeIs('pelanggan.pembayaran.*')])>Pembayaran</a>
                        <a href="{{ route('profil.edit') }}" @class(['flex items-center rounded-full border-2 px-3 py-2 max-lg:rounded-lg', 'border-ink bg-sun shadow-[3px_3px_0_#252525]' => request()->routeIs('profil.*'), 'border-transparent hover:bg-paper-2' => !request()->routeIs('profil.*')])>Profil</a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex w-full items-center rounded-full border-2 border-transparent px-3 py-2 text-left text-bad max-lg:rounded-lg hover:bg-danger">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="flex items-center rounded-full border-2 border-transparent px-3 py-2 max-lg:rounded-lg hover:bg-paper-2">Login</a>
                    <a href="{{ route('register') }}" class="btn-primary !min-h-0 !px-4 !py-2">Reservasi Sekarang</a>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        @include('partials.toast')

        @if (session('galat'))
            <div class="mx-auto max-w-7xl px-5 pt-6 sm:px-8"><div class="alert-error" role="alert"><strong>Aksi tidak dapat dilakukan.</strong> {{ session('galat') }}</div></div>
        @endif

        @yield('content')
    </main>
    <footer class="mx-5 mt-12 border-t border-[#e5dccb] py-5 text-xs text-ink-2 sm:mx-8"><div class="mx-auto flex max-w-7xl justify-between gap-4"><span>© 2026 RUANGMAIN</span><strong class="text-ink">10.00–21.00</strong></div></footer>
</body>
</html>

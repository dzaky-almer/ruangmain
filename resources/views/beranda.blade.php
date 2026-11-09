@extends('layouts.public')

@section('title', 'RUANGMAIN — Game Space')

@section('content')
    <section class="mx-auto grid max-w-6xl gap-6 px-5 py-6 sm:gap-10 sm:px-12 sm:py-8 lg:grid-cols-[1.1fr_.9fr] lg:items-center lg:py-14">
        <div>
            <p class="text-xs font-bold tracking-[.16em] text-ink-2">RUANG BUAT MAIN. WAKTU BUAT KAMU.</p>
            <h1 class="mt-4 text-4xl font-extrabold leading-[.94] tracking-[-.055em] sm:text-7xl">YOUR SPACE.<br>YOUR GAME.<br><span class="relative z-0 inline-block after:absolute after:-bottom-1 after:left-0 after:-z-10 after:h-5 after:w-full after:rotate-[-1deg] after:border-2 after:border-ink after:bg-bubble">YOUR TIME.</span></h1>
            <p class="mt-5 max-w-xl text-base leading-7 text-ink-2">Temukan room favoritmu, pilih waktu bermain, dan nikmati pengalaman gaming bersama teman dengan reservasi yang mudah.</p>
            <div class="mt-6 flex flex-wrap gap-4"><a class="btn-primary" href="#fasilitas">Lihat Fasilitas</a><a class="btn-secondary" href="{{ auth()->check() ? (auth()->user()->peran === 'admin' ? route('admin.dashboard') : route('pelanggan.katalog')) : route('register') }}">Reservasi Sekarang</a></div>
            <p class="mt-5 text-sm text-ink-2">PlayStation · Nintendo Switch · Netflix Room — Setiap hari, 10.00–21.00</p>
        </div>
        <div class="relative mx-auto w-full max-w-lg"><p class="absolute -top-8 right-4 font-script text-3xl text-ink-2">Jakarta, let's play!</p><div class="rotate-2 rounded-[30px] border-2 border-ink bg-sky p-8 shadow-[6px_6px_0_#252525] sm:p-12"><div class="grid aspect-square place-items-center overflow-hidden rounded-[24px] border-2 border-ink bg-paper"><img class="h-full w-full object-contain p-4" src="{{ asset('img/consoleicon.webp') }}" alt="Ilustrasi controller"></div><p class="mt-5 text-center font-script text-4xl">LET'S PLAY!</p></div></div>
    </section>
    <section id="fasilitas" class="border-y-2 border-ink bg-paper-2"><div class="mx-auto max-w-6xl px-5 py-6 sm:px-12"><p class="text-xs font-bold tracking-[.16em]">01 / PILIH CARA MAINMU</p><div class="mt-6 flex flex-wrap items-end justify-between gap-5"><div><h2 class="text-3xl font-extrabold tracking-[-.04em] sm:text-5xl">PICK YOUR PLAYGROUND.</h2><p class="mt-3 text-ink-2">Tiga suasana. Banyak cerita. Mana tempat mainmu?</p></div><p class="font-script text-3xl text-ink-2">pilih mood, ajak teman</p></div>            <div class="mt-6 space-y-10 sm:space-y-14">
                @foreach ([
            ['bg-sky', 'playstationroom', 'Couch co-op, no lag.', 'PlayStation', 'Room privat dengan TV 55 inci, PS5, dua DualSense, dan katalog game multiplayer favorit: EA FC, Tekken 8, It Takes Two, dan banyak lagi.', ['PS5 + 2 controller', 'TV 55"', 'AC & sofa', 'Headset tersedia'], '3 room aktif', 'mulai Rp20.000'],
            ['bg-bubble', 'nintendoroom', 'Party game, tawa ramai.', 'Nintendo', 'Satu room untuk semua ronde yang tak pernah cukup. Mario Kart, Smash Bros, dan tawa yang tidak berhenti sampai jam habis.', ['Switch + 4 joycon', 'TV 43"', 'AC & bean bag'], '2 room aktif', 'mulai Rp20.000'],
            ['bg-sun', 'netflixroom', 'movie night?', 'Netflix Room', 'Ruang nonton dengan sofa empuk, layar besar, dan sound system. Bawa akunmu sendiri atau gunakan akun room kami.', ['Smart TV 65"', 'Soundbar', 'Sofa empuk'], '2 room aktif', 'mulai Rp20.000'],
                ] as [$warna, $gambar, $tagline, $judul, $deskripsi, $chip, $roomAktif, $harga])
            <article class="grid items-center gap-6 md:grid-cols-2 md:gap-10">
                <div class="card-paper {{ $warna }} {{ $loop->even ? 'md:order-2' : '' }} hidden -rotate-1 p-4 sm:block">
                    <div class="overflow-hidden rounded-[16px] border-2 border-ink bg-paper"><img class="aspect-[4/3] w-full object-cover" src="{{ asset('img/'.$gambar.'.png') }}" alt="{{ $judul }}" loading="lazy"></div>
                </div>
                <div class="px-1 py-1 sm:px-2">
                    <p class="font-script text-2xl leading-none text-ink-2">{{ $tagline }}</p>
                    <h3 class="mt-2 text-2xl font-extrabold tracking-[-.04em] sm:text-4xl">{{ $judul }}</h3>
                    <p class="mt-3 text-sm leading-7 text-ink-2">{{ $deskripsi }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($chip as $label)
                            <span class="rounded-full border-2 border-ink px-3 py-1 text-xs font-bold">{{ $label }}</span>
                        @endforeach
                    </div>
                    <p class="mt-5 text-sm text-ink-2">{{ $roomAktif }}</p>
                    <p class="text-2xl font-extrabold">{{ $harga }} <span class="text-base font-bold text-ink-2">/ jam</span></p>
                    <a class="btn-primary mt-5" href="{{ auth()->check() && auth()->user()->peran === 'pelanggan' ? route('pelanggan.katalog') : route('register') }}">Lihat Detail <span aria-hidden="true">→</span></a>
                </div>
            </article>
        @endforeach
    </div></div></section>
    <section id="cara-reservasi" class="mx-auto max-w-7xl px-5 pt-8 pb-3 sm:px-8">
        <div class="relative inline-block">
            <h2 class="relative text-3xl font-extrabold tracking-[-.04em] sm:text-7xl">HOW TO PLAY.</h2>
            <svg class="pointer-events-none absolute -top-4 -left-3 h-[calc(100%+2rem)] w-[calc(100%+1.5rem)] text-[#e8672d]" viewBox="0 0 300 70" fill="none" preserveAspectRatio="none" aria-hidden="true">
                <path d="M8 34c-6-14 26-26 76-28 52-2 128 0 168 6 34 5 46 14 38 24-9 12-52 22-118 24-66 2-134-2-156-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
        </div>
        <ol class="mt-5 grid gap-5 sm:mt-8 sm:grid-cols-2 sm:gap-8 lg:grid-cols-5 lg:gap-10">
            @foreach ([
                ['1', 'text-sun', 'Pilih fasilitas & room', 'PlayStation, Nintendo, atau Netflix Room.'],
                ['2', 'text-sky', 'Tentukan tanggal, waktu & durasi', 'Jam selesai dihitung otomatis. Maksimal sampai 21.00.'],
                ['3', 'text-bubble', 'Konfirmasi reservasi', 'Cek ringkasan harga, lalu kode booking dibuat.'],
                ['4', 'text-mint', 'Selesaikan pembayaran', 'DP atau lunas via QRIS, atau cash ke admin.'],
                ['5', 'text-grape', 'Datang & tunjukkan kode', 'Admin scan QR-mu, lalu main!'],
            ] as [$nomor, $warna, $judul, $isi])
                <li @class(['relative', 'lg:mt-10' => $loop->even])>
                    @if (! $loop->last)
                        <svg class="pointer-events-none absolute top-1 -right-6 hidden h-12 w-14 text-ink/70 lg:block" viewBox="0 0 40 32" fill="none" aria-hidden="true">
                            <path d="M2 5c12 1 24 6 34 21M28 25l8 1-1-8" stroke="currentColor" stroke-width="2" stroke-dasharray="5 4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    @endif
                    <p class="text-3xl font-extrabold {{ $warna }} [-webkit-text-stroke:3px_#252525] [text-shadow:4px_4px_0_#252525] sm:text-6xl">{{ $nomor }}</p>
                    <h3 class="mt-2 text-lg font-extrabold sm:mt-3">{{ $judul }}</h3>
                    <p class="mt-2 text-sm leading-6 text-ink-2">{{ $isi }}</p>
                </li>
            @endforeach
        </ol>
        <a class="mt-6 inline-flex min-h-12 items-center gap-2 rounded-full border-2 border-ink bg-bubble px-6 py-3 text-sm font-extrabold shadow-[2px_4px_0_#252525] translate-x-0 translate-y-0 transition-[translate,box-shadow] duration-200 ease-out hover:translate-x-0.5 hover:translate-y-0.5 hover:shadow-[0px_2px_0_#252525] active:translate-x-1 active:translate-y-1 active:shadow-[0px_0px_0_#252525] sm:mt-8" href="#fasilitas">Mulai dari langkah 1 <span aria-hidden="true">→</span></a>
    </section>
@endsection

@extends('layouts.public')
@section('title', 'Login — RUANGMAIN')
@section('content')
<section class="mx-auto grid max-w-6xl items-center gap-10 px-5 py-8 lg:grid-cols-2 lg:gap-16 lg:py-16">
    <div class="relative mx-auto w-full max-w-[380px]">
        <img class="mx-auto h-48 w-48 -rotate-12 object-contain sm:h-64 sm:w-64" src="{{ asset('img/consoleicon.webp') }}" alt="" aria-hidden="true">
        <span class="absolute top-0 right-0 text-4xl leading-none text-sun [-webkit-text-stroke:2px_#252525] sm:text-5xl" aria-hidden="true">★</span>
        <p class="mt-2 text-center font-script text-3xl text-ink">welcome back, player</p>
    </div>
    <div class="mx-auto w-full max-w-md">
        <p class="font-script text-3xl text-ink-2">ayo lanjut main</p>
        <h1 class="mt-1 text-4xl font-extrabold tracking-[-.03em] sm:text-6xl">LOGIN</h1>
        <form class="mt-8 space-y-5" action="{{ route('login.store') }}" method="POST">
            @csrf
            <div>
                <label class="label-sketch" for="email">Email</label>
                <input class="field-sketch @error('email') !border-bad @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                @error('email')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label-sketch" for="password">Password</label>
                <div class="relative">
                    <input class="field-sketch pr-12 @error('password') !border-bad @enderror" id="password" name="password" type="password" autocomplete="current-password" required>
                    <button class="absolute top-[calc(50%+4px)] right-3 -translate-y-1/2 rounded-full p-1 text-ink-2 hover:text-ink" type="button" data-lihat-password aria-label="Tampilkan password">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    </button>
                </div>
                @error('password')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary w-full" type="submit">Login</button>
        </form>
        <p class="mt-5 text-sm text-ink-2">Belum punya akun? <a class="font-bold text-ink underline" href="{{ route('register') }}">Daftar di sini</a></p>
        <p class="mt-4 text-sm text-ink-2 underline decoration-ink-2/40">akun uji: sari@ruangmain.test / Pelanggan123!</p>
    </div>
</section>
<script>
    document.querySelector('[data-lihat-password]').addEventListener('click', function () {
        const input = document.getElementById('password');
        const lihat = input.type === 'password';
        input.type = lihat ? 'text' : 'password';
        this.setAttribute('aria-label', lihat ? 'Sembunyikan password' : 'Tampilkan password');
    });
</script>
@endsection
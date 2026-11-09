@extends('layouts.public')
@section('title', 'Registrasi — RUANGMAIN')
@section('content')
<section class="mx-auto grid max-w-6xl items-start gap-10 px-5 py-8 lg:grid-cols-2 lg:gap-16 lg:py-16">
    <div class="relative mx-auto w-full max-w-[380px] lg:sticky lg:top-24">
        <img class="mx-auto h-48 w-48 -rotate-12 object-contain sm:h-64 sm:w-64" src="{{ asset('img/consoleicon.webp') }}" alt="" aria-hidden="true">
        <span class="absolute top-0 right-0 text-4xl leading-none text-sun [-webkit-text-stroke:2px_#252525] sm:text-5xl" aria-hidden="true">★</span>
        <p class="mt-2 text-center font-script text-3xl text-ink">welcome back, player</p>
    </div>
    <div class="mx-auto w-full max-w-md">
        <p class="font-script text-3xl text-ink-2">player baru?</p>
        <h1 class="mt-1 text-4xl font-extrabold tracking-[-.03em] sm:text-6xl">DAFTAR</h1>
        <form class="mt-8 space-y-5" action="{{ route('register.store') }}" method="POST">
            @csrf
            @foreach ([['nama', 'Nama lengkap', 'text', 'nama'], ['email', 'Email', 'email', 'email'], ['no_hp', 'Nomor telepon', 'tel', 'tel']] as [$field, $label, $type, $autocomplete])
                <div>
                    <label class="label-sketch" for="{{ $field }}">{{ $label }}</label>
                    <input class="field-sketch @error($field) !border-bad @enderror" id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" autocomplete="{{ $autocomplete }}" required>
                    @error($field)<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror
                </div>
            @endforeach
            @foreach ([['password', 'Password'], ['password_confirmation', 'Konfirmasi password']] as [$field, $label])
                <div>
                    <label class="label-sketch" for="{{ $field }}">{{ $label }}</label>
                    <div class="relative">
                        <input class="field-sketch pr-12 @error($field) !border-bad @enderror" id="{{ $field }}" name="{{ $field }}" type="password" autocomplete="new-password" required>
                        <button class="absolute top-[calc(50%+4px)] right-3 -translate-y-1/2 rounded-full p-1 text-ink-2 hover:text-ink" type="button" data-lihat-password="{{ $field }}" aria-label="Tampilkan password">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                        </button>
                    </div>
                    @error($field)<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <button class="btn-primary w-full" type="submit">Buat Akun</button>
        </form>
        <p class="mt-5 text-sm text-ink-2">Sudah punya akun? <a class="font-bold text-ink underline" href="{{ route('login') }}">Login di sini</a></p>
        <p class="mt-4 text-sm text-ink-2 underline decoration-ink-2/40">password disimpan sebagai hash, tidak pernah ditampilkan</p>
    </div>
</section>
<script>
    document.querySelectorAll('[data-lihat-password]').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.lihatPassword);
            const lihat = input.type === 'password';
            input.type = lihat ? 'text' : 'password';
            this.setAttribute('aria-label', lihat ? 'Sembunyikan password' : 'Tampilkan password');
        });
    });
</script>
@endsection
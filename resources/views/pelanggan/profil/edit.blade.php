@extends('layouts.app')
@section('title', 'Profil — RUANGMAIN')
@section('content')
<section class="mx-auto max-w-5xl px-5 py-8 sm:px-8">
    <p class="font-script text-2xl text-ink-2">akunmu</p>
    <h1 class="text-3xl font-extrabold tracking-[-.04em] sm:text-5xl">Profil</h1>
    <div class="mt-3 h-2 w-28 -rotate-1 bg-sun"></div>

    <div class="mt-7 grid gap-x-6 gap-y-8 sm:mt-10 sm:grid-cols-2 sm:gap-x-14 sm:gap-y-12">
        <form action="{{ route('profil.update') }}" method="POST">
            @csrf
            @method('PUT')
            <h2 class="text-2xl font-extrabold">Data diri</h2>

            <div class="mt-6">
                <label class="label-sketch" for="nama">Nama lengkap</label>
                <input class="field-sketch @error('nama') !border-bad @enderror" id="nama" name="nama" type="text" value="{{ old('nama', auth()->user()->nama) }}" required>
                @error('nama')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5">
                <label class="label-sketch" for="email">Email</label>
                <input class="field-sketch cursor-not-allowed !bg-[#f4eee6] @error('email') !border-bad @enderror" id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" readonly>
                <p class="mt-2 text-xs text-ink-2">Email tidak dapat diubah.</p>
                @error('email')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5">
                <label class="label-sketch" for="no_hp">Nomor telepon</label>
                <input class="field-sketch @error('no_hp') !border-bad @enderror" id="no_hp" name="no_hp" type="tel" value="{{ old('no_hp', auth()->user()->no_hp) }}" required>
                @error('no_hp')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror
            </div>

            <button class="btn-primary mt-6" type="submit">Simpan profil</button>
        </form>

        <form action="{{ route('profil.password.update') }}" method="POST">
            @csrf
            @method('PUT')
            <h2 class="text-2xl font-extrabold">Ubah password</h2>

            <div class="mt-6">
                <label class="label-sketch" for="password_lama">Password lama</label>
                <input class="field-sketch @error('password_lama') !border-bad @enderror" id="password_lama" name="password_lama" type="password" autocomplete="current-password" required>
                @error('password_lama')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5">
                <label class="label-sketch" for="password">Password baru</label>
                <input class="field-sketch @error('password') !border-bad @enderror" id="password" name="password" type="password" autocomplete="new-password" required>
                <p class="mt-2 text-xs text-ink-2">Minimal 8 karakter.</p>
                @error('password')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5">
                <label class="label-sketch" for="password_confirmation">Konfirmasi password baru</label>
                <input class="field-sketch @error('password_confirmation') !border-bad @enderror" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                @error('password_confirmation')<p class="mt-2 text-sm font-semibold text-bad">{{ $message }}</p>@enderror
            </div>

            <button class="btn-primary mt-6 bg-sky" type="submit">Ubah password</button>
        </form>
    </div>
</section>
@endsection

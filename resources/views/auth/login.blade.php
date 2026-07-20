@extends('layouts.bare')

@section('title', 'Log in — Mealboard')

@section('content')
    <div class="w-full max-w-sm">
        <h1 class="mb-6 text-center font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight text-primary">
            Mealboard
        </h1>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-6">
            <h2 class="mb-4 font-[family-name:var(--font-display)] text-xl font-semibold">Log in</h2>

            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           class="input min-h-11 w-full {{ $errors->has('email') ? 'input-error' : '' }}"
                           required autofocus autocomplete="email">
                    @error('email')
                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                    <input id="password" type="password" name="password"
                           class="input min-h-11 w-full {{ $errors->has('password') ? 'input-error' : '' }}"
                           required autocomplete="current-password">
                    @error('password')
                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex min-h-11 cursor-pointer items-center gap-3">
                    <input id="remember" type="checkbox" name="remember" value="1" class="checkbox checkbox-primary">
                    <span class="text-sm">Remember me</span>
                </label>

                <button type="submit" class="btn btn-primary min-h-11 w-full">Log in</button>
            </form>
        </div>
    </div>
@endsection

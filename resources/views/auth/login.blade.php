@extends('layouts.bare')

@section('title', 'Log in — Mise')

@section('content')
    <div class="w-full max-w-sm">
        <p class="mb-6 text-center font-display text-2xl font-semibold text-primary">Mise</p>

        <x-ui.card title="Log in">
            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
                @csrf

                <x-ui.field label="Email" name="email" type="email" :value="old('email')"
                            required autofocus autocomplete="email" />
                <x-ui.field label="Password" name="password" type="password"
                            required autocomplete="current-password" />

                <label class="flex min-h-11 cursor-pointer items-center gap-3 sm:min-h-9">
                    <input id="remember" type="checkbox" name="remember" value="1" class="checkbox checkbox-primary">
                    <span>Remember me</span>
                </label>

                <x-ui.button type="submit" variant="primary" class="w-full">Log in</x-ui.button>
            </form>
        </x-ui.card>
    </div>
@endsection

@extends('layouts.bare')

@section('title', '404 — Mealboard')

@section('content')
    <x-ui.card class="w-full max-w-sm py-4 text-center">
        <p class="font-display text-5xl font-semibold text-primary" aria-hidden="true">404</p>
        <h1 class="mt-2 font-display text-xl font-semibold">Page not found</h1>
        <p class="mt-1 text-sm text-base-content/70">That page doesn't exist or has moved.</p>
        <x-ui.button variant="primary" :href="url('/')" class="mt-5">Back to Mealboard</x-ui.button>
    </x-ui.card>
@endsection

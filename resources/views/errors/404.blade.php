@extends('layouts.bare')

@section('title', '404 — Mise')

@section('content')
    <x-ui.card class="w-full max-w-sm py-4 text-center">
        <x-ui.page-header title="Page not found" pretitle="404" class="mb-2 justify-center" />
        <p class="text-sm text-base-content/70">That page doesn't exist or has moved.</p>
        <x-ui.button variant="primary" :href="url('/')" class="mt-5">Back to Mise</x-ui.button>
    </x-ui.card>
@endsection

@extends('layouts.bare')

@section('title', '500 — Mise')

@section('content')
    <x-ui.card class="w-full max-w-sm py-4 text-center">
        <x-ui.page-header title="Something went wrong" pretitle="500" class="mb-2 justify-center" />
        <p class="text-sm text-base-content/70">An unexpected error occurred. Try again in a moment.</p>
        <x-ui.button variant="primary" :href="url('/')" class="mt-5">Back to Mise</x-ui.button>
    </x-ui.card>
@endsection

@extends('layouts.bare')

@section('title', '404 — Mealboard')

@section('content')
    <div class="w-full max-w-sm rounded-2xl border border-base-300 bg-base-100 p-8 text-center">
        <p class="font-[family-name:var(--font-display)] text-5xl font-semibold text-primary" aria-hidden="true">404</p>
        <h1 class="mt-2 font-[family-name:var(--font-display)] text-xl font-semibold">Page not found</h1>
        <p class="mt-1 text-sm opacity-60">That page doesn't exist or has moved.</p>
        <a href="{{ url('/') }}" class="btn btn-primary btn-sm mt-5 min-h-11">Back to Mealboard</a>
    </div>
@endsection

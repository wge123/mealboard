@extends('layouts.bare')

@section('title', '500 — Mealboard')

@section('content')
    <div class="w-full max-w-sm rounded-box border border-base-300 bg-base-100 p-8 text-center">
        <p class="font-display text-5xl font-semibold text-primary" aria-hidden="true">500</p>
        <h1 class="mt-2 font-display text-xl font-semibold">Something went wrong</h1>
        <p class="mt-1 text-sm text-base-content/70">An unexpected error occurred. Try again in a moment.</p>
        <a href="{{ url('/') }}" class="btn btn-primary btn-sm mt-5 min-h-11">Back to Mealboard</a>
    </div>
@endsection

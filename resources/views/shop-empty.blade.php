{{-- /shop with no locked week: nothing to shop for yet. --}}
@extends('layouts.app')

@section('content')
    <x-ui.page-header title="Shop" />

    <x-ui.card>
        <x-ui.empty-state title="Lock a week to build your list" icon="tabler-shopping-cart">
            Your shopping list comes from a locked week of meals.
            <x-slot:action>
                <x-ui.button variant="primary" icon="tabler-calendar-week" href="{{ route('plan.builder') }}">Go to Plan</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    </x-ui.card>
@endsection

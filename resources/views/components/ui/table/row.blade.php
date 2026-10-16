{{-- Kit: table row. With `group` it shows and hides with that group's fold state. --}}
@props(['group' => null])

<tr {{ $attributes->merge(['class' => 'hover:bg-base-200/60']) }} @if ($group !== null) data-group="{{ $group }}" x-show="open(@js($group))" @endif>{{ $slot }}</tr>

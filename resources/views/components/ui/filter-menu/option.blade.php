{{-- Kit: one option of a filter menu. The tick box follows aria-pressed, so a caller may bind it (Alpine) as well as pass `pressed`.
     Trailing content (a count) goes in the slot. --}}
@props(['pressed' => false])

<button
    aria-pressed="{{ $pressed ? 'true' : 'false' }}"
    {{ $attributes->merge(['type' => 'button', 'class' => 'group/option flex min-h-11 w-full items-center gap-2 rounded-field px-2 text-start text-sm hover:bg-base-200 sm:min-h-9']) }}
>
    <span class="grid size-4 shrink-0 place-items-center rounded-sm border border-base-300 text-primary-content group-aria-pressed/option:border-primary group-aria-pressed/option:bg-primary" aria-hidden="true">
        <x-tabler-check class="hidden size-3 group-aria-pressed/option:block" />
    </span>
    {{ $slot }}
</button>

<?php

namespace App\Support;

/**
 * Small shared helpers for the Blade kit in resources/views/components/ui.
 */
class Kit
{
    /**
     * The attributes that disable a control while a Livewire action runs: `busy` is that action's wire:target.
     *
     * @return array<string, string>
     */
    public static function busy(?string $target): array
    {
        return $target ? ['wire:loading.attr' => 'disabled', 'wire:target' => $target] : [];
    }
}

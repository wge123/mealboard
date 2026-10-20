<?php

namespace App\Livewire;

use App\Models\RecipeSite;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * GLOSSARY.md "Recipe site" — phone-editable list of sites daily discovery may read.
 */
class RecipeSiteSettings extends Component
{
    public string $feedUrl = '';

    public string $name = '';

    public function add(): void
    {
        $this->validate([
            'feedUrl' => 'required|url:http,https|max:255|unique:recipe_sites,feed_url',
            'name' => 'required|string|max:255',
        ]);

        RecipeSite::create([
            'feed_url' => trim($this->feedUrl),
            'name' => trim($this->name),
        ]);

        $this->reset('feedUrl', 'name');
        $this->dispatch(Settings::CHANGED);
    }

    public function toggle(int $id): void
    {
        $site = RecipeSite::findOrFail($id);
        $site->update(['active' => ! $site->active]);
        $this->dispatch(Settings::CHANGED);
    }

    public function remove(int $id): void
    {
        RecipeSite::findOrFail($id)->delete();
        $this->dispatch(Settings::CHANGED);
    }

    public function render(): View
    {
        return view('livewire.recipe-site-settings', [
            'sites' => RecipeSite::query()->orderBy('name')->get(),
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Models\YouTubeChannel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * DECISIONS.md #5 — phone-editable YouTube channel list.
 */
#[Layout('layouts.app')]
#[Title('YouTube channels')]
class ChannelSettings extends Component
{
    public string $channelId = '';

    public string $name = '';

    public function add(): void
    {
        $this->validate([
            'channelId' => 'required|string|max:255|unique:youtube_channels,channel_id',
            'name' => 'required|string|max:255',
        ]);

        YouTubeChannel::create([
            'channel_id' => trim($this->channelId),
            'name' => trim($this->name),
        ]);

        $this->reset('channelId', 'name');
    }

    public function toggle(int $id): void
    {
        $channel = YouTubeChannel::findOrFail($id);
        $channel->update(['active' => ! $channel->active]);
    }

    public function remove(int $id): void
    {
        YouTubeChannel::findOrFail($id)->delete();
    }

    public function render(): View
    {
        return view('livewire.channel-settings', [
            'channels' => YouTubeChannel::query()->orderBy('name')->get(),
        ]);
    }
}

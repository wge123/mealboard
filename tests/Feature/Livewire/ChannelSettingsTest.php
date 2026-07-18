<?php

use App\Livewire\ChannelSettings;
use App\Models\User;
use App\Models\YouTubeChannel;
use Livewire\Livewire;

it('renders the channel list for an authenticated user', function () {
    YouTubeChannel::factory()->create(['name' => 'Pro Home Cooks']);

    $this->actingAs(User::factory()->create())
        ->get('/settings/channels')
        ->assertOk()
        ->assertSeeLivewire(ChannelSettings::class)
        ->assertSee('Pro Home Cooks');
});

it('redirects guests to login', function () {
    $this->get('/settings/channels')->assertRedirect('/login');
});

it('adds a channel', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(ChannelSettings::class)
        ->set('channelId', 'UCnew123')
        ->set('name', 'Ethan Chlebowski')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('channelId', '')
        ->assertSet('name', '');

    $channel = YouTubeChannel::firstWhere('channel_id', 'UCnew123');

    expect($channel)->not->toBeNull()
        ->and($channel->name)->toBe('Ethan Chlebowski')
        ->and($channel->active)->toBeTrue();
});

it('rejects a duplicate channel id and a blank name', function () {
    YouTubeChannel::factory()->create(['channel_id' => 'UCdupe']);

    Livewire::actingAs(User::factory()->create())
        ->test(ChannelSettings::class)
        ->set('channelId', 'UCdupe')
        ->set('name', '')
        ->call('add')
        ->assertHasErrors(['channelId' => 'unique', 'name' => 'required']);

    expect(YouTubeChannel::count())->toBe(1);
});

it('toggles a channel active flag', function () {
    $channel = YouTubeChannel::factory()->create();

    $component = Livewire::actingAs(User::factory()->create())->test(ChannelSettings::class);

    $component->call('toggle', $channel->id);
    expect($channel->refresh()->active)->toBeFalse();

    $component->call('toggle', $channel->id);
    expect($channel->refresh()->active)->toBeTrue();
});

it('removes a channel', function () {
    $channel = YouTubeChannel::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(ChannelSettings::class)
        ->call('remove', $channel->id);

    expect(YouTubeChannel::count())->toBe(0);
});

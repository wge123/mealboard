<?php

namespace App\Livewire;

use App\Actions\Discovery\RunRequest;
use App\Enums\RequestStatus;
use App\Models\RecipeRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Ask for one specific dish. The run is synchronous on purpose: both lanes
 * shell out to local CLIs with their own timeouts, there is no queue worker in
 * this codebase, and a household asking for dinner wants the answer in the
 * same visit rather than a job id.
 */
#[Layout('layouts.app')]
#[Title('Request a recipe')]
class RecipeRequester extends Component
{
    #[Validate('required|string|min:3|max:500')]
    public string $query = '';

    public ?int $lastRequestId = null;

    public function submit(RunRequest $runRequest): void
    {
        $this->validate();

        $request = RecipeRequest::create(['query' => trim($this->query)]);
        $this->lastRequestId = $request->id;

        $runRequest->handle($request);

        $this->query = '';
    }

    public function render(): View
    {
        return view('livewire.recipe-requester', [
            'lastRequest' => $this->lastRequestId === null
                ? null
                : RecipeRequest::find($this->lastRequestId),
            'recent' => RecipeRequest::query()
                ->whereIn('status', [RequestStatus::Completed, RequestStatus::Failed])
                ->withCount('recipes')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
        ]);
    }
}

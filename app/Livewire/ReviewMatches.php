<?php

namespace App\Livewire;

use App\Actions\Planning\ReviewProductMatch;
use App\Models\WalmartMatchProposal;
use App\Support\WalmartCartLink;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One screen to settle the matcher's proposals: a "replace" tick per row and
 * an optional "found it" URL. Submitting confirms every untouched row.
 */
#[Layout('layouts.app')]
#[Title('Review Walmart matches')]
class ReviewMatches extends Component
{
    /**
     * "Replace this product" ticks, keyed by proposal id.
     *
     * @var array<int|string, bool>
     */
    public array $replace = [];

    /**
     * Optional "found it" product URLs, keyed by proposal id.
     *
     * @var array<int|string, string>
     */
    public array $foundUrls = [];

    public ?string $summary = null;

    /**
     * Settle every listed proposal in one go. Every pasted URL is checked
     * first, so one bad paste writes nothing.
     */
    public function submit(ReviewProductMatch $review, WalmartCartLink $cartLink): void
    {
        $proposals = $this->proposals();
        $found = [];

        foreach ($proposals as $proposal) {
            $url = trim($this->foundUrls[$proposal->id] ?? '');

            if ($url === '') {
                continue;
            }

            if (! filter_var($url, FILTER_VALIDATE_URL) || $cartLink->itemId($url) === null) {
                $this->addError('foundUrls.'.$proposal->id, 'Paste a walmart.com/ip/... product URL.');

                continue;
            }

            $found[$proposal->id] = $url;
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $counts = ['confirmed' => 0, 'replaced' => 0, 'rejected' => 0];

        foreach ($proposals as $proposal) {
            $url = $found[$proposal->id] ?? null;
            $tick = (bool) ($this->replace[$proposal->id] ?? false);

            $review->handle($proposal, $tick, $url);

            $counts[match (true) {
                $url !== null => 'replaced',
                $tick => 'rejected',
                default => 'confirmed',
            }]++;
        }

        $this->replace = [];
        $this->foundUrls = [];
        $this->summary = "{$counts['confirmed']} confirmed, {$counts['replaced']} replaced with your URL, {$counts['rejected']} rejected.";
    }

    public function render(): View
    {
        return view('livewire.review-matches', ['proposals' => $this->proposals()]);
    }

    /**
     * Low-confidence rows first: they are the ones most worth a look.
     *
     * @return Collection<int, WalmartMatchProposal>
     */
    private function proposals(): Collection
    {
        return WalmartMatchProposal::query()
            ->with('ingredient')
            ->get()
            ->sortBy(fn (WalmartMatchProposal $proposal) => [
                match ($proposal->confidence) {
                    'low' => 0,
                    'medium' => 1,
                    null => 2,
                    default => 3,
                },
                $proposal->ingredient->name,
            ])
            ->values();
    }
}

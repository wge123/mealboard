<?php

namespace App\Livewire;

use App\Actions\Planning\ReviewProductMatch;
use App\Models\WalmartMatchProposal;
use App\Support\WalmartCartLink;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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
     * The proposals the page showed (id => updated_at), so a submit settles
     * what the user looked at and never a guess that arrived or changed after.
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $shown = [];

    public function mount(): void
    {
        $this->shown = $this->snapshot($this->proposals());
    }

    /**
     * Settle every shown proposal in one go. Every pasted URL is checked
     * first, so one bad paste writes nothing. A proposal created or changed
     * since the page rendered is left for the next look.
     */
    public function submit(ReviewProductMatch $review, WalmartCartLink $cartLink): void
    {
        $proposals = $this->proposals()->filter(
            fn (WalmartMatchProposal $proposal) => ($this->shown[$proposal->id] ?? null) === $this->stamp($proposal),
        );
        $found = [];

        foreach ($proposals as $proposal) {
            $raw = $this->foundUrls[$proposal->id] ?? '';
            $url = is_string($raw) ? trim($raw) : '';

            if ($url === '' && $raw !== '' && $raw !== null) {
                $this->addError('foundUrls.'.$proposal->id, 'Paste a walmart.com/ip/... product URL.');

                continue;
            }

            if ($url === '') {
                continue;
            }

            if (! $cartLink->isProductUrl($url)) {
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

        $left = $this->proposals()->count();

        $this->replace = [];
        $this->foundUrls = [];
        $this->summary = "{$counts['confirmed']} confirmed, {$counts['replaced']} replaced with your URL, {$counts['rejected']} rejected."
            .($left > 0 ? " {$left} new or changed since you opened this page: look at them next." : '');
        $this->shown = $this->snapshot($this->proposals());
    }

    /**
     * @param  Collection<int, WalmartMatchProposal>  $proposals
     * @return array<int, string>
     */
    private function snapshot(Collection $proposals): array
    {
        return $proposals->mapWithKeys(fn (WalmartMatchProposal $proposal) => [$proposal->id => $this->stamp($proposal)])->all();
    }

    private function stamp(WalmartMatchProposal $proposal): string
    {
        return $proposal->updated_at->format('Y-m-d H:i:s.u').'|'.$proposal->product_url.'|'.$proposal->confidence;
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

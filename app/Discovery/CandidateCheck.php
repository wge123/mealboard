<?php

namespace App\Discovery;

use Illuminate\Support\Facades\Log;

/**
 * Outcome of CandidateValidator::check: either the repaired, shaped
 * candidate or a list of human-readable errors (suitable to show a model
 * in a retry prompt). Never both.
 */
final readonly class CandidateCheck
{
    /**
     * @param  ?array<string, mixed>  $candidate
     * @param  list<string>  $errors
     */
    public function __construct(
        public ?array $candidate,
        public array $errors = [],
    ) {}

    public function passes(): bool
    {
        return $this->candidate !== null;
    }

    /**
     * The candidate, or null after logging why it is dropped (a lane that
     * drops a failing candidate rather than retrying or recording it).
     *
     * @return ?array<string, mixed>
     */
    public function candidateOrDrop(string $title): ?array
    {
        if (! $this->passes()) {
            Log::warning("discovery: discarded candidate \"{$title}\": ".implode('; ', $this->errors));
        }

        return $this->candidate;
    }
}

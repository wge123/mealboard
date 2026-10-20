<?php

namespace App\Discovery;

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
}

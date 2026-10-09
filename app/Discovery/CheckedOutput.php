<?php

namespace App\Discovery;

/**
 * What RetryOnce hands back: the model's last output and the check errors
 * still standing against it (empty when it passes).
 */
final readonly class CheckedOutput
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public string $output,
        public array $errors,
    ) {}

    public function passes(): bool
    {
        return $this->errors === [];
    }
}

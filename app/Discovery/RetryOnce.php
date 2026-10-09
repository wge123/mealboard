<?php

namespace App\Discovery;

use Closure;

/**
 * Call claude, check the output, and on failure call once more with the
 * original prompt plus the model's own output and the check's errors. A
 * second failure is returned, not thrown: each path decides what a final
 * failure means (drop the candidate, record the error on a row).
 */
class RetryOnce
{
    public function __construct(private ClaudeCli $claude) {}

    /**
     * @param  Closure(string): list<string>  $errorsOf  check an output; empty list = passes
     */
    public function run(string $prompt, Closure $errorsOf): CheckedOutput
    {
        $output = $this->claude->run($prompt);
        $errors = $errorsOf($output);

        if ($errors === []) {
            return new CheckedOutput($output, []);
        }

        $output = $this->claude->run($this->retryPrompt($prompt, $output, $errors));

        return new CheckedOutput($output, $errorsOf($output));
    }

    /**
     * @param  list<string>  $errors
     */
    private function retryPrompt(string $prompt, string $previousOutput, array $errors): string
    {
        $list = implode("\n", array_map(fn (string $error) => "- {$error}", $errors));

        return <<<RETRY
        {$prompt}

        Your previous answer to the request above was:
        {$previousOutput}

        It failed these checks:
        {$list}

        Answer the request again, fixing every problem listed. Respond with STRICT JSON only, in the same format as before.
        RETRY;
    }
}

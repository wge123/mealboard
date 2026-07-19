<?php

namespace App\Discovery;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Shared `claude -p <prompt> --model sonnet` invocation (DECISIONS.md #2 —
 * local CLI, no API key) plus JSON isolation for its chatty output.
 */
class ClaudeCli
{
    public function run(string $prompt): string
    {
        $result = Process::timeout(600)->run([
            $this->binary(), '-p', $prompt, '--model', 'sonnet',
        ]);

        if ($result->failed()) {
            throw new RuntimeException(
                'claude CLI failed (exit '.$result->exitCode().'): '
                .trim($result->errorOutput() !== '' ? $result->errorOutput() : $result->output()),
            );
        }

        return $result->output();
    }

    private function binary(): string
    {
        $configured = config('mealboard.claude_bin');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $found = (new ExecutableFinder)->find('claude');

        if ($found === null) {
            throw new RuntimeException(
                'claude CLI not found on PATH — install Claude Code or set mealboard.claude_bin.',
            );
        }

        return $found;
    }

    /**
     * Strip fences/preamble and isolate the outermost JSON array or object.
     */
    public function extractJson(string $output): string
    {
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $output, $m)) {
            $output = $m[1];
        }

        $closerByOpener = ['[' => ']', '{' => '}'];
        $start = false;

        foreach (array_keys($closerByOpener) as $opener) {
            $position = strpos($output, $opener);

            if ($position !== false && ($start === false || $position < $start)) {
                $start = $position;
            }
        }

        if ($start === false) {
            return trim($output); // Let json_decode fail upstream.
        }

        $end = strrpos($output, $closerByOpener[$output[$start]]);

        if ($end === false || $end < $start) {
            return trim($output);
        }

        return substr($output, $start, $end - $start + 1);
    }
}

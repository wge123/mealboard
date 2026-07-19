<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal GitHub contents-API client for the second-brain vault repo:
 * brain:sync reads preference notes, PublishMealsMarkdown writes meal
 * markdown. Fails fast at execution time when GITHUB_PAT is missing.
 */
class GitHubClient
{
    private const BASE = 'https://api.github.com';

    /**
     * Raw file content at a vault-relative path, or null when the file
     * does not exist in the repo (404).
     */
    public function getRawFile(string $repo, string $path): ?string
    {
        $response = $this->request()
            ->withHeader('Accept', 'application/vnd.github.raw+json')
            ->get($this->contentsUrl($repo, $path));

        if ($response->status() === 404) {
            return null;
        }

        return $response->throw()->body();
    }

    private function contentsUrl(string $repo, string $path): string
    {
        return self::BASE."/repos/{$repo}/contents/{$path}";
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->token());
    }

    private function token(): string
    {
        $token = config('mealboard.github_pat');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException(
                'GITHUB_PAT not set — add a GitHub personal access token with contents access to .env before syncing or publishing.',
            );
        }

        return $token;
    }
}

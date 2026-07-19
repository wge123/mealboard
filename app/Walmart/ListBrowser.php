<?php

namespace App\Walmart;

/**
 * The seam between walmart:push-list and the real browser: the command talks
 * to this interface only, so tests never launch Chrome. The one production
 * implementation is ChromeListBrowser (chrome-php, headed, persistent
 * profile); its selectors are PROVISIONAL until the first live supervised run
 * (docs/tier3-list.md).
 */
interface ListBrowser
{
    /** Launch the headed browser and open the Walmart list page. */
    public function open(string $listUrl): void;

    /**
     * Type the cleaned keywords into the list's add-item input and confirm.
     *
     * @throws \RuntimeException on any selector miss ("selector miss: …") —
     *                           the run must abort, never continue silently.
     */
    public function addItem(string $keywords): void;

    /** Close the browser (safe to call even when open() failed). */
    public function close(): void;
}

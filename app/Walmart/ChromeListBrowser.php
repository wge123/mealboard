<?php

namespace App\Walmart;

use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Dom\Node;
use HeadlessChromium\Page;
use RuntimeException;
use Throwable;

/**
 * Drives the Walmart list page in a HEADED Chrome (chrome-php) using a
 * persistent, already-logged-in profile. Never touches cart or checkout.
 *
 * Every selector here is PROVISIONAL — Walmart's list page markup has not
 * been verified. The first live supervised run inspects the real UI and
 * updates these constants together with docs/tier3-list.md. Every selector
 * interaction is wrapped: a miss throws a "selector miss: …" RuntimeException
 * so the run aborts loudly instead of continuing half-blind.
 */
class ChromeListBrowser implements ListBrowser
{
    /** PROVISIONAL — the list page's add-item text input (docs/tier3-list.md). */
    public const ADD_ITEM_INPUT = 'input[placeholder*="Add an item" i]';

    private ?ProcessAwareBrowser $browser = null;

    private ?Page $page = null;

    public function __construct(private string $profileDir) {}

    public function open(string $listUrl): void
    {
        $this->browser = (new BrowserFactory)->createBrowser([
            'headless' => false, // hard rule: headed, human-visible
            'userDataDir' => $this->profileDir,
            'windowSize' => [1280, 900],
        ]);

        $this->page = $this->browser->createPage();
        $this->page->navigate($listUrl)->waitForNavigation(Page::NETWORK_IDLE, 30_000);
    }

    public function addItem(string $keywords): void
    {
        $input = $this->find(self::ADD_ITEM_INPUT, 'add-item input');

        try {
            $input->click();
            $input->sendKeys($keywords);
            $this->page()->keyboard()->typeRawKey('Enter');

            // PROVISIONAL confirm: Enter submits the input. Give the page a
            // beat to register the add before the next item re-finds the
            // (possibly re-rendered) input.
            usleep(750_000);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'selector miss: '.self::ADD_ITEM_INPUT." (add-item interaction failed: {$e->getMessage()})",
                previous: $e,
            );
        }
    }

    public function close(): void
    {
        try {
            $this->browser?->close();
        } finally {
            $this->browser = null;
            $this->page = null;
        }
    }

    /** Wait for a selector and return its node, or throw a loud selector miss. */
    private function find(string $selector, string $what): Node
    {
        try {
            $this->page()->waitUntilContainsElement($selector, 15_000);
            $node = $this->page()->dom()->querySelector($selector);
        } catch (Throwable) {
            $node = null;
        }

        if ($node === null) {
            throw new RuntimeException("selector miss: {$selector} ({$what})");
        }

        return $node;
    }

    private function page(): Page
    {
        if ($this->page === null) {
            throw new RuntimeException('Browser not opened — call open() first.');
        }

        return $this->page;
    }
}

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
 * Selectors and flow VERIFIED against the real list UI in the first
 * supervised run (2026-07-21) — see docs/tier3-list.md for the findings and
 * hazards. Every selector interaction is wrapped: a miss throws a
 * "selector miss: …" RuntimeException so the run aborts loudly instead of
 * continuing half-blind.
 */
class ChromeListBrowser implements ListBrowser
{
    /** VERIFIED — the list page's add-item text input (docs/tier3-list.md). */
    public const ADD_ITEM_INPUT = 'input[placeholder*="Add an item" i], input[aria-label*="Add an item" i]';

    /** VERIFIED — exact text of the button that reveals the add-item input. */
    public const ADD_ITEM_BUTTON_TEXT = 'Add an item';

    /** VERIFIED — captcha dialog heading; hard-stop condition. */
    public const CAPTCHA_HEADING = 'Robot or human?';

    private ?ProcessAwareBrowser $browser = null;

    private ?Page $page = null;

    private ?int $lastCount = null;

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

        $this->guardCaptcha();
        $this->revealAddInput();
        $this->lastCount = $this->itemCount();
    }

    public function addItem(string $keywords): void
    {
        $this->guardCaptcha();

        // Re-focus before EVERY entry: bulk adds silently drop focus after a
        // handful of items (verified live) and keystrokes then go nowhere.
        $input = $this->find(self::ADD_ITEM_INPUT, 'add-item input');

        try {
            $input->click();
            $input->sendKeys($keywords);
            $this->page()->keyboard()->typeRawKey('Enter');

            // Pace ≥1s, then verify the "| N items" count actually grew —
            // a non-incrementing count is the loud-abort condition
            // (silent focus loss produces no error otherwise).
            sleep(1);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'selector miss: '.self::ADD_ITEM_INPUT." (add-item interaction failed: {$e->getMessage()})",
                previous: $e,
            );
        }

        $count = $this->itemCount();

        if ($this->lastCount !== null && $count !== null && $count <= $this->lastCount) {
            throw new RuntimeException(
                "add not registered: item count stayed at {$count} after typing \"{$keywords}\" — "
                .'input likely lost focus; re-run (already-added items may duplicate; see docs/tier3-list.md)',
            );
        }

        $this->lastCount = $count;
    }

    public function close(): void
    {
        try {
            $this->browser?->close();
        } finally {
            $this->browser = null;
            $this->page = null;
            $this->lastCount = null;
        }
    }

    /** Click the "Add an item" button when the input isn't already present. */
    private function revealAddInput(): void
    {
        $present = $this->evaluate(
            'document.querySelector(\'input[placeholder*="Add an item" i], input[aria-label*="Add an item" i]\') !== null',
        );

        if ($present === true) {
            return;
        }

        $clicked = $this->evaluate(<<<'JS'
            (() => {
                const btn = Array.from(document.querySelectorAll('button'))
                    .find(b => b.innerText.trim() === 'Add an item');
                if (!btn) return false;
                btn.click();
                return true;
            })()
            JS);

        if ($clicked !== true) {
            throw new RuntimeException('selector miss: button "'.self::ADD_ITEM_BUTTON_TEXT.'" (reveal add-item input)');
        }

        $this->find(self::ADD_ITEM_INPUT, 'add-item input after reveal');
    }

    /** The "| N items" header count, or null when the header isn't found. */
    private function itemCount(): ?int
    {
        $result = $this->evaluate(
            "(document.querySelector('main')?.innerText.match(/\\|\\s*(\\d+)\\s*items?/) || [])[1] ?? null",
        );

        return is_numeric($result) ? (int) $result : null;
    }

    /** Hard-stop on Walmart's press-and-hold captcha — a human must pass it. */
    private function guardCaptcha(): void
    {
        $hit = $this->evaluate(
            "document.body.innerText.includes('".self::CAPTCHA_HEADING."')",
        );

        if ($hit === true) {
            throw new RuntimeException(
                'captcha: "'.self::CAPTCHA_HEADING.'" dialog is showing — complete it by hand in the '
                .'browser window, then re-run (docs/tier3-list.md hazard 1)',
            );
        }
    }

    private function evaluate(string $js): mixed
    {
        try {
            return $this->page()->evaluate($js)->getReturnValue();
        } catch (Throwable) {
            return null;
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

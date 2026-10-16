<?php

use App\Models\User;

it('renders the nav icons from the Tabler package, hidden from screen readers', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get('/plan')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('icon-tabler-calendar-week')
        ->toContain('icon-tabler-circle-check')
        ->toContain('icon-tabler-dots')
        ->toContain('aria-hidden="true"');
});

it('leaves no emoji or glyph standing in for an icon in any view', function () {
    $glyphs = ['★', '☆', '✕', '✓', '↗', '↶', '▶', '🎉', '&larr;'];

    $offenders = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        foreach ($glyphs as $glyph) {
            if (str_contains(file_get_contents($file->getPathname()), $glyph)) {
                $offenders[] = $file->getFilename().' has '.$glyph;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('has no arbitrary display-font class left in any view', function () {
    $offenders = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (str_ends_with($file->getFilename(), '.blade.php')
            && str_contains(file_get_contents($file->getPathname()), 'font-[family-name')) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

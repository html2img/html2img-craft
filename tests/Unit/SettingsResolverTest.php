<?php

use html2img\ogimages\support\SettingsResolver;

$defaults = [
    'template' => 'og-images/_default',
    'width' => 1200,
    'height' => 630,
    'dpi' => 2,
];

it('returns the defaults when a section has no override', function () use ($defaults) {
    expect(SettingsResolver::resolve($defaults, [], 'blog'))->toBe($defaults);
});

it('returns the defaults when no section is given', function () use ($defaults) {
    $overrides = ['blog' => ['template' => '_og/blog']];

    expect(SettingsResolver::resolve($defaults, $overrides, null))->toBe($defaults);
});

it('applies overrides from a map keyed by section handle', function () use ($defaults) {
    $overrides = [
        'blog' => ['template' => '_og/blog', 'width' => 1600, 'height' => 900],
    ];

    expect(SettingsResolver::resolve($defaults, $overrides, 'blog'))->toBe([
        'template' => '_og/blog',
        'width' => 1600,
        'height' => 900,
        'dpi' => 2,
    ]);
});

it('applies overrides from control panel table rows', function () use ($defaults) {
    $overrides = [
        ['section' => 'news', 'template' => '', 'width' => '1080', 'height' => '1080'],
        ['section' => 'blog', 'template' => '_og/blog', 'width' => '', 'height' => ''],
    ];

    expect(SettingsResolver::resolve($defaults, $overrides, 'news'))->toBe([
        'template' => 'og-images/_default',
        'width' => 1080,
        'height' => 1080,
        'dpi' => 2,
    ])->and(SettingsResolver::resolve($defaults, $overrides, 'blog'))->toBe([
        'template' => '_og/blog',
        'width' => 1200,
        'height' => 630,
        'dpi' => 2,
    ]);
});

it('ignores blank and null override values', function () use ($defaults) {
    $overrides = [
        'blog' => ['template' => '', 'width' => null, 'height' => '', 'dpi' => null],
    ];

    expect(SettingsResolver::resolve($defaults, $overrides, 'blog'))->toBe($defaults);
});

it('leaves other sections untouched', function () use ($defaults) {
    $overrides = ['blog' => ['width' => 1600]];

    expect(SettingsResolver::resolve($defaults, $overrides, 'news'))->toBe($defaults);
});

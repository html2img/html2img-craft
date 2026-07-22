<?php

use html2img\ogimages\support\MetaTags;

function candidate(?string $url, array $extra = []): array
{
    return array_merge([
        'url' => $url,
        'width' => null,
        'height' => null,
        'alt' => null,
        'type' => null,
    ], $extra);
}

it('picks the first candidate with a url', function () {
    $picked = MetaTags::pick([
        candidate(null),
        candidate('https://i.html2img.com/generated.png'),
        candidate('https://example.com/fallback.png'),
    ]);

    expect($picked['url'])->toBe('https://i.html2img.com/generated.png');
});

it('prefers the editor image over the generated one', function () {
    $picked = MetaTags::pick([
        candidate('https://example.com/editor-choice.jpg', ['type' => 'image/jpeg']),
        candidate('https://i.html2img.com/generated.png'),
    ]);

    expect($picked['url'])->toBe('https://example.com/editor-choice.jpg')
        ->and($picked['type'])->toBe('image/jpeg');
});

it('skips blank urls and returns null when nothing matches', function () {
    expect(MetaTags::pick([candidate(''), candidate(null)]))->toBeNull()
        ->and(MetaTags::pick([]))->toBeNull();
});

it('renders the full tag set', function () {
    $html = MetaTags::render([
        'url' => 'https://i.html2img.com/abc.png',
        'width' => 2400,
        'height' => 1260,
        'alt' => 'Ten ways to ship faster',
        'type' => 'image/png',
    ]);

    expect($html)
        ->toContain('<meta property="og:image" content="https://i.html2img.com/abc.png">')
        ->toContain('<meta property="og:image:width" content="2400">')
        ->toContain('<meta property="og:image:height" content="1260">')
        ->toContain('<meta property="og:image:alt" content="Ten ways to ship faster">')
        ->toContain('<meta property="og:image:type" content="image/png">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('<meta name="twitter:image" content="https://i.html2img.com/abc.png">');
});

it('omits optional tags when values are missing', function () {
    $html = MetaTags::render(candidate('https://i.html2img.com/abc.png'));

    expect($html)
        ->toContain('og:image')
        ->toContain('twitter:card')
        ->not->toContain('og:image:width')
        ->not->toContain('og:image:alt')
        ->not->toContain('og:image:type');
});

it('escapes attribute values', function () {
    $html = MetaTags::render(candidate('https://example.com/a.png?x=1&y=2', [
        'alt' => 'Bits & "pieces"',
    ]));

    expect($html)
        ->toContain('https://example.com/a.png?x=1&amp;y=2')
        ->toContain('Bits &amp; &quot;pieces&quot;')
        ->not->toContain('y=2"><script');
});

it('returns an empty string for a missing image', function () {
    expect(MetaTags::render(null))->toBe('');
});

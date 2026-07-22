<?php

namespace html2img\ogimages\support;

/**
 * Builds the Open Graph and Twitter meta tags for a resolved image, and picks
 * the winning image from the output cascade.
 */
final class MetaTags
{
    /**
     * Returns the first candidate with a usable URL. Candidates are checked in
     * the order given: editor-chosen image, generated image, site fallback.
     *
     * @param array<int, array{url: ?string, width: ?int, height: ?int, alt: ?string, type: ?string}> $candidates
     * @return array{url: string, width: ?int, height: ?int, alt: ?string, type: ?string}|null
     */
    public static function pick(array $candidates): ?array
    {
        foreach ($candidates as $candidate) {
            $url = $candidate['url'] ?? null;

            if (is_string($url) && $url !== '') {
                return [
                    'url' => $url,
                    'width' => $candidate['width'] ?? null,
                    'height' => $candidate['height'] ?? null,
                    'alt' => $candidate['alt'] ?? null,
                    'type' => $candidate['type'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Renders the tag set for one image. Returns an empty string when there is
     * no image, so templates can call it unconditionally.
     *
     * @param array{url: string, width: ?int, height: ?int, alt: ?string, type: ?string}|null $image
     */
    public static function render(?array $image): string
    {
        if ($image === null) {
            return '';
        }

        $lines = [self::tag('property', 'og:image', $image['url'])];

        if (!empty($image['width'])) {
            $lines[] = self::tag('property', 'og:image:width', (string)$image['width']);
        }

        if (!empty($image['height'])) {
            $lines[] = self::tag('property', 'og:image:height', (string)$image['height']);
        }

        if (!empty($image['alt'])) {
            $lines[] = self::tag('property', 'og:image:alt', $image['alt']);
        }

        if (!empty($image['type'])) {
            $lines[] = self::tag('property', 'og:image:type', $image['type']);
        }

        $lines[] = self::tag('name', 'twitter:card', 'summary_large_image');
        $lines[] = self::tag('name', 'twitter:image', $image['url']);

        return implode("\n", $lines);
    }

    private static function tag(string $attribute, string $key, string $content): string
    {
        $escaped = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf('<meta %s="%s" content="%s">', $attribute, $key, $escaped);
    }
}

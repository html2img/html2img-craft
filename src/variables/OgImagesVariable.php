<?php

namespace html2img\ogimages\variables;

use craft\elements\Entry;
use html2img\ogimages\Plugin;
use Twig\Markup;

/**
 * Exposes the plugin to Twig as `craft.ogImages`.
 */
class OgImagesVariable
{
    /** Emits the Open Graph and Twitter meta tags for an entry. */
    public function meta(Entry $entry): Markup
    {
        return Plugin::getInstance()->images->meta($entry);
    }

    /** The resolved image URL for an entry, or null when there is none. */
    public function url(Entry $entry): ?string
    {
        return Plugin::getInstance()->images->url($entry);
    }
}

<?php

namespace html2img\ogimages\services;

use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Template;
use html2img\ogimages\models\Settings;
use html2img\ogimages\Plugin;
use html2img\ogimages\records\ImageRecord;
use html2img\ogimages\support\MetaTags;
use Twig\Markup;
use yii\base\Component;

/**
 * Owns the plugin's storage table and resolves the output cascade: the
 * editor's chosen image first, then the generated one, then the fallback.
 */
class Images extends Component
{
    public function getRecord(int $elementId, int $siteId): ?ImageRecord
    {
        return ImageRecord::findOne(['elementId' => $elementId, 'siteId' => $siteId]);
    }

    /**
     * @param array{url: ?string, assetId: ?int, inputHash: string, width: int, height: int} $attributes
     */
    public function store(int $elementId, int $siteId, array $attributes): ImageRecord
    {
        $record = $this->getRecord($elementId, $siteId) ?? new ImageRecord([
            'elementId' => $elementId,
            'siteId' => $siteId,
        ]);

        $record->url = $attributes['url'];
        $record->assetId = $attributes['assetId'];
        $record->inputHash = $attributes['inputHash'];
        $record->width = $attributes['width'];
        $record->height = $attributes['height'];
        $record->save(false);

        return $record;
    }

    /** The URL of the generated image for an entry, whichever storage mode produced it. */
    public function generatedUrl(Entry $entry): ?string
    {
        $elementId = $entry->getCanonicalId() ?? $entry->id;

        if ($elementId === null) {
            return null;
        }

        $record = $this->getRecord($elementId, $entry->siteId);

        if ($record === null) {
            return null;
        }

        if ($record->assetId !== null) {
            $asset = Asset::find()->id($record->assetId)->one();

            return $asset?->getUrl();
        }

        return $record->url;
    }

    /** Renders the full Open Graph and Twitter tag set for an entry. */
    public function meta(Entry $entry): Markup
    {
        // In seo-field mode the SEO plugin owns the head, so emit nothing.
        if (Plugin::getInstance()->getSettings()->integration === Settings::INTEGRATION_SEO_FIELD) {
            return Template::raw('');
        }

        $image = MetaTags::pick($this->candidates($entry));

        return Template::raw(MetaTags::render($image));
    }

    /** The image URL the meta tags would use, after the cascade. */
    public function url(Entry $entry): ?string
    {
        $image = MetaTags::pick($this->candidates($entry));

        return $image['url'] ?? null;
    }

    /**
     * @return array<int, array{url: ?string, width: ?int, height: ?int, alt: ?string, type: ?string}>
     */
    public function candidates(Entry $entry): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $alt = Plugin::getInstance()->renderer->headlineFor($entry);
        $candidates = [];

        $editorAsset = $this->editorAsset($entry, $settings->imageField);
        if ($editorAsset !== null) {
            $candidates[] = [
                'url' => $editorAsset->getUrl(),
                'width' => $editorAsset->getWidth(),
                'height' => $editorAsset->getHeight(),
                'alt' => $editorAsset->alt ?: $alt,
                'type' => $editorAsset->getMimeType(),
            ];
        }

        $candidates[] = $this->generatedCandidate($entry, $alt);

        $fallback = $settings->fallbackImageUrl();
        if ($fallback !== '') {
            $candidates[] = [
                'url' => $fallback,
                'width' => null,
                'height' => null,
                'alt' => $alt,
                'type' => self::mimeFromUrl($fallback),
            ];
        }

        return array_filter($candidates);
    }

    /**
     * @return array{url: ?string, width: ?int, height: ?int, alt: ?string, type: ?string}|null
     */
    private function generatedCandidate(Entry $entry, string $alt): ?array
    {
        $elementId = $entry->getCanonicalId() ?? $entry->id;

        if ($elementId === null) {
            return null;
        }

        $record = $this->getRecord($elementId, $entry->siteId);

        if ($record === null) {
            return null;
        }

        if ($record->assetId !== null) {
            $asset = Asset::find()->id($record->assetId)->one();

            if ($asset === null) {
                return null;
            }

            return [
                'url' => $asset->getUrl(),
                'width' => $asset->getWidth(),
                'height' => $asset->getHeight(),
                'alt' => $alt,
                'type' => $asset->getMimeType(),
            ];
        }

        return [
            'url' => $record->url,
            'width' => $record->width,
            'height' => $record->height,
            'alt' => $alt,
            'type' => 'image/png',
        ];
    }

    private function editorAsset(Entry $entry, string $fieldHandle): ?Asset
    {
        if ($fieldHandle === '') {
            return null;
        }

        $layout = $entry->getFieldLayout();

        if ($layout === null || $layout->getFieldByHandle($fieldHandle) === null) {
            return null;
        }

        $value = $entry->getFieldValue($fieldHandle);

        if (!is_object($value) || !method_exists($value, 'one')) {
            return null;
        }

        $asset = $value->one();

        return $asset instanceof Asset ? $asset : null;
    }

    private static function mimeFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };
    }
}

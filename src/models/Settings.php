<?php

namespace html2img\ogimages\models;

use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;
use html2img\ogimages\support\SettingsResolver;

class Settings extends Model
{
    public const STORAGE_CDN = 'cdn';
    public const STORAGE_ASSET = 'asset';
    public const INTEGRATION_STANDALONE = 'standalone';
    public const INTEGRATION_SEO_FIELD = 'seo-field';

    /** API key, usually an environment variable reference. */
    public ?string $apiKey = '$HTML2IMG_API_KEY';

    /** @var string[] Handles of the sections images are generated for. */
    public array $sections = [];

    /** The Twig template rendered for each card. */
    public string $template = 'og-images/_default';

    /** Viewport width in CSS pixels. */
    public int $width = 1200;

    /** Viewport height in CSS pixels. */
    public int $height = 630;

    /** Device pixel ratio multiplier. 2 keeps text crisp on retina displays. */
    public int $dpi = 2;

    /** @var array<int|string, array<string, mixed>> Per-section overrides for template, width, height and dpi. */
    public array $overrides = [];

    /** Where generated images live: `cdn` stores the API URL, `asset` downloads the PNG. */
    public string $storage = self::STORAGE_CDN;

    /** Volume handle used by asset storage. */
    public ?string $volume = null;

    /** `standalone` emits meta tags, `seo-field` hands the image to an SEO plugin's field. */
    public string $integration = self::INTEGRATION_STANDALONE;

    /** Asset field handle written to in seo-field mode. */
    public ?string $seoField = null;

    /** Site name passed to templates. Falls back to the Craft site name. */
    public string $siteName = '';

    /** Absolute URL of a logo passed to templates. */
    public ?string $siteLogo = null;

    /** Absolute URL used when an entry has no image at all. */
    public ?string $fallbackImage = null;

    /** Handle of an asset field editors can use to override the generated image. */
    public string $imageField = '';

    /** Regenerate during bulk resaves. Off by default so resaves spend no credits. */
    public bool $regenerateOnResave = false;

    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['apiKey', 'siteLogo', 'fallbackImage'],
            ],
        ];
    }

    protected function defineRules(): array
    {
        return [
            [['template'], 'required'],
            [['width', 'height'], 'integer', 'min' => 100, 'max' => 5000],
            [['dpi'], 'integer', 'min' => 1, 'max' => 3],
            [['storage'], 'in', 'range' => [self::STORAGE_CDN, self::STORAGE_ASSET]],
            [['integration'], 'in', 'range' => [self::INTEGRATION_STANDALONE, self::INTEGRATION_SEO_FIELD]],
        ];
    }

    public function beforeValidate(): bool
    {
        $this->sections = array_values(array_filter(array_map('strval', $this->sections)));
        $this->overrides = $this->normaliseOverrides($this->overrides);

        return parent::beforeValidate();
    }

    /** The API key with any environment variable reference resolved. */
    public function apiKey(): string
    {
        return trim((string)App::parseEnv($this->apiKey ?? ''));
    }

    public function siteLogoUrl(): string
    {
        return trim((string)App::parseEnv($this->siteLogo ?? ''));
    }

    public function fallbackImageUrl(): string
    {
        return trim((string)App::parseEnv($this->fallbackImage ?? ''));
    }

    public function isSectionEnabled(string $handle): bool
    {
        return in_array($handle, $this->sections, true);
    }

    /** Asset storage is required when an SEO plugin's field owns the output. */
    public function effectiveStorage(): string
    {
        if ($this->integration === self::INTEGRATION_SEO_FIELD) {
            return self::STORAGE_ASSET;
        }

        return $this->storage;
    }

    /**
     * @return array{template: string, width: int, height: int, dpi: int}
     */
    public function resolveForSection(?string $sectionHandle): array
    {
        return SettingsResolver::resolve([
            'template' => $this->template,
            'width' => $this->width,
            'height' => $this->height,
            'dpi' => $this->dpi,
        ], $this->overrides, $sectionHandle);
    }

    /**
     * Accepts both the control panel's table rows and a config file map keyed
     * by section handle, and stores the map form.
     *
     * @param array<int|string, array<string, mixed>> $overrides
     * @return array<string, array<string, mixed>>
     */
    private function normaliseOverrides(array $overrides): array
    {
        $map = [];

        foreach ($overrides as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $handle = is_string($key) && $key !== '' ? $key : (string)($row['section'] ?? '');

            if ($handle === '') {
                continue;
            }

            $values = [];
            foreach (['template', 'width', 'height', 'dpi'] as $attribute) {
                $value = $row[$attribute] ?? null;

                if ($value !== null && $value !== '') {
                    $values[$attribute] = $attribute === 'template' ? (string)$value : (int)$value;
                }
            }

            if ($values !== []) {
                $map[$handle] = $values;
            }
        }

        return $map;
    }
}

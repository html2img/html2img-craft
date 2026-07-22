<?php

namespace html2img\ogimages\services;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Assets as AssetsHelper;
use craft\web\View;
use html2img\ogimages\models\Settings;
use html2img\ogimages\Plugin;
use html2img\ogimages\support\ApiRenderer;
use html2img\ogimages\support\InputHash;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * The render pipeline: resolve settings, render the card template, hash the
 * inputs, call the API when anything changed and store the result.
 */
class Renderer extends Component
{
    public const STATUS_GENERATED = 'generated';
    public const STATUS_UNCHANGED = 'unchanged';
    public const STATUS_DISABLED = 'disabled';

    /**
     * @return array{status: string, url: ?string, message: string}
     * @throws \Throwable when the render or the API call fails
     */
    public function generate(Entry $entry, bool $force = false): array
    {
        $settings = $this->settings();

        if ($this->isDisabledForEntry($entry)) {
            return [
                'status' => self::STATUS_DISABLED,
                'url' => null,
                'message' => Craft::t('og-images', 'Skipped: ogDisabled is on for this entry.'),
            ];
        }

        $resolved = $settings->resolveForSection($entry->getSection()?->handle);

        Craft::$app->getSites()->setCurrentSite($entry->getSite());
        $html = $this->renderHtml($this->templateVariables($entry), $resolved['template']);
        $hash = InputHash::make($html, $resolved['width'], $resolved['height'], $resolved['dpi']);

        $images = Plugin::getInstance()->images;
        $record = $images->getRecord($entry->id, $entry->siteId);
        $storage = $settings->effectiveStorage();

        if (!$force && $record !== null && $record->inputHash === $hash && $this->storedResultUsable($record, $storage)) {
            Craft::info("Input hash unchanged for entry {$entry->id}, skipping the API call.", 'og-images');

            // Keep the SEO field populated even when the image itself is current,
            // so switching integration modes needs no forced regeneration.
            if ($settings->integration === Settings::INTEGRATION_SEO_FIELD && $record->assetId !== null) {
                $this->applySeoField($entry, $record->assetId, (string)$settings->seoField);
            }

            return [
                'status' => self::STATUS_UNCHANGED,
                'url' => $images->generatedUrl($entry),
                'message' => Craft::t('og-images', 'Unchanged: the render inputs match the stored image.'),
            ];
        }

        $apiKey = $settings->apiKey();

        if ($apiKey === '') {
            throw new InvalidConfigException(
                'No HTML to Image API key is set. Add HTML2IMG_API_KEY to your environment.'
            );
        }

        $response = (new ApiRenderer($apiKey))->render(
            $html,
            $resolved['width'],
            $resolved['height'],
            $resolved['dpi']
        );

        if (!$response->success || $response->url === null) {
            throw new \RuntimeException('The API did not return an image URL: ' . ($response->message ?? 'unknown error'));
        }

        $assetId = null;
        $url = $response->url;

        if ($storage === Settings::STORAGE_ASSET) {
            $assetId = $this->storeAsAsset($entry, $response->url, $record?->assetId);
            $url = null;
        }

        $images->store($entry->id, $entry->siteId, [
            'url' => $url,
            'assetId' => $assetId,
            'inputHash' => $hash,
            'width' => $resolved['width'] * $resolved['dpi'],
            'height' => $resolved['height'] * $resolved['dpi'],
        ]);

        if ($settings->integration === Settings::INTEGRATION_SEO_FIELD && $assetId !== null) {
            $this->applySeoField($entry, $assetId, (string)$settings->seoField);
        }

        $credits = $response->creditsRemaining !== null ? ", {$response->creditsRemaining} credits remaining" : '';
        Craft::info("Generated Open Graph image for entry {$entry->id} via the API{$credits}.", 'og-images');

        return [
            'status' => self::STATUS_GENERATED,
            'url' => $images->generatedUrl($entry),
            'message' => Craft::t('og-images', 'Image generated.'),
        ];
    }

    /**
     * Renders the card template in site mode, the same HTML the API receives.
     *
     * @param array<string, mixed> $variables
     */
    public function renderHtml(array $variables, string $template): string
    {
        return Craft::$app->getView()->renderTemplate($template, $variables, View::TEMPLATE_MODE_SITE);
    }

    /**
     * @return array<string, mixed>
     */
    public function templateVariables(Entry $entry): array
    {
        $settings = $this->settings();

        return [
            'entry' => $entry,
            'ogHeadline' => $this->headlineFor($entry),
            'ogSubtitle' => $this->stringField($entry, 'ogSubtitle'),
            'siteName' => $settings->siteName !== '' ? $settings->siteName : $entry->getSite()->getName(),
            'siteLogo' => $settings->siteLogoUrl(),
        ];
    }

    /**
     * Stand-in variables for the settings page preview, no entry required.
     *
     * @return array<string, mixed>
     */
    public function sampleVariables(): array
    {
        $settings = $this->settings();

        return [
            'entry' => null,
            'ogHeadline' => Craft::t('og-images', 'A headline long enough to show how your card wraps across lines'),
            'ogSubtitle' => Craft::t('og-images', 'Sample subtitle text from the settings preview'),
            'siteName' => $settings->siteName !== '' ? $settings->siteName : Craft::$app->getSites()->getPrimarySite()->getName(),
            'siteLogo' => $settings->siteLogoUrl(),
        ];
    }

    /** The ogHeadline field when present and filled, otherwise the title. */
    public function headlineFor(Entry $entry): string
    {
        return $this->stringField($entry, 'ogHeadline') ?? (string)$entry->title;
    }

    public function isDisabledForEntry(Entry $entry): bool
    {
        $layout = $entry->getFieldLayout();

        if ($layout === null || $layout->getFieldByHandle('ogDisabled') === null) {
            return false;
        }

        return (bool)$entry->getFieldValue('ogDisabled');
    }

    private function stringField(Entry $entry, string $handle): ?string
    {
        $layout = $entry->getFieldLayout();

        if ($layout === null || $layout->getFieldByHandle($handle) === null) {
            return null;
        }

        $value = trim((string)$entry->getFieldValue($handle));

        return $value !== '' ? $value : null;
    }

    /**
     * Downloads the PNG into the configured volume. Replaces the existing
     * asset's file when there is one, so references to it stay valid.
     */
    private function storeAsAsset(Entry $entry, string $url, ?int $existingAssetId): int
    {
        $settings = $this->settings();
        $volumeHandle = (string)$settings->volume;
        $volume = $volumeHandle !== '' ? Craft::$app->getVolumes()->getVolumeByHandle($volumeHandle) : null;

        if ($volume === null) {
            throw new InvalidConfigException(
                'Asset storage needs a volume. Pick one in the plugin settings.'
            );
        }

        $tempPath = AssetsHelper::tempFilePath('png');
        $client = Craft::createGuzzleClient();
        $client->request('GET', $url, ['sink' => $tempPath]);

        $filename = sprintf('og-%d-%d.png', $entry->id, $entry->siteId);

        if ($existingAssetId !== null) {
            $existing = Asset::find()->id($existingAssetId)->one();

            if ($existing !== null) {
                Craft::$app->getAssets()->replaceAssetFile($existing, $tempPath, $existing->getFilename());

                return $existing->id;
            }
        }

        $folder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->setFilename($filename);
        $asset->newFolderId = $folder->id;
        $asset->setVolumeId($folder->volumeId);
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            throw new \RuntimeException('Could not save the image asset: ' . implode(' ', $asset->getFirstErrors()));
        }

        return $asset->id;
    }

    /** Writes the generated asset into the SEO plugin's field, without re-triggering ourselves. */
    private function applySeoField(Entry $entry, int $assetId, string $fieldHandle): void
    {
        if ($fieldHandle === '') {
            return;
        }

        $layout = $entry->getFieldLayout();

        if ($layout === null || $layout->getFieldByHandle($fieldHandle) === null) {
            Craft::warning("The SEO field '{$fieldHandle}' is not in the entry's field layout.", 'og-images');

            return;
        }

        $current = $entry->getFieldValue($fieldHandle);
        if (is_object($current) && method_exists($current, 'ids') && $current->ids() === [$assetId]) {
            return;
        }

        Plugin::$suppressListeners = true;

        try {
            $entry->setFieldValue($fieldHandle, [$assetId]);
            Craft::$app->getElements()->saveElement($entry, false);
        } finally {
            Plugin::$suppressListeners = false;
        }
    }

    private function storedResultUsable(\html2img\ogimages\records\ImageRecord $record, string $storage): bool
    {
        if ($storage === Settings::STORAGE_ASSET) {
            return $record->assetId !== null && Asset::find()->id($record->assetId)->exists();
        }

        return $record->url !== null && $record->url !== '';
    }

    private function settings(): Settings
    {
        return Plugin::getInstance()->getSettings();
    }
}

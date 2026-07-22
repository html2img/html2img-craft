<?php

namespace html2img\ogimages;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\ModelEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\ElementHelper;
use craft\helpers\Queue;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use html2img\ogimages\jobs\GenerateImage;
use html2img\ogimages\models\Settings;
use html2img\ogimages\services\Images;
use html2img\ogimages\services\Renderer;
use html2img\ogimages\variables\OgImagesVariable;
use yii\base\Event;

/**
 * Auto Open Graph Images.
 *
 * Renders a Twig template against each entry on save, sends the HTML to the
 * HTML to Image API and stores the resulting PNG for output in the page head.
 *
 * @property-read Images $images
 * @property-read Renderer $renderer
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    /**
     * Suppresses the save listener while the plugin writes to an entry itself,
     * such as populating the SEO field after a render.
     */
    public static bool $suppressListeners = false;

    public static function config(): array
    {
        return [
            'components' => [
                'images' => Images::class,
                'renderer' => Renderer::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->registerTemplateRoots();
        $this->registerVariable();
        $this->registerCpUrlRules();
        $this->registerSidebarPanel();
        $this->registerSaveListener();
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        $sectionOptions = [];
        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            $sectionOptions[] = ['label' => $section->name, 'value' => $section->handle];
        }

        $volumeOptions = [['label' => Craft::t('og-images', 'None'), 'value' => '']];
        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $volumeOptions[] = ['label' => $volume->name, 'value' => $volume->handle];
        }

        return Craft::$app->getView()->renderTemplate('og-images/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'sectionOptions' => $sectionOptions,
            'volumeOptions' => $volumeOptions,
        ]);
    }

    private function registerTemplateRoots(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['og-images'] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
            }
        );
    }

    private function registerVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('ogImages', OgImagesVariable::class);
            }
        );
    }

    private function registerCpUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['og-images/preview'] = 'og-images/preview/index';
            }
        );
    }

    private function registerSidebarPanel(): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            function(DefineHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;
                $event->html .= $this->sidebarHtml($entry, $event->static);
            }
        );
    }

    private function sidebarHtml(Entry $entry, bool $static): string
    {
        $section = $entry->sectionId ? $entry->getSection() : null;

        if (
            $section === null
            || !$this->getSettings()->isSectionEnabled($section->handle)
            || $entry->getIsRevision()
        ) {
            return '';
        }

        $canonicalId = $entry->getCanonicalId();

        if ($canonicalId === null) {
            return '';
        }

        return Craft::$app->getView()->renderTemplate('og-images/_sidebar', [
            'entry' => $entry,
            'elementId' => $canonicalId,
            'record' => $this->images->getRecord($canonicalId, $entry->siteId),
            'imageUrl' => $this->images->generatedUrl($entry),
            'static' => $static,
        ], View::TEMPLATE_MODE_CP);
    }

    private function registerSaveListener(): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_AFTER_PROPAGATE,
            function(ModelEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;
                $this->handleEntrySave($entry);
            }
        );
    }

    private function handleEntrySave(Entry $entry): void
    {
        if (self::$suppressListeners) {
            return;
        }

        $settings = $this->getSettings();
        $section = $entry->sectionId ? $entry->getSection() : null;

        if ($section === null || !$settings->isSectionEnabled($section->handle)) {
            return;
        }

        if (
            ElementHelper::isDraftOrRevision($entry)
            || $entry->propagating
            || !$entry->enabled
            || !$entry->getEnabledForSite()
        ) {
            return;
        }

        if ($entry->resaving && !$settings->regenerateOnResave) {
            return;
        }

        if ($settings->apiKey() === '') {
            Craft::warning(
                "Skipped queueing an Open Graph image for entry {$entry->id}: no API key is set.",
                'og-images'
            );

            return;
        }

        Queue::push(new GenerateImage([
            'elementId' => $entry->id,
            'siteId' => $entry->siteId,
        ]));
    }
}

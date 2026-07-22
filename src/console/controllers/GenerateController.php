<?php

namespace html2img\ogimages\console\controllers;

use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use html2img\ogimages\Plugin;
use html2img\ogimages\services\Renderer;
use yii\console\ExitCode;

/**
 * Regenerates Open Graph images from the command line.
 *
 * php craft og-images/generate [--section=blog] [--force]
 */
class GenerateController extends Controller
{
    /** @var string|null Limit the run to one section handle. */
    public ?string $section = null;

    /** @var bool Re-render every entry, ignoring the input hash. */
    public bool $force = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['section', 'force']);
    }

    /**
     * Regenerates images across the enabled sections.
     */
    public function actionIndex(): int
    {
        $settings = Plugin::getInstance()->getSettings();
        $renderer = Plugin::getInstance()->renderer;

        $handles = $this->section !== null ? [$this->section] : $settings->sections;

        if ($handles === []) {
            $this->stderr("No sections are enabled in the plugin settings.\n", Console::FG_RED);

            return ExitCode::CONFIG;
        }

        if ($this->section !== null && !$settings->isSectionEnabled($this->section)) {
            $this->stderr("The '{$this->section}' section is not enabled in the plugin settings.\n", Console::FG_RED);

            return ExitCode::CONFIG;
        }

        $counts = ['generated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($handles as $handle) {
            $entries = Entry::find()
                ->section($handle)
                ->siteId('*')
                ->unique(false)
                ->status(null)
                ->all();

            foreach ($entries as $entry) {
                $label = "{$handle}/{$entry->slug} (#{$entry->id}, site {$entry->siteId})";

                if (!$entry->enabled || !$entry->getEnabledForSite()) {
                    $counts['skipped']++;
                    $this->stdout("skip      {$label}: entry is disabled\n", Console::FG_GREY);

                    continue;
                }

                try {
                    $result = $renderer->generate($entry, $this->force);
                } catch (\Throwable $exception) {
                    $counts['failed']++;
                    $this->stdout("failed    {$label}: {$exception->getMessage()}\n", Console::FG_RED);

                    continue;
                }

                switch ($result['status']) {
                    case Renderer::STATUS_GENERATED:
                        $counts['generated']++;
                        $this->stdout("generated {$label}\n", Console::FG_GREEN);
                        break;
                    case Renderer::STATUS_UNCHANGED:
                        $counts['unchanged']++;
                        $this->stdout("unchanged {$label}\n", Console::FG_GREY);
                        break;
                    default:
                        $counts['skipped']++;
                        $this->stdout("skip      {$label}: ogDisabled is on\n", Console::FG_GREY);
                }
            }
        }

        $this->stdout(sprintf(
            "\nDone: %d generated, %d unchanged, %d skipped, %d failed.\n",
            $counts['generated'],
            $counts['unchanged'],
            $counts['skipped'],
            $counts['failed']
        ), $counts['failed'] > 0 ? Console::FG_YELLOW : Console::FG_GREEN);

        return $counts['failed'] > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}

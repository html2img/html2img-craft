<?php

namespace html2img\ogimages\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use html2img\ogimages\Plugin;

/**
 * Renders one entry's Open Graph image in the queue, so saves return fast and
 * the API's synchronous render budget never blocks an editor.
 */
class GenerateImage extends BaseJob
{
    public int $elementId;
    public int $siteId;
    public bool $force = false;

    public function execute($queue): void
    {
        $entry = Entry::find()
            ->id($this->elementId)
            ->siteId($this->siteId)
            ->status(null)
            ->one();

        if ($entry === null) {
            Craft::info("Entry {$this->elementId} no longer exists, nothing to render.", 'og-images');

            return;
        }

        Plugin::getInstance()->renderer->generate($entry, $this->force);
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('og-images', 'Generating Open Graph image');
    }
}

<?php

namespace html2img\ogimages\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use html2img\ogimages\Plugin;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * The sidebar Generate button. Renders synchronously and ignores the input
 * hash, so editors can compare the preview with a real API render.
 */
class GenerateController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
        $this->requireAcceptsJson();

        $elementId = (int)$this->request->getRequiredBodyParam('elementId');
        $siteId = (int)$this->request->getBodyParam('siteId', Craft::$app->getSites()->getCurrentSite()->id);

        $entry = Entry::find()->id($elementId)->siteId($siteId)->status(null)->one();

        if ($entry === null) {
            return $this->asFailure(Craft::t('og-images', 'Entry not found.'));
        }

        $user = Craft::$app->getUser()->getIdentity();

        if ($user === null || !$entry->canSave($user)) {
            throw new ForbiddenHttpException('You are not allowed to generate images for this entry.');
        }

        try {
            $result = Plugin::getInstance()->renderer->generate($entry, true);
        } catch (\Throwable $exception) {
            Craft::error('Generate failed: ' . $exception->getMessage(), 'og-images');

            return $this->asFailure($exception->getMessage());
        }

        return $this->asSuccess($result['message'], ['url' => $result['url']]);
    }
}

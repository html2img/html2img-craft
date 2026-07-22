<?php

namespace html2img\ogimages\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use html2img\ogimages\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Renders the card template in the browser, no API key needed. The browser
 * shows the same HTML the API's Chrome would capture.
 */
class PreviewController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requireCpRequest();

        $renderer = Plugin::getInstance()->renderer;
        $settings = Plugin::getInstance()->getSettings();

        $entryId = (int)$this->request->getQueryParam('entryId', 0);
        $sectionHandle = $this->request->getQueryParam('section');

        if ($entryId > 0) {
            $entry = Entry::find()->id($entryId)->status(null)->one();

            if ($entry === null) {
                throw new NotFoundHttpException('Entry not found.');
            }

            Craft::$app->getSites()->setCurrentSite($entry->getSite());
            $variables = $renderer->templateVariables($entry);
            $sectionHandle = $entry->getSection()?->handle;
        } else {
            $variables = $renderer->sampleVariables();
        }

        $resolved = $settings->resolveForSection(is_string($sectionHandle) ? $sectionHandle : null);
        $html = $renderer->renderHtml($variables, $resolved['template']);

        $this->response->format = Response::FORMAT_RAW;
        $this->response->getHeaders()->set('Content-Type', 'text/html; charset=utf-8');
        $this->response->data = $html;

        return $this->response;
    }
}

<?php

namespace jorisnoo\CraftModules\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\Queue;
use craft\web\Controller;
use jorisnoo\CraftModules\jobs\TriggerCachewarming;
use yii\caching\TagDependency;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class CacheRefreshController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    // Public Methods
    // =========================================================================

    /**
     * @throws ForbiddenHttpException
     * @throws \Throwable
     */
    public function actionIndex(): Response
    {
        $this->ensureTokenIsValid();

        // invalidate template caches
        TagDependency::invalidate(Craft::$app->getCache(), 'template');

        Queue::push(new TriggerCachewarming());

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws ForbiddenHttpException|\Throwable
     */
    private function ensureTokenIsValid(): void
    {
        if (\Craft::$app->getUser()->getIdentity() && \Craft::$app->getUser()->getIdentity()->admin) {
            return;
        }

        $secretHeader = $this->request->headers->get('cw-token');

        if (is_null($secretHeader) || ! App::env('CACHEWARMER_TOKEN')) {
            throw new ForbiddenHttpException('Invalid secret');
        }

        if ($secretHeader !== App::env('CACHEWARMER_TOKEN')) {
            throw new ForbiddenHttpException('Invalid secret');
        }
    }
}

<?php

namespace jorisnoo\CraftModules;

use Craft;
use craft\base\Event;
use craft\events\ExceptionEvent;
use craft\helpers\App;
use craft\web\ErrorHandler;
use Throwable;
use yii\db\IntegrityException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use Spatie\FlareClient\Flare as FlareClient;

class Flare extends BaseModule
{
    public function attachEventHandlers(): void
    {
        $isLocal = App::env('CRAFT_ENVIRONMENT') === 'dev';

        if($isLocal || !App::env('FLARE_KEY')) {
            return;
        }

        $flare = FlareClient::make(App::env('FLARE_KEY'))
//            ->setStage(Craft::$app->getConfig()->env)
//            ->registerErrorHandler()
            ->anonymizeIp();

        $flare->filterExceptionsUsing(
            fn(Throwable $exception) => (
                !($exception instanceof NotFoundHttpException && $exception?->statusCode === 404)
                &&
                !($exception instanceof ForbiddenHttpException && $exception?->statusCode === 403)
                &&
                !($exception instanceof IntegrityException)
            )
        );

        Event::on(
            ErrorHandler::class,
            ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION,
            static function (ExceptionEvent $event) use ($flare) {

                if (Craft::$app->getRequest()->getIsCpRequest()) {
                    return;
                }

                $flare->handleException($event->exception);
            });
    }
}

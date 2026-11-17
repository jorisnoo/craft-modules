<?php

namespace Noo\CraftModules;

use Throwable;
use Twig\Error\RuntimeError;
use webhubworks\flare\CraftFlare;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class FlareExceptionFilter extends BaseModule
{
    public function attachEventHandlers(): void
    {
        if (! class_exists(CraftFlare::class)) {
            return;
        }

        $flare = CraftFlare::getFlareInstance();

        $flare?->filterExceptionsUsing(function (Throwable $throwable) {
            if (
                $throwable instanceof BadRequestHttpException ||
                $throwable instanceof NotFoundHttpException ||
                $throwable instanceof ForbiddenHttpException
            ) {
                return false;
            }

            if (
                $throwable instanceof RuntimeError &&
                ($throwable->getPrevious() instanceof NotFoundHttpException ||
                 $throwable->getPrevious() instanceof ForbiddenHttpException)
            ) {
                return false;
            }

            return true;
        });
    }
}

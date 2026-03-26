<?php

namespace Noo\CraftModules;

use Craft;
use craft\events\TemplateEvent;
use craft\web\View;
use yii\base\Event;

class CpCss extends BaseModule
{
    public function attachEventHandlers(): void
    {
        if (! Craft::$app->getRequest()->getIsCpRequest()) {
            return;
        }

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function (TemplateEvent $event) {
                Craft::$app->getView()->registerCss(
                    <<<CSS
                    body.login {
                        background: #fff;
                        color: #000;
                    }
                    body.login #login-logo {
                        max-height: 100px;
                        max-width: 100px;
                    }
                    body.login .login-container {
                        margin-top: 4rem;
                    }
                    body.login .login-form-container {
                        background-color: #fff;
                    }
                    body.login .login-form .btn.submit {
                        background: #000 !important;
                    }
                    body.login header {
                        display: none !important;
                    }
                    CSS
                );
            }
        );
    }
}

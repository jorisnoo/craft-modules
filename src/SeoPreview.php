<?php
namespace jorisnoo\CraftModules;

use Craft;
use craft\base\Element;
use craft\events\RegisterPreviewTargetsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;
use yii\base\Module;

class SeoPreview extends Module
{
    public function init()
    {
        Craft::setAlias('@jorisnoo/craft-modules', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'jorisnoo\\CraftModules\\console\\controllers';
        } else {
            $this->controllerNamespace = 'jorisnoo\\CraftModules\\controllers';
        }

        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
        });
    }

    private function attachEventHandlers(): void
    {
        $request = Craft::$app->getRequest();

        // Register preview target
        Event::on(
            Element::class,
            Element::EVENT_REGISTER_PREVIEW_TARGETS,
            static function (RegisterPreviewTargetsEvent $event) {
                $element = $event->sender;
                if (!$element->getUrl()) {
                    return;
                }
                $event->previewTargets[] = [
                    'label' => Craft::t('app', 'SEO Preview'),
                    'url' => UrlHelper::siteUrl('seopreview/preview', [
                        'elementId' => $element->id,
                        'siteId' => $element->siteId,
                    ]),
                ];
            }
        );

        // Register preview site route
        if (!Craft::$app->user->isGuest && $request->getIsSiteRequest() && !$request->getIsConsoleRequest()) {

            Event::on(
                View::class,
                View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
                static function(RegisterTemplateRootsEvent $event) {
                    $event->roots['_jorisnoo'] = __DIR__ . '/templates';
                }
            );

            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                static function (RegisterUrlRulesEvent $event) {
                    $event->rules['seopreview/preview'] = [
                        'template' => '_jorisnoo/seo',
                    ];
                }
            );
        }
    }

}

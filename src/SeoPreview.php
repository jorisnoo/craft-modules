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

class SeoPreview extends BaseModule
{
    public function attachEventHandlers(): void
    {
        $request = Craft::$app->getRequest();

        // Register preview target
        Event::on(
            Element::class,
            Element::EVENT_REGISTER_PREVIEW_TARGETS,
            static function (RegisterPreviewTargetsEvent $event) {
                $element = $event->sender;
                if (! $element->getUrl()) {
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
        if (! Craft::$app->user->isGuest && $request->getIsSiteRequest() && ! $request->getIsConsoleRequest()) {

            Event::on(
                View::class,
                View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
                static function (RegisterTemplateRootsEvent $event) {
                    $event->roots['_jorisnoo'] = __DIR__.'/templates';
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

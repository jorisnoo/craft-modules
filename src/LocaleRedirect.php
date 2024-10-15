<?php

namespace jorisnoo\CraftModules;

use Craft;
use craft\web\twig\variables\CraftVariable;
use jorisnoo\CraftModules\traits\HasConfig;
use koenster\PHPLanguageDetection\BrowserLocalization;
use yii\base\Event;
use craft\web\Application;

class LocaleRedirect extends BaseModule
{
    use HasConfig;

    protected string $configFile = 'locale-redirect';

    public function attachEventHandlers(): void
    {
        $request = Craft::$app->getRequest();

        if (
            Craft::$app->env !== 'testing'
            && $request->getIsSiteRequest()
            && $request->getFullUri() === ''
            && $request->method === 'GET'
            && !$request->getIsActionRequest()
            && !$request->getIsPreview()
            && !$request->getIsLivePreview()
        ) {

            $this->config = $this->getConfig();
            $locales = $this->config['locales'];

            $localeKeys = array_keys($locales);
            $localesHasStringKeys = count(array_filter($localeKeys, 'is_string')) > 0;
            $availableLocales = $localesHasStringKeys ? $localeKeys : $locales;

            /**
             * $localesHasStringKeys is true if the array has string keys, e.g.:
             * 'de' => 'de-DE', 'en' => 'en-US'
             *
             * it is false if the array has numeric keys, e.g.:
             * ['de-DE', 'en-US'],
             */

            $browser = new BrowserLocalization();

            $browser->setAvailable($availableLocales)
                ->setDefault($this->config['default'] ?? $availableLocales[0])
                ->setPreferences($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null);

            $lang = $browser->detect();

            $redirectUrl = $localesHasStringKeys ? $locales[$lang] : $lang;

            header("Location: /{$redirectUrl}/", true, 302);
            exit();
        }
    }

}

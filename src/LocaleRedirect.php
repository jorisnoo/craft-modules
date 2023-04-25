<?php
namespace jorisnoo\CraftModules;

use Craft;
use craft\web\twig\variables\CraftVariable;
use jorisnoo\CraftModules\traits\HasConfig;
use koenster\PHPLanguageDetection\BrowserLocalization;
use yii\base\Event;

class LocaleRedirect extends BaseModule
{
    use HasConfig;

    protected string $configFile = 'locale-redirect';

    public function attachEventHandlers(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $request = Craft::$app->getRequest();

                if (
                    !$request->isSiteRequest
                    || $request->isPreview
                    || $request->getFullUri() !== ''
                ) {
                    return;
                }

                $this->config = $this->getConfig();
                $locales = $this->config['locales'];
                $localeKeys = array_keys($locales);
                $localesHasStringKeys = count(array_filter($localeKeys, 'is_string')) > 0;
                $availableLocales = $localesHasStringKeys ? $localeKeys : $locales;

                $browser = new BrowserLocalization();

                $browser->setAvailable($availableLocales)
                    ->setDefault($this->config['default'] ?? $availableLocales[0])
                    ->setPreferences($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null);

                $lang = $browser->detect();

                $redirectUrl = $localesHasStringKeys ? $locales[$lang] : $lang;

                header("Location: /{$redirectUrl}", true, 302);
                exit();
            }
        );
    }

}

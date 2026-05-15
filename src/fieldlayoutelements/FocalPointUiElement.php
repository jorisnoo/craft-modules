<?php

namespace Noo\CraftModules\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\fieldlayoutelements\BaseUiElement;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\assets\focalpoint\FocalPointAsset;

class FocalPointUiElement extends BaseUiElement
{
    public string $label = '';

    public string $instructions = '';

    protected function selectorLabel(): string
    {
        $label = trim($this->label);
        return $label !== '' ? $label : Craft::t('app', 'Focal Point');
    }

    protected function selectorIcon(): ?string
    {
        return 'crosshairs';
    }

    public function hasSettings(): bool
    {
        return true;
    }

    protected function settingsHtml(): ?string
    {
        return
            Cp::textFieldHtml([
                'label' => Craft::t('app', 'Label'),
                'instructions' => Craft::t('app', 'Heading shown above the focal point picker. Defaults to "Focal Point".'),
                'id' => 'label',
                'name' => 'label',
                'value' => $this->label,
            ]) .
            Cp::textareaFieldHtml([
                'label' => Craft::t('app', 'Instructions'),
                'instructions' => Craft::t('app', 'Optional helper text shown below the label.'),
                'class' => ['nicetext'],
                'id' => 'instructions',
                'name' => 'instructions',
                'value' => $this->instructions,
            ]);
    }

    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Asset) {
            return null;
        }

        if (!in_array($element->kind, [Asset::KIND_IMAGE, Asset::KIND_VIDEO], true)) {
            return null;
        }

        if ($element->mimeType === 'image/svg+xml') {
            return null;
        }

        $thumbnailUrl = self::resolveThumbnailUrl($element);

        if (!$thumbnailUrl) {
            return Html::tag('div', Html::tag('p', Craft::t('app', 'No preview available to set a focal point on.'), [
                'class' => 'light',
            ]), $this->containerAttributes($element, $static));
        }

        if (!Craft::$app->getElements()->canSave($element)) {
            return null;
        }

        $view = Craft::$app->getView();
        $view->registerAssetBundle(FocalPointAsset::class);
        $view->registerTranslations('app', [
            'Enable focal point',
            'Disable focal point',
            'Saved',
            'Saving…',
        ]);

        $containerId = sprintf('focal-point-%s', mt_rand());
        $toggleId = sprintf('focal-toggle-%s', mt_rand());
        $revealId = sprintf('focal-reveal-%s', mt_rand());
        $panelId = sprintf('focal-panel-%s', mt_rand());
        $namespacedContainerId = $view->namespaceInputId($containerId);
        $namespacedToggleId = $view->namespaceInputId($toggleId);
        $namespacedRevealId = $view->namespaceInputId($revealId);
        $namespacedPanelId = $view->namespaceInputId($panelId);

        $focal = implode(';', $element->getFocalPoint());
        $hasFocal = $element->getHasFocalPoint();
        $openLabel = Craft::t('app', 'Set focal point');
        $editLabel = Craft::t('app', 'Edit focal point');
        $closeLabel = Craft::t('app', 'Done');
        $revealLabel = $hasFocal ? $editLabel : $openLabel;
        $openLabelJs = Json::encode($openLabel);
        $editLabelJs = Json::encode($editLabel);
        $closeLabelJs = Json::encode($closeLabel);
        $hasFocalJs = Json::encode($hasFocal);

        $view->registerCss(<<<CSS
            [data-focal-ui-element] .button-fade .buttons { opacity: 1; }
            [data-focal-ui-element] .asset-image-preview { max-width: 100%; }
            [data-focal-ui-element] [data-focal-panel][hidden] { display: none; }
            [data-focal-ui-element] [data-focal-panel] { margin-top: 8px; }
        CSS);

        $view->registerJs(<<<JS
            (() => {
                const \$reveal = $('#$namespacedRevealId');
                const \$panel = $('#$namespacedPanelId');
                const \$container = $('#$namespacedContainerId');
                const \$toggle = $('#$namespacedToggleId');
                if (!\$reveal.length || !\$panel.length || !\$container.length || !\$toggle.length) {
                    return;
                }
                if (typeof Craft === 'undefined' || typeof Craft.FocalPoint === 'undefined') {
                    return;
                }
                const openLabel = $openLabelJs;
                const editLabel = $editLabelJs;
                const closeLabel = $closeLabelJs;
                let hasFocal = $hasFocalJs;
                let initialized = false;
                let fp = null;
                const init = () => {
                    if (initialized) return;
                    initialized = true;
                    try {
                        fp = new Craft.FocalPoint(\$container, \$toggle, true);
                        fp.visible = true;
                        fp.renderFocal();
                    } catch (e) {
                        console.error('[focal-point] init failed', e);
                    }
                };
                const open = () => {
                    \$panel.prop('hidden', false);
                    \$reveal.text(closeLabel);
                    const \$img = \$container.find('img');
                    // Set the src on first reveal so we don't load the image
                    // for users who never open the picker.
                    if (!\$img.attr('src') && \$img.attr('data-src')) {
                        \$img.attr('src', \$img.attr('data-src'));
                    }
                    if (\$img[0] && \$img[0].complete && \$img[0].naturalWidth > 0) {
                        init();
                    } else {
                        \$img.one('load', init);
                    }
                };
                const close = () => {
                    \$panel.prop('hidden', true);
                    if (fp) {
                        hasFocal = fp.visible && !fp.isCentered();
                    }
                    \$reveal.text(hasFocal ? editLabel : openLabel);
                };
                \$reveal.on('click', (e) => {
                    e.preventDefault();
                    if (\$panel.prop('hidden')) {
                        open();
                    } else {
                        close();
                    }
                });
            })();
        JS);

        $imgHtml = Html::tag('img', '', [
            'alt' => '',
            'style' => 'display:block;max-width:100%;height:auto;',
            'data' => [
                'src' => $thumbnailUrl,
                'uid' => $element->uid,
                'focal' => $focal,
            ],
        ]);

        $buttonsHtml = Html::tag(
            'div',
            Html::tag('div', '', ['class' => 'btn', 'id' => $toggleId]),
            ['class' => 'buttons'],
        );

        $previewHtml = Html::tag('div', $buttonsHtml . $imgHtml, [
            'id' => $containerId,
            'class' => ['button-fade', 'asset-image-preview'],
            'style' => 'position:relative;display:inline-block;max-width:100%;',
        ]);

        $revealButton = Html::button($revealLabel, [
            'id' => $revealId,
            'type' => 'button',
            'class' => 'btn',
        ]);

        $panel = Html::tag('div', $previewHtml, [
            'id' => $panelId,
            'data' => ['focal-panel' => true],
            'hidden' => true,
        ]);

        $inputHtml = Html::tag('div', $revealButton . $panel, [
            'data' => ['focal-ui-element' => true],
        ]);

        $label = trim($this->label) !== '' ? trim($this->label) : Craft::t('app', 'Focal Point');
        $instructions = trim($this->instructions);

        $fieldHtml = Cp::fieldHtml($inputHtml, [
            'label' => $label,
            'instructions' => $instructions !== '' ? $instructions : null,
        ]);

        return Html::tag('div', $fieldHtml, $this->containerAttributes($element, $static));
    }

    private static function resolveThumbnailUrl(Asset $asset): ?string
    {
        if ($asset->kind === Asset::KIND_VIDEO) {
            $helper = '\\Noo\\CraftBunnyStream\\helpers\\BunnyStreamHelper';
            if (class_exists($helper) && method_exists($helper, 'getThumbnailUrl')) {
                return $helper::getThumbnailUrl($asset) ?: null;
            }
            return null;
        }

        // Image kind
        return $asset->getUrl() ?? Craft::$app->getAssets()->getThumbUrl($asset, 800, 800, false);
    }
}

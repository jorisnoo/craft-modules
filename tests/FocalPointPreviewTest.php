<?php

use craft\elements\Asset;
use craft\imagetransforms\FallbackTransformer;
use craft\models\ImageTransform;
use craft\services\Images;
use craft\web\Application;
use Noo\CraftModules\fieldlayoutelements\FocalPointUiElement;

require_once dirname(__DIR__).'/vendor/yiisoft/yii2/Yii.php';
require_once dirname(__DIR__).'/vendor/craftcms/cms/src/Craft.php';

it('requests an uncropped JPEG for an image in a private volume and handles unsupported drivers', function (bool $supported) {
    $previous = Craft::$app;
    Craft::$app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    $images = $this->createStub(Images::class);
    $images->method('getSupportedImageFormats')->willReturn($supported ? ['heic'] : ['jpeg']);
    Craft::$app->set('images', $images);
    $asset = $this->createMock(Asset::class);
    $asset->kind = Asset::KIND_IMAGE;
    $asset->method('getExtension')->willReturn('heic');
    $asset->expects($supported ? $this->exactly(2) : $this->never())->method('getUrl')->willReturnCallback(static function (ImageTransform $transform) {
        expect([$transform->mode, $transform->width, $transform->height, $transform->format, $transform->upscale])->toBe(['fit', 1200, 1200, 'jpg', false]);

        return $transform->transformer === FallbackTransformer::class ? '/private-preview.jpg' : null;
    });
    try {
        $preview = (new ReflectionMethod(FocalPointUiElement::class, 'resolveThumbnailUrl'))->invoke(null, $asset);
        expect($preview)->toBe($supported ? '/private-preview.jpg' : null);
    } finally {
        Craft::$app = $previous;
    }
})->with([true, false]);

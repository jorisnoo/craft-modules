<?php

use craft\base\Plugin;
use craft\console\Application;
use craft\events\CancelableEvent;
use craft\services\Api;
use craft\services\Plugins;
use Noo\CraftModules\console\controllers\DeployController;
use Noo\CraftModules\deploy\DeployCachePlan;
use webhubworks\ohdear\health\jobs\QueueHealthJob;
use yii\base\Component;
use yii\queue\Queue;

require_once dirname(__DIR__).'/vendor/yiisoft/yii2/Yii.php';
require_once dirname(__DIR__).'/vendor/craftcms/cms/src/Craft.php';

it('clears only available keys, restores health checks and scopes proxy suppression to the Blitz refresh', function (bool $apiAvailable) {
    $previous = Craft::$app;
    $purger = new class extends Component
    {
        public const EVENT_BEFORE_PURGE_ALL_CACHE = 'beforePurge';
    };
    $plugin = (new ReflectionClass(Plugin::class))->newInstanceWithoutConstructor();
    $plugin->set('cachePurger', $purger);
    $plugins = $this->createStub(Plugins::class);
    $plugins->method('getPlugin')->willReturn($plugin);
    $api = $this->createMock(Api::class);
    $request = $api->expects($this->once())->method('getLicenseInfo');
    if ($apiAvailable) {
        $request->willReturn([]);
    } else {
        $request->willThrowException(new RuntimeException('Service unavailable'));
    }
    $queue = $this->createMock(Queue::class);
    foreach (['priority', 'delay', 'ttr'] as $method) {
        $queue->method($method)->willReturnSelf();
    }
    $queue->expects($this->once())->method('push')->with($this->isInstanceOf(QueueHealthJob::class))->willReturn('1');
    Craft::$app = $this->getMockBuilder(Application::class)->disableOriginalConstructor()->onlyMethods(['createController'])->getMock();
    Craft::$app->setComponents(['api' => $api, 'plugins' => $plugins, 'queue' => $queue]);
    $routes = [];
    $command = new class($purger)
    {
        public bool $queue = false;

        public bool $interactive = false;

        public function __construct(private Component $purger) {}

        public function runAction(string $route): int
        {
            if ($route === 'blitz/cache/refresh') {
                $event = new CancelableEvent;
                $this->purger->trigger('beforePurge', $event);
                expect($event->isValid)->toBeFalse()->and($this->queue)->toBeTrue();
            }

            return 0;
        }
    };
    Craft::$app->expects($this->exactly(2))->method('createController')->willReturnCallback(function ($route) use (&$routes, $command) {
        $routes[] = $route;

        return [$command, $route];
    });
    $deploy = $this->getMockBuilder(DeployController::class)->disableOriginalConstructor()->onlyMethods(['stdout', 'stderr'])->getMock();
    $deploy->expects($apiAvailable ? $this->never() : $this->once())->method('stderr');
    try {
        (new ReflectionMethod(DeployController::class, 'executePlan'))->invoke($deploy, new DeployCachePlan([], ['data', 'missing'], true), ['data']);
        expect($routes)->toBe(['clear-caches/data', 'blitz/cache/refresh'])
            ->and($purger->hasEventHandlers('beforePurge'))->toBeFalse();
    } finally {
        Craft::$app = $previous;
    }
})->with([true, false]);

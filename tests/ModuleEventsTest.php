<?php

use craft\base\Element;
use craft\base\FieldInterface;
use craft\console\Application;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\ModelEvent;
use craft\models\FieldLayout;
use craft\models\Site;
use craft\models\UserGroup;
use craft\services\Sites;
use craft\services\UserGroups;
use craft\services\Users;
use craft\web\Request;
use craft\web\twig\Environment;
use craft\web\View;
use Noo\CraftModules\MakeUsersEditors;
use Noo\CraftModules\TextSnippetTwigExtension;
use Noo\CraftModules\TextSnippetTwigFunction;
use Twig\Loader\ArrayLoader;
use Twig\Markup;
use yii\base\Event;
use yii\queue\Queue;

require_once dirname(__DIR__).'/vendor/yiisoft/yii2/Yii.php';
require_once dirname(__DIR__).'/vendor/craftcms/cms/src/Craft.php';

beforeEach(function () {
    $this->previousApp = Craft::$app;
});

afterEach(function () {
    Craft::$app = $this->previousApp;
    Event::off(User::class, Element::EVENT_AFTER_SAVE);
    Event::off(Queue::class, Queue::EVENT_BEFORE_EXEC);
});

it('renders trusted and ordinary snippets in a console site template and resets them before another job', function () {
    Craft::$app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    $view = (new ReflectionClass(View::class))->newInstanceWithoutConstructor();
    $twig = new Environment(new ArrayLoader(['mail' => "{{ textSnippet('notice') }} {{ textSnippet('label') }}"]), ['autoescape' => 'html']);
    (new ReflectionProperty(View::class, '_siteTwig'))->setValue($view, $twig);
    Craft::$app->set('view', $view);
    $sites = $this->createStub(Sites::class);
    $site = $this->createStub(Site::class);
    $site->id = 1;
    $sites->method('getCurrentSite')->willReturn($site);
    Craft::$app->set('sites', $sites);

    (new TextSnippetTwigFunction)->attachEventHandlers();
    $extension = $twig->getExtension(TextSnippetTwigExtension::class);
    $entry = $this->createStub(Entry::class);
    $layout = $this->createStub(FieldLayout::class);
    $layout->method('getFieldByHandle')->willReturn($this->createStub(FieldInterface::class));
    $entry->method('getFieldLayout')->willReturn($layout);
    $entry->method('getFieldValue')->willReturnMap([
        ['notice', new Markup('<b>Notice</b>', 'UTF-8')], ['label', '<i>Label</i>'],
    ]);
    $entries = new ReflectionProperty($extension, 'entries');
    $entries->setValue($extension, ['translations|1' => [$entry]]);

    expect($view->getTwig(View::TEMPLATE_MODE_SITE)->render('mail'))->toBe('<b>Notice</b> &lt;i&gt;Label&lt;/i&gt;');
    Event::trigger(Queue::class, Queue::EVENT_BEFORE_EXEC);
    expect($entries->getValue($extension))->toBe([]);
});

it('adds the editor group only for an authorized CP creation', function (bool $cp, bool $credentialed, bool $register, bool $assign, bool $elevated, bool $expected) {
    $actor = $this->createStub(User::class);
    $actor->method('can')->willReturnMap([['registerUsers', $register], ['assignUserGroup:editor-uid', $assign]]);
    $user = $this->createStub(User::class);
    $user->id = 71;
    $user->firstSave = true;
    $user->method('getIsCredentialed')->willReturn($credentialed);
    $user->method('getGroups')->willReturn([(object) ['id' => 4]]);
    $group = $this->createStub(UserGroup::class);
    $group->id = 9;
    $group->uid = 'editor-uid';
    $groups = $this->createStub(UserGroups::class);
    $groups->method('getGroupByHandle')->willReturn($group);
    $session = $this->createStub(craft\web\User::class);
    $session->method('getIdentity')->willReturn($actor);
    $session->method('getHasElevatedSession')->willReturn($elevated);
    $users = $this->createMock(Users::class);
    $users->expects($expected ? $this->once() : $this->never())->method('assignUserToGroups')->with(71, [4, 9]);
    $request = $this->createStub(Request::class);
    $request->method('getIsCpRequest')->willReturn($cp);
    Craft::$app = (new ReflectionClass(craft\web\Application::class))->newInstanceWithoutConstructor();
    Craft::$app->setComponents(['request' => $request, 'user' => $session, 'users' => $users, 'userGroups' => $groups]);

    (new MakeUsersEditors)->attachEventHandlers();
    Event::trigger($user, Element::EVENT_AFTER_SAVE, new ModelEvent);
})->with([
    'public registration' => [false, true, true, true, true, false],
    'uncredentialed account' => [true, false, true, true, true, false],
    'cannot register' => [true, true, false, true, true, false],
    'cannot grant this group' => [true, true, true, false, true, false],
    'session not elevated' => [true, true, true, true, false, false],
    'authorized CP registration' => [true, true, true, true, true, true],
]);

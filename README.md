# Craft Modules

A collection of small [Craft CMS](https://craftcms.com/) modules for control panel customization and workflow automation.

## Requirements

- PHP 8.2+
- Craft CMS 5

## Installation

Add the repository to your `composer.json`:

```json
{
    "repositories": [{
        "type": "vcs",
        "url": "https://github.com/jorisnoo/craft-modules.git"
    }]
}
```

```bash
composer require jorisnoo/craft-modules:dev-main
```

Then register the module in `config/app.php`:

```php
return [
    'modules' => [
        'craft-modules' => \Noo\CraftModules\CraftModules::class,
    ],
    'bootstrap' => ['craft-modules'],
];
```

All modules are bootstrapped automatically by the `CraftModules` entry point.

## Modules

### AnalyticsNavLink

Adds an external "Analytics" link to the CP navigation, pointing to your Plausible dashboard. Set the URL via the `PLAUSIBLE_DASHBOARD_URL` environment variable. The link is only added when the variable is present.

### EnvironmentIndicator

Colors the CP sidebar header on non-production environments:

- `dev` — cyan
- Any other non-production value (e.g. `staging`) — amber
- `production` — no indicator

Uses the `CRAFT_ENVIRONMENT` env variable.

### CpCss

Injects custom styles on the CP login page (white background, black submit button, hidden header, constrained logo size).

### CpNavItems

Adjusts the default CP navigation: removes the Dashboard item and replaces the Oh Dear plugin icon with a built-in gauge icon.

### FlareExceptionFilter

Filters common HTTP exceptions (400, 403, 404) from Flare error reporting. Only activates when the `spatie/craft-flare` plugin is installed.

### HideUserPermissions

Removes the "Permissions" tab from the user edit screen.

### MakeUsersEditors

Automatically assigns users to the `editor` user group after group/permission assignment.

### TextSnippetTwigFunction

Registers a `textSnippet` Twig function that retrieves a text snippet from a Craft entry. Defaults to looking in the `translations` section.

```twig
{{ textSnippet('welcomeMessage') }}
{{ textSnippet('label', 'customSection') }}
```

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.

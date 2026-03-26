# Craft Modules

Reusable Craft CMS 5 modules.

## Modules

### AnalyticsNavLink

Adds an external "Analytics" link to the CP navigation. Reads the URL from `config/custom.php`:

```php
// config/custom.php
return [
    'plausible_url' => 'https://plausible.io/example.com',
];
```

### EnvironmentIndicator

Colors the CP sidebar header on non-production environments and shows a warning icon. Uses `CRAFT_ENVIRONMENT` to determine the environment:

- `dev` — cyan (`#00d0ff`)
- Any other non-production value (e.g. `staging`) — amber (`#f3b737`)
- `production` — no indicator

### MakeUsersEditors

Automatically assigns new users to the `editor` user group after group/permission assignment.

## Installation

Add the repository to your `composer.json`:

```json
{
    "repositories": [{
        "name": "craft-modules",
        "type": "vcs",
        "url": "https://github.com/jorisnoo/craft-modules.git"
    }]
}
```

```bash
composer require jorisnoo/craft-modules:dev-main
```

## Registration

Register modules in `config/app.php`:

```php
return [
    'modules' => [
        'make-users-editors' => Noo\CraftModules\MakeUsersEditors::class,
        'analytics-nav-link' => Noo\CraftModules\AnalyticsNavLink::class,
        'environment-indicator' => Noo\CraftModules\EnvironmentIndicator::class,
    ],
    'bootstrap' => [
        'make-users-editors',
        'analytics-nav-link',
        'environment-indicator',
    ],
];
```

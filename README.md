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

### MakeUsersEditors

Automatically assigns users to the `editor` user group after group/permission assignment.

### TextSnippetTwigFunction

Registers a `textSnippet` Twig function that retrieves a text snippet from the single entry of a section. Defaults to looking in the `translations` section.

```twig
{{ textSnippet('welcomeMessage') }}
{{ textSnippet('label', 'customSection') }}
```

It reads the snippet from a direct field instance on the entry (`handle`), and falls back to the legacy layout where the section's single holds one Matrix block (field handle == section name) carrying the snippets as its sub-fields. The section's entries are fetched once per request and memoized per site.

If a section spreads its snippets across several entry types, the lookup walks the section's entries and returns the value from whichever one carries the handle. Pass an entry type handle as the third argument to check that entry type first (it still falls back to the others):

```twig
{{ textSnippet('registrationConfirmed', 'newsletterStrings', 'newsletterVerificationEmail') }}
```

### Deployment cache command

The zero-argument deployment command compares the current Git commit with the last successful
deployment, clears only affected Craft caches, and refreshes Blitz when rendered pages may have
changed:

```bash
php craft craft-modules/deploy
```

Queueing is enabled by default. On Forge, state is stored at
`$FORGE_SITE_ROOT/.deploy-state/blitz-commit`; elsewhere it falls back to
`@storage/runtime/blitz-deploy/commit`. Blitz is optional and is skipped when it is not installed.

The command performs a conservative full clear and Blitz refresh on its first run or when the
previous commit is unavailable. Later runs map template, frontend, configuration, module,
migration, Composer, and package changes to the relevant caches. Unrelated changes require no
cache work.

Available overrides:

```bash
php craft craft-modules/deploy --dry-run
php craft craft-modules/deploy --force
php craft craft-modules/deploy --queue=0
php craft craft-modules/deploy --interactive=0
php craft craft-modules/deploy --from=<commit> --to=<commit>
php craft craft-modules/deploy --state-file=/custom/path
```

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.

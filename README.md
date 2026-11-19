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

`CraftModules` boots the bundled modules and registers and boots `craft-sitemap`, `remote-sync`, and `twig-helpers`.

## Modules

### AnalyticsNavLink

Adds an external "Analytics" link to the CP navigation, pointing to your Plausible dashboard. Set the URL via the `PLAUSIBLE_DASHBOARD_URL` environment variable. The link is only added when the variable is present.

### EnvironmentIndicator

Colors the CP sidebar header on non-production environments:

- `dev` — cyan
- Any other non-production value (e.g. `staging`) — amber
- `production`, `prod`, `live`, or unset — no indicator (case-insensitive)

Uses the `CRAFT_ENVIRONMENT` env variable.

### CpCss

Injects custom styles on the CP login page (white background, black submit button, hidden header, constrained logo size).

### CpNavItems

Adjusts the default CP navigation: removes the Dashboard item and replaces the Oh Dear plugin icon with a built-in gauge icon.

`RedirectDashboard` sends the Dashboard index to Entries. `HideSlugWithoutUri` hides the slug control when an entry has no URI format. Add the **Focal Point** UI element to an asset field layout to edit the focal point on an uncropped preview; image conversion requires support from the server's image driver. Bunny Stream videos use their thumbnail when available.

### FlareExceptionFilter

`webhubworks/craft-flare` owns exception reporting. In versions with `ignoredHttpStatusCodes`, configure that setting in the plugin (use `[400, 403, 404]` to also ignore bad requests). On older versions without that setting, the module filters common HTTP exceptions.

### Flare logs

When `FLARE_KEY` is set, `FlareLogs` automatically forwards Craft/Yii logs to Flare's OpenTelemetry logs endpoint. No application configuration is required beyond bootstrapping `CraftModules`.

```dotenv
FLARE_KEY=your-project-key
FLARE_LOG_LEVEL=warning
```

`FLARE_LOG_LEVEL` accepts `debug`, `info`, `warning` (the default), or `error`. The target excludes HTTP 400/403/404 and translation-source noise. Web requests export on Yii's final flush and finish an already-sent FastCGI response before delivery. Other web runtimes may still wait for the final export (500 ms timeout). Console workers can also export on a regular log flush. Log delivery is best effort and does not follow redirects.

### MakeUsersEditors

On the first canonical save of a credentialed user in the control panel, this adds `editor` only when the acting user can register users, can assign that specific group, and has an elevated session. Existing groups are retained. Public registrations, guest/customer records without credentials, console jobs and subsequent saves do not grant membership. Upgrading does not revoke existing memberships; review `editor` members if public registration has been enabled.

### TextSnippetTwigFunction

Registers `textSnippet` for site, CP, console and queued Twig renders. It retrieves snippets from the `translations` section by default. Trusted Twig markup retains its HTML; ordinary strings remain escaped. Each queued job starts with a fresh snippet cache.

```twig
{{ textSnippet('welcomeMessage') }}
{{ textSnippet('label', 'customSection') }}
```

It reads the snippet from a direct field instance on the entry (`handle`), and falls back to the legacy layout where the section's single holds one Matrix block (field handle == section name) carrying the snippets as its sub-fields. The section's entries are fetched once per request and memoized per site.

If a section spreads its snippets across several entry types, the lookup walks the section's entries and returns the value from whichever one carries the handle. Pass an entry type handle as the third argument to check that entry type first (it still falls back to the others):

```twig
{{ textSnippet('registrationConfirmed', 'newsletterStrings', 'newsletterVerificationEmail') }}
```

### Oh Dear health checks

When using `webhubworks/craft-ohdear`, register the desired checks in `config/ohdear.php`, replacing the corresponding built-ins:

```php
use Noo\CraftModules\ohdear\LicensesCheck;
use Noo\CraftModules\ohdear\ResilientAbandonedPackagesCheck;
use Noo\CraftModules\ohdear\ResilientCveCheck;
use Noo\CraftModules\ohdear\ResilientQueueCheck;

return [
    'healthChecks' => [
        LicensesCheck::new(),
        ResilientQueueCheck::new()->graceMinutes(10),
        ResilientCveCheck::new()->cachedViaCron(43200),
        ResilientAbandonedPackagesCheck::new()->cachedViaCron(43200),
    ],
];
```

Schedule `php craft ohdear/health-check/refresh` more frequently than the cache staleness threshold. Composer failures retry once; if a last good result exists the second failure is logged and its original refresh time is preserved. Missing queue heartbeats have one grace period per cache clear, not one per day. When a `QueueHealthJob` errors, the module releases that job from Craft's queue.

### Database backup cleanup

`php craft craft-modules/db/cleanup --keep=10` deletes older `.sql` and `.sql.zip` backups in Craft's backup directory, retaining the newest ten by modification time by default.

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
The refresh suppresses Blitz's reverse-proxy purge in the deploy process: purge that proxy separately. With Oh Dear installed, deploy asks Craft's API to refresh license information and queues a replacement heartbeat after clearing the data cache. A license-server failure is reported without aborting deployment.

The command fetches the single previous commit when a shallow deployment clone does not contain
it. It performs a conservative full clear and Blitz refresh on its first run or when that commit
remains unavailable. Later runs map template, frontend, configuration, module, migration,
Composer, and package changes to the relevant caches. Unrelated changes require no cache work.

Available overrides:

```bash
php craft craft-modules/deploy --dry-run
php craft craft-modules/deploy --force
php craft craft-modules/deploy --queue=0
php craft craft-modules/deploy --interactive=0
php craft craft-modules/deploy --from=<commit> --to=<commit>
php craft craft-modules/deploy --state-file=/custom/path
```

### Cached frontend builds

The deploy controller can skip npm installation and compilation when the exact frontend inputs
have already been built:

```bash
php craft craft-modules/deploy/build
```

It fingerprints `package.json`, npm lock/config files, Vite `.env` files, everything in `src/` and
`templates/`, and common Vite, Tailwind, PostCSS, Webpack, and TypeScript/JavaScript config files.
Symlinked inputs are followed and their target contents are included. On a cache hit it restores
`web/dist`; on a miss it runs `npm ci` and `npm run build`, verifies
`web/dist/.vite/manifest.json`, and stores the result. The five most recent builds are retained.

On Forge, builds are cached in `$FORGE_SITE_ROOT/.deploy-cache/frontend-builds`, outside individual
releases. Elsewhere the default is `@storage/runtime/deploy-build-cache`. Use these overrides to
inspect or control it:

```bash
php craft craft-modules/deploy/build --dry-run
php craft craft-modules/deploy/build --force
php craft craft-modules/deploy/build --build-cache-dir=/persistent/path
```

The three original deploy-script lines can therefore be replaced with the single build command.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.

## Maintainer releases

See [RELEASING.md](RELEASING.md) for versioning, changelog entries and the release command.

# Changelog

All notable changes to this project will be documented in this file.

## [2.0.7](https://github.com/jorisnoo/craft-modules/releases/tag/v2.0.7) (2026-07-14)

### Features

- **deploy:** fetch missing commits from remote during shallow deployments ([4b5278e](https://github.com/jorisnoo/craft-modules/commit/4b5278e01f1a9af9a8230c4075afaa496c8c6b6d))
## [2.0.6](https://github.com/jorisnoo/craft-modules/releases/tag/v2.0.6) (2026-07-14)

### Features

- **deploy:** add zero-argument deployment cache command ([52bd883](https://github.com/jorisnoo/craft-modules/commit/52bd883de93d63ab2e82d0a7e28ef6aec75739e1))

### Bug Fixes

- **deploy:** respect configured interactive flag ([a41683d](https://github.com/jorisnoo/craft-modules/commit/a41683d0d691de8f68ccc09dcda7369df504b07d))
## [2.0.5](https://github.com/jorisnoo/craft-modules/releases/tag/v2.0.5) (2026-07-10)

### Bug Fixes

- **LicensesCheck:** treat Unknown license status as warning, not ok ([be60dac](https://github.com/jorisnoo/craft-modules/commit/be60dac7c5999d1668ed19bb9c4892f432d0ea5e))

### Build System

- **deps:** bump actions/checkout from 6 to 7 ([834a962](https://github.com/jorisnoo/craft-modules/commit/834a96280ef5bd6eaa4e5e1bb0cba92aa713b8b5))
## [2.0.4](https://github.com/jorisnoo/craft-modules/releases/tag/v2.0.4) (2026-06-02)

### Features

- add HideSlugWithoutUri module to hide slug field from entries without URI format ([3e5b61a](https://github.com/jorisnoo/craft-modules/commit/3e5b61aa7ea988e925288e5e0bd64ed8409ad937))
- **TextSnippetTwigFunction:** support multi-entry-type snippets with optional type priority ([22e64f1](https://github.com/jorisnoo/craft-modules/commit/22e64f198bbdef5a4faba70b76329627214079e4))
## [2.0.3](https://github.com/jorisnoo/craft-modules/releases/tag/v2.0.3) (2026-06-02)

### Features

- add dual-layout support to TextSnippetTwigFunction with per-request memoization ([f351060](https://github.com/jorisnoo/craft-modules/commit/f351060e7d0b5a97bc108bc7d8b89534d7de1e59))
## [2.0.2](https://github.com/jorisnoo/craft-modules/releases/tag/v2.0.2) (2026-05-15)

### Features

- add FocalPointPicker module for selecting asset focal points ([c6b9bde](https://github.com/jorisnoo/craft-modules/commit/c6b9bdebe9724d47c96ec0d76250ca563f204695))
## [2.0.1](https://github.com/jorisnoo/craft-modules/releases/tag/v2.0.1) (2026-05-14)

### Features

- add RedirectDashboard module to redirect CP dashboard to entries ([8efbde4](https://github.com/jorisnoo/craft-modules/commit/8efbde47d8ddd35fe0602e6c6ba504b81cab5cac))

### Chores

- add MIT license and package metadata ([aaf7ff9](https://github.com/jorisnoo/craft-modules/commit/aaf7ff959b517444c1f98a016691f61aaa4ea848))
## [2.0.0](https://github.com/jorisnoo/craft-modules/releases/tag/v2.0.0) (2026-05-12)

### ⚠ BREAKING CHANGES

- add EnvironmentIndicator, remove SidebarRelations ([1754d88](https://github.com/jorisnoo/craft-modules/commit/1754d88186bdeddc5fe44ea92d642ec2ceac66cd))

### Features

- **ohdear:** add Astray license status handling ([7a23d8d](https://github.com/jorisnoo/craft-modules/commit/7a23d8db58f7de09f46d3e74ab6df9997df58c0e))
- **ohdear:** add LicensesCheck health check ([84da891](https://github.com/jorisnoo/craft-modules/commit/84da89150339a76fb2b32b439981484a55e621c2))
- integrate ohdear queue health check error handling ([e06f0ce](https://github.com/jorisnoo/craft-modules/commit/e06f0ce3e508d0265b4a43a9f080630a6c624a33))
- integrate craft-twig-helpers module ([2cf3ef0](https://github.com/jorisnoo/craft-modules/commit/2cf3ef031654ee29b663f68832166728cffc1587))
- integrate craft-seo-preview plugin ([fd87e0a](https://github.com/jorisnoo/craft-modules/commit/fd87e0a03a1af3493bd17916d426104e84bdf017))
- integrate craft-sitemap and remote-sync modules ([19e09c1](https://github.com/jorisnoo/craft-modules/commit/19e09c1dea415913db85296f8beb310e50b1bed9))
- add TextSnippetTwigFunction module and configure Pest testing ([814ad5d](https://github.com/jorisnoo/craft-modules/commit/814ad5d5601790e748cbdc8d1eb2ac1f435539be))
- add database backup cleanup console command ([6a2ef3c](https://github.com/jorisnoo/craft-modules/commit/6a2ef3c5ebc09d2afa1a410e75f1008666aeed32))
- **FlareExceptionFilter:** filter HTTP exceptions from Flare error tracking ([fed03d9](https://github.com/jorisnoo/craft-modules/commit/fed03d9981500e2374c7e6202c11ff13e97eec38))
- **HideUserPermissions:** hide permissions tab from user edit screen ([cb7e45f](https://github.com/jorisnoo/craft-modules/commit/cb7e45f0e6923320c8f0926431b70805def9b4c7))
- **CpCss:** add module for custom control panel login styles ([9ac4784](https://github.com/jorisnoo/craft-modules/commit/9ac4784dd97e5538298cc04dfe7be441bb3a16f2))
- **EnvironmentIndicator:** add hover color and collapsed sidebar styling ([df26a07](https://github.com/jorisnoo/craft-modules/commit/df26a076eba660e9c416497501b7990cbd911339))
- add EnvironmentIndicator, remove SidebarRelations ([1754d88](https://github.com/jorisnoo/craft-modules/commit/1754d88186bdeddc5fe44ea92d642ec2ceac66cd))
- add AnalyticsNavLink module ([cf410db](https://github.com/jorisnoo/craft-modules/commit/cf410db0723859d598253b5ee64b97e37a0911fb))

### Bug Fixes

- **MakeUsersEditors:** use user.firstSave instead of event.isNew ([6faeee5](https://github.com/jorisnoo/craft-modules/commit/6faeee5e4506292f8578f647b4b55b65ddbaa9f0))
- **EnvironmentIndicator:** remove emoji icon styling ([47129cb](https://github.com/jorisnoo/craft-modules/commit/47129cba641eea402b9430f402c3eac17a92a98e))
- env label ([e630bba](https://github.com/jorisnoo/craft-modules/commit/e630bbab2097176834f50791574ac6a818da28f6))
- **EnvironmentIndicator:** update CSS selectors to a#system-info ([1ce35ec](https://github.com/jorisnoo/craft-modules/commit/1ce35ec8879863afe0419c8acb3385ea96623f0a))

### Code Refactoring

- **MakeUsersEditors:** migrate to element events with duplicate group prevention ([5bb0428](https://github.com/jorisnoo/craft-modules/commit/5bb0428ce1c94bc0e39851ca312eed58eb3b6a36))
- remove HideUserPermissions module ([d475c26](https://github.com/jorisnoo/craft-modules/commit/d475c2652a8f0ebb0f138fb13afa2cf0c43de503))
- **ohdear:** include license info in cache fingerprint and avoid redundant lookups ([d07fab2](https://github.com/jorisnoo/craft-modules/commit/d07fab2c5f04589ed6404f31008feb8f1540c46a))
- **modules:** add CpNavItems and centralize initialization ([a43a01d](https://github.com/jorisnoo/craft-modules/commit/a43a01dc03790b43ff58300fd1d544bed4ed5871))

### Documentation

- correct backup retention default in DbController ([febf591](https://github.com/jorisnoo/craft-modules/commit/febf591dc85c0e651efa5a2ac6dc537fd6712de0))
- restructure README for centralized module registration and updated documentation ([2a19ffd](https://github.com/jorisnoo/craft-modules/commit/2a19ffd7884575839f6dbfd7256943a6cc59db42))

### Continuous Integration

- simplify dependabot auto-merge workflow and update login logo styling ([fbe012b](https://github.com/jorisnoo/craft-modules/commit/fbe012be5d7b260fafa7a6272ab1ff58b6cd1c6d))
- add release automation with GitHub Actions and Shipmark ([49d247e](https://github.com/jorisnoo/craft-modules/commit/49d247e7e55cf8ad9082fd2cba8b9e4b2ac066bf))

### Chores

- pin dependencies to stable releases ([109a865](https://github.com/jorisnoo/craft-modules/commit/109a8651b541084e1145f7246e05951ef510d5ec))
- upgrade to Craft 5 and extract modules to separate packages ([9703bbc](https://github.com/jorisnoo/craft-modules/commit/9703bbc81fa538655f16be476d8738aeb4c6dcd3))

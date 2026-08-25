# VibeLMS project context

## Current state

VibeLMS is an independent WordPress LMS fork based on the LifterLMS trunk source snapshot. Public LifterLMS identifiers and the `lifterlms` text domain remain unchanged for compatibility while the fork is being adapted to the project requirements.

The WordPress plugin metadata now presents the product as VibeLMS version `0.0.06`, authored by Mazhenov Design with `https://mazhenov.kz` as the plugin site. The internal LifterLMS compatibility version remains `10.2.0` in the core class and is not the public plugin header version. Public VibeLMS updates increment the final numeric segment: the next update must use `0.0.07`.

The first project layer is opt-in diagnostics. It reuses the existing LifterLMS log system and writes structured events to the `vibelms-diagnostics` handle with redaction of common secrets. It records PHP warnings/notices, uncaught throwables and fatal shutdown errors when `VIBELMS_DEBUG` is enabled.

The first VibeLMS-specific feature layer adds the neutral `vibelms_student` and `vibelms_observer` roles. The observer receives only VibeLMS report/export/material capabilities; it does not receive WordPress editor capabilities.

The repository now includes `scripts/build-installable-package.sh`, which creates a production-style ZIP with Composer runtime dependencies while keeping development dependencies out of the repository. The package build skips Composer dev scripts because those scripts configure PHPCS, which is intentionally absent from a `--no-dev` production install.

The runtime Composer `vendor/` directory is now tracked so the GitHub Push-to-Deploy integration receives the required `vendor/autoload.php`. It contains only production dependencies; development tools remain excluded.

The admin interface now uses VibeLMS branding, shows the public VibeLMS version, hides the old license/support/add-ons/promotional dashboard blocks, and keeps only local content/report shortcuts. The dashboard uses one normal postbox column after the removed promotional side column; this prevents the empty WordPress side-sortables area from creating a large vertical gap. The remaining report shortcut is Russian. Compiled production CSS and JS are tracked because Push-to-Deploy does not execute npm; the missing `admin.css` was the cause of the unstyled dashboard and oversized logo. The customized admin stylesheet now uses `VIBELMS_VERSION` for cache busting.

The old LifterLMS review-request module was removed from the VibeLMS load path, so the admin footer and review notice no longer contain LifterLMS promotional text or WordPress.org review links.

VibeLMS remains a reusable LMS engine for different projects. Project-specific branding, languages, slides, videos, documents, companies, questions, passing scores and certificate text must be supplied through configurable content/settings rather than hard-coded client names or assets.

The dashboard analytics enqueue now supports both the legacy LifterLMS screen base and the `toplevel_page_llms-dashboard` screen produced by the VibeLMS menu. The official WordPress.org Russian translation package is included in `languages/` as `lifterlms-ru_RU.po/.mo` plus JavaScript JSON catalogs, and custom VibeLMS admin labels are Russian.

The dashboard analytics path now also recognizes the `page=llms-dashboard` query directly. Widget AJAX starts before Google Charts initialization, and the optional student filter/Google Charts APIs are guarded so a missing optional script cannot leave the metric cards stuck in the loading state. The customized analytics asset uses `VIBELMS_VERSION` for cache busting instead of the unchanged internal LifterLMS version.

## Repository

- Local path: `/Users/diasmazhenov/vibecode/VibeLMS`
- Main branch: `main`
- Upstream reference: `https://github.com/gocodebox/lifterlms`
- Target repository: `https://github.com/DiasMazhenov/VibeLMS`
- The local Git history is a clean source snapshot plus the VibeLMS diagnostics commit; the original LifterLMS repository is retained as `upstream`.

## Checks

- PHP syntax and PHPCS must pass for changed PHP files.
- Current checks: PHP lint and `git diff --check` passed for the current change; PHPCS is unavailable because the tracked production `vendor/` intentionally excludes `vendor/bin/phpcs`. Diagnostics and role-definition smoke tests passed earlier. Legacy JS syntax and minified analytics syntax pass; `assets/css/admin.css`, `assets/js/llms.js` and their production variants are present. The analytics query fallback and optional-library guards are checked before release. The Russian translation catalogs pass `msgfmt --check`. The production package build passed with `--no-scripts`; `unzip -t` and the packaged Composer autoloader smoke test also passed. The runtime `vendor/autoload.php` is present and `composer show --direct --no-dev` lists only the three production packages.
- Local package artifact: `/Users/diasmazhenov/vibecode/VibeLMS/dist/vibelms-0.0.06.zip` (generated, ignored). It is for staging installation and is not committed to Git.
- Targeted PHPUnit is blocked before test discovery because `tmp/tests/wordpress-tests-lib/includes/functions.php` is not installed. A WordPress test library and database are required to run it.
- `composer.lock`, generated assets and `tmp/` stay untracked according to upstream rules. Runtime `vendor/` is tracked specifically for Push-to-Deploy; dev dependencies must not be installed before committing it.

## Activation incident

The production error log showed `lifterlms.php:48` failing because `/wp-content/plugins/VibeLMS/vendor/autoload.php` was missing. The GitHub source tree previously ignored `vendor/`, so Push-to-Deploy copied an incomplete plugin. The fix is to track the production Composer runtime directory. The same log also contains repeated `WP_DEBUG already defined` warnings in `wp-config.php`; those are a separate server configuration cleanup and are not the activation blocker.

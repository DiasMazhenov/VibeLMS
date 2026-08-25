# VibeLMS project context

## Current state

VibeLMS is an independent WordPress LMS fork based on the LifterLMS trunk source snapshot. Public LifterLMS identifiers and the `lifterlms` text domain remain unchanged for compatibility while the fork is being adapted to the project requirements.

The WordPress plugin metadata now presents the product as VibeLMS version `0.0.15`, authored by Mazhenov Design with `https://mazhenov.kz` as the plugin site. The internal LifterLMS compatibility version remains `10.2.0` in the core class and is not the public plugin header version. Public VibeLMS updates increment the final numeric segment.

The first project layer is opt-in diagnostics. It reuses the existing LifterLMS log system and writes structured events to the `vibelms-diagnostics` handle with redaction of common secrets. It records PHP warnings/notices, uncaught throwables and fatal shutdown errors when `VIBELMS_DEBUG` is enabled.

The first VibeLMS-specific feature layer adds the neutral `vibelms_student` and `vibelms_observer` roles. The observer receives only VibeLMS report/export/material capabilities; it does not receive WordPress editor capabilities. Existing installations refresh the administrator report capabilities through the role-version migration.

The repository now includes `scripts/build-installable-package.sh`, which creates a production-style ZIP with Composer runtime dependencies while keeping development dependencies out of the repository. The package build skips Composer dev scripts because those scripts configure PHPCS, which is intentionally absent from a `--no-dev` production install.

The runtime Composer `vendor/` directory is now tracked so the GitHub Push-to-Deploy integration receives the required `vendor/autoload.php`. It contains only production dependencies; development tools remain excluded.

The admin interface now uses VibeLMS branding, shows the public VibeLMS version, hides the old license/support/add-ons/promotional dashboard blocks, and keeps only local content/report shortcuts. The dashboard uses one normal postbox column after the removed promotional side column; this prevents the empty WordPress side-sortables area from creating a large vertical gap. The remaining report shortcut is Russian. Compiled production CSS and JS are tracked because Push-to-Deploy does not execute npm; the missing `admin.css` was the cause of the unstyled dashboard and oversized logo. The customized admin stylesheet now uses `VIBELMS_VERSION` for cache busting.

The old LifterLMS review-request module was removed from the VibeLMS load path, so the admin footer and review notice no longer contain LifterLMS promotional text or WordPress.org review links.

The access-group creation route keeps the internal `post_type=llms_membership` identifier for compatibility, but its visible admin labels are now `Access Groups` / `Группы доступа`. The update-safe role installer refreshes the core post-type capabilities on the first admin request after Push-to-Deploy, so existing active installations regain the capability required to create access groups even when the activation hook did not run.

Generated runtime files required by WordPress are tracked for Push-to-Deploy: `includes/class.llms.l10n.frontend.php` and the production `libraries/lifterlms-blocks/assets/` bundle. They must not be excluded by `.gitignore`, because the deployment server does not run the localization or Gutenberg block build.

The core Gutenberg block build is also tracked in `blocks/`. This includes `blocks/pricing-table/`, which is required for WordPress to recognize `llms/pricing-table` in existing course and access-group content.

VibeLMS remains a reusable LMS engine for different projects. Project-specific branding, languages, slides, videos, documents, companies, questions, passing scores and certificate text must be supplied through configurable content/settings rather than hard-coded client names or assets.

The dashboard analytics enqueue now supports both the legacy LifterLMS screen base and the `toplevel_page_llms-dashboard` screen produced by the VibeLMS menu. The official WordPress.org Russian translation package is included in `languages/` as `lifterlms-ru_RU.po/.mo` plus JavaScript JSON catalogs, and custom VibeLMS admin labels are Russian.

The dashboard analytics path now also recognizes the `page=llms-dashboard` query directly. Widget AJAX starts before Google Charts initialization, and the optional student filter/Google Charts APIs are guarded so a missing optional script cannot leave the metric cards stuck in the loading state. The customized analytics asset uses `VIBELMS_VERSION` for cache busting instead of the unchanged internal LifterLMS version.

The universal VibeLMS platform layer stores employee identity fields (`company`, `employee_name`, `region`, `station`) in user meta and exposes them through `[vibelms_student_identity]`. When the optional setting is enabled, quiz access requires these fields. The same form is available as an Elementor widget.

Completed quiz attempts are copied into the protected `{$wpdb->prefix}vibelms_attempts` table. The journal is available to administrators and observers, supports paginated viewing and UTF-8 CSV export, and stores email, identity snapshot, locale, attempt number, question count, correct answers, grade, status and dates. The table is created on activation and through the update-safe admin migration.

General settings now expose the project-neutral assessment rule: target question count defaults to `15`, passing score defaults to `100%`, optional identity requirement, and an optional certificate template. A successful attempt must match the configured question count and pass threshold before the selected certificate template is awarded. The question builder itself is not limited to 15 questions.

VibeLMS now includes a complete administrator-only transfer screen at **VibeLMS → Перенос данных**. The ZIP archive contains VibeLMS settings, courses with nested lessons/quizzes/questions/access plans, access groups, certificate templates, LMS users, enrollments, quiz attempts, VibeLMS report rows and referenced local media. Import maps users by email and content by the generator's source-ID marker, replaces media URLs, preserves featured images, and adds data without deleting existing records. Passwords, tokens and sensitive user meta are excluded; only newly created users receive a `vibelms_transfer_needs_password_reset` marker. The archive format is versioned and import diagnostics are shown in the admin notice and VibeLMS log when debug mode is enabled.

Elementor widget registration supports the modern `elementor/widgets/register` hook with backward compatibility for older Elementor releases. The visible category is `VibeLMS`; the internal category key remains `lifterlms` so saved Elementor documents continue to resolve. The old external Elementor upsell text was removed and replaced with a local VibeLMS note.

Translated UI strings from the compatibility domains replace visible `LifterLMS`/`Lifterlms` product-name text with `VibeLMS`. Internal class names, hooks, post types, shortcodes, text domains and compatibility URLs are intentionally not mass-renamed.

The course builder now uses the public `VIBELMS_VERSION` for its builder CSS, JavaScript and popover assets, preventing stale internal LifterLMS asset URLs after a VibeLMS update. Builder page output records the prepared question-type count and IDs through the existing `vibelms-diagnostics` handle when `VIBELMS_DEBUG` is enabled; this makes an empty “Add Question” panel distinguishable between a PHP data problem and a browser asset/cache problem.

The course-builder failure `Cannot read properties of undefined (reading 'start')` was caused by the production `llms.js` bundle missing `LLMS.Spinner`, even though builder quiz views call it. The generated `llms-spinner` asset is now tracked and loaded as a dependency of the core `llms` script and the builder, so quiz question creation works on admin and frontend paths that use the shared script.

Advanced Quizzes 3.3.0 is now bundled into VibeLMS rather than installed as a separate plugin. The bundle includes question-type behavior, the question bank, manual-review notifications/reporting, secure file-upload AJAX handling, admin question settings, frontend templates, CodeMirror, Quill Snow styling, and production JS/CSS assets. The existing core question-type definitions remain the compatibility base; the bundled module removes upgrade prompts and attaches the full behavior. If a standalone Advanced Quizzes plugin is already active, VibeLMS reuses its `llms_aq()` integration instead of loading a second copy.

The Russian catalog now translates the True/False labels as `Верно`, `Неверно`, and `Верно или неверно`, and translates Scale as `Шкала`.

The Advanced Questions buttons are native VibeLMS functionality. Their core definitions now set `upgrade` to `false` directly, so the course builder cannot render them as disabled premium upsells even if a stale cache or delayed add-on initialization is present.

## Repository

- Local path: `/Users/diasmazhenov/vibecode/VibeLMS`
- Main branch: `main`
- Upstream reference: `https://github.com/gocodebox/lifterlms`
- Target repository: `https://github.com/DiasMazhenov/VibeLMS`
- The local Git history is a clean source snapshot plus the VibeLMS diagnostics commit; the original LifterLMS repository is retained as `upstream`.

## Checks

- PHP syntax and PHPCS must pass for changed PHP files.
- Current checks: PHP lint for the transfer module and main plugin, shell syntax, `git diff --check`, existing VibeLMS smoke checks, diagnostics and role-definition checks passed; PHPCS is unavailable because the tracked production `vendor/` intentionally excludes `vendor/bin/phpcs`. Legacy JS syntax and minified analytics syntax pass; `assets/css/admin.css`, `assets/js/llms.js` and their production variants are present. The analytics query fallback and optional-library guards are checked before release. The Russian translation catalogs pass `msgfmt --check`. Full PHPUnit is currently blocked because `vendor/bin/phpunit` and the WordPress test library/database are not installed.
- Local package artifact: `/Users/diasmazhenov/vibecode/VibeLMS/dist/vibelms-0.0.15.zip` (generated, ignored). It is for staging installation and is not committed to Git.
- Targeted PHPUnit is blocked before test discovery because `tmp/tests/wordpress-tests-lib/includes/functions.php` is not installed. A WordPress test library and database are required to run it.
- `composer.lock`, generated assets and `tmp/` stay untracked according to upstream rules. Runtime `vendor/` is tracked specifically for Push-to-Deploy; dev dependencies must not be installed before committing it.

## Activation incident

The production error log showed `lifterlms.php:48` failing because `/wp-content/plugins/VibeLMS/vendor/autoload.php` was missing. The GitHub source tree previously ignored `vendor/`, so Push-to-Deploy copied an incomplete plugin. The fix is to track the production Composer runtime directory. The same log also contains repeated `WP_DEBUG already defined` warnings in `wp-config.php`; those are a separate server configuration cleanup and are not the activation blocker.

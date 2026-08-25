# VibeLMS project context

## Current state

VibeLMS is an independent WordPress LMS fork based on the LifterLMS trunk source snapshot. Public LifterLMS identifiers and the `lifterlms` text domain remain unchanged for compatibility while the fork is being adapted to the project requirements.

The first project layer is opt-in diagnostics. It reuses the existing LifterLMS log system and writes structured events to the `vibelms-diagnostics` handle with redaction of common secrets. It records PHP warnings/notices, uncaught throwables and fatal shutdown errors when `VIBELMS_DEBUG` is enabled.

The first VibeLMS-specific feature layer adds the neutral `vibelms_student` and `vibelms_observer` roles. The observer receives only VibeLMS report/export/material capabilities; it does not receive WordPress editor capabilities.

The repository now includes `scripts/build-installable-package.sh`, which creates a production-style ZIP with Composer runtime dependencies while keeping development dependencies out of the repository. The package build skips Composer dev scripts because those scripts configure PHPCS, which is intentionally absent from a `--no-dev` production install.

## Repository

- Local path: `/Users/diasmazhenov/vibecode/VibeLMS`
- Main branch: `main`
- Upstream reference: `https://github.com/gocodebox/lifterlms`
- Target repository: `https://github.com/DiasMazhenov/VibeLMS`
- The local Git history is a clean source snapshot plus the VibeLMS diagnostics commit; the original LifterLMS repository is retained as `upstream`.

## Checks

- PHP syntax and PHPCS must pass for changed PHP files.
- Current checks: PHP lint passed; diagnostics and role-definition smoke tests passed; PHPCS and `git diff --check` passed for the changed files. The production package build passed with `--no-scripts`; `unzip -t` and the packaged Composer autoloader smoke test also passed.
- Local package artifact: `/Users/diasmazhenov/vibecode/VibeLMS/dist/vibelms-0.1.0.zip` (generated, ignored, 7.8 MB). It is for staging installation and is not committed to Git.
- Targeted PHPUnit is blocked before test discovery because `tmp/tests/wordpress-tests-lib/includes/functions.php` is not installed. A WordPress test library and database are required to run it.
- `vendor/`, `composer.lock`, generated assets and `tmp/` stay untracked according to upstream rules.

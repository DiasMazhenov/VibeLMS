# VibeLMS project context

## Current state

VibeLMS is a WordPress LMS fork based on the LifterLMS trunk source snapshot. Public LifterLMS identifiers and the `lifterlms` text domain remain unchanged for compatibility while the fork is being adapted to the Bifimbill requirements.

The first project layer is opt-in diagnostics. It reuses the existing LifterLMS log system and writes structured events to the `vibelms-diagnostics` handle with redaction of common secrets. It records PHP warnings/notices, uncaught throwables and fatal shutdown errors when `VIBELMS_DEBUG` is enabled.

## Repository

- Local path: `/Users/diasmazhenov/vibecode/VibeLMS`
- Main branch: `main`
- Upstream reference: `https://github.com/gocodebox/lifterlms`
- Target repository: `https://github.com/DiasMazhenov/VibeLMS`
- The local Git history is a clean source snapshot plus the VibeLMS diagnostics commit; the original LifterLMS repository is retained as `upstream`.

## Checks

- PHP syntax and PHPCS must pass for changed PHP files.
- Current checks: PHP lint passed; diagnostics redaction smoke test passed; PHPCS passed for the new diagnostics and regression test.
- Targeted PHPUnit is blocked before test discovery because `tmp/tests/wordpress-tests-lib/includes/functions.php` is not installed. A WordPress test library and database are required to run it.
- `vendor/`, `composer.lock`, generated assets and `tmp/` stay untracked according to upstream rules.

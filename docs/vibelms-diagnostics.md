# VibeLMS diagnostics

Diagnostics are opt-in. On a staging site, add this to `wp-config.php` before the WordPress bootstrap:

```php
define( 'VIBELMS_DEBUG', true );
```

Events are written through the compatible WordPress LMS logger to the `vibelms-diagnostics` handle. View them at `VibeLMS -> Статус -> Логи` or in `uploads/llms-logs/`. Sensitive keys such as passwords, tokens, nonces, cookies and authorization data are redacted.

Disable the flag after troubleshooting; verbose request logging is not intended for production.

# Imagine Partner Portal

PHP partner portal for partner applications, partner/admin dashboards, leads, resources, 2FA, and firewall logs.

## Local setup

1. Install PHP 8.1+ and Composer.
2. Install dependencies:

   ```sh
   composer install
   ```

3. Copy the local config example:

   ```sh
   cp includes/config.local.example.php includes/config.local.php
   ```

4. Fill in `includes/config.local.php` with database credentials, `APP_URL`, `MAIL_FROM`, and optional `OPENWEATHER_API_KEY`.
5. Serve the project from the repository root with Apache/cPanel or a PHP development server.

## Configuration

Runtime configuration is loaded in this order:

1. Environment variables
2. `includes/config.local.php`
3. Safe defaults in `includes/config.php`

Do not commit `includes/config.local.php`, database credentials, API keys, uploaded files, or generated archives.

## Current modernization direction

- `includes/bootstrap.php` centralizes session setup, Composer autoloading, escaping, CSRF helpers, PDO creation, login finalization, and firewall audit logging.
- New or touched pages should prefer `require_once __DIR__ . '/includes/bootstrap.php';` or the correct relative path instead of creating their own PDO/session boilerplate.
- Authentication pages now use CSRF checks and session ID regeneration during login/2FA handoff.

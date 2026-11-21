# Hostinger deployment — PlantCore Traceability

This runbook is for the same Business Web Hosting account that hosts `devalejandre.com` and its separate LPA application. It is **not a permission to modify** either existing site.

## Preconditions

- Dedicated subdomain: `traceability.devalejandre.com` with HTTPS enabled.
- Separate private application directory:
  `/home/u641948971/domains/devalejandre.com/apps/traceability`
- Public document root for this subdomain only:
  `/home/u641948971/domains/devalejandre.com/public_html/traceability`
- PHP 8.4 executed by the subdomain. On this Hostinger account, LPA required an explicit PHP handler in the subdomain's `.htaccess`.
- A **new** MariaDB database and account for Traceability. Never reuse the LPA database credentials.
- Backup the current hosting files and database settings first.

The web server must serve **only** the contents of Laravel's `public` directory. Never upload `.env`, `vendor`, `storage`, `app`, `bootstrap` or other project internals to the public directory.

## Phase 1 — Private installation

After confirming the private directory is empty:

```bash
cd /home/u641948971/domains/devalejandre.com/apps/traceability
git clone https://github.com/diegoarmando025/industrial-traceability-api.git .
/opt/alt/php84/usr/bin/php /usr/local/bin/composer2 install --no-dev --prefer-dist --optimize-autoloader --no-interaction
cp .env.example .env
```

Check the PHP CLI binary and Composer path on this specific hosting account before running commands; a different CLI default may use an unsupported PHP version.

Edit the **private** `.env`:

```dotenv
APP_NAME="PlantCore Traceability"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://traceability.devalejandre.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=REPLACE_WITH_HOSTINGER_DATABASE
DB_USERNAME=REPLACE_WITH_HOSTINGER_USER
DB_PASSWORD=REPLACE_LOCALLY

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=traceability.devalejandre.com
LOG_CHANNEL=single
LOG_LEVEL=warning
```

Hostinger may provide a different DB host. Do not paste secret values into GitHub, issues, logs, or chats.

```bash
/opt/alt/php84/usr/bin/php artisan key:generate
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views
chmod -R u+rwX storage bootstrap/cache
/opt/alt/php84/usr/bin/php artisan migrate --force
```

Do not execute `migrate:fresh`; it deletes existing database tables. `TraceabilityDemoSeeder` intentionally refuses to run in production. Do not change `APP_ENV` to `local` to bypass it.

## Phase 2 — Build front-end assets locally

On a development workstation, in an up-to-date checkout of the same Git commit:

```bash
npm ci
npm run build
```

Copy `public/build/` to **both**:

1. `apps/traceability/public/build/` (Laravel's Vite manifest lookup), and
2. `public_html/traceability/build/` (the browser's assets).

If any other static assets live under `public`, copy only the public assets to the subdomain public directory, never the private project. Do not upload `node_modules`.

## Phase 3 — Public front controller

Create `public_html/traceability/index.php` with:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$basePath = '/home/u641948971/domains/devalejandre.com/apps/traceability';

if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $basePath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

The subdomain's `.htaccess` must preserve the PHP 8.4 handler and Laravel's rewrite rules. Example after backing up any existing file:

```apache
DirectoryIndex index.php

<FilesMatch "\\.(php|phtml)$">
    SetHandler application/x-lsphp84
</FilesMatch>

<IfModule mod_rewrite.c>
    Options -MultiViews -Indexes
    RewriteEngine On

    RewriteCond %{HTTP_HOST} !^traceability\\.devalejandre\\.com$ [NC]
    RewriteRule ^ - [F,L]

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

These rules must be validated on the actual hosting configuration. The domain-host rule blocks requests through `devalejandre.com/traceability` but does not replace application authentication and plant-scoped authorization.

## Phase 4 — Validation before publishing the link

- `https://traceability.devalejandre.com/login` loads without framework debug details.
- Requests to `https://devalejandre.com/traceability/` return 403, while the main site and LPA remain unchanged.
- `/dashboard` and `/boxes` redirect unauthenticated visitors to the login form.
- No private files are reachable via the public host.
- `APP_ENV=production` and `APP_DEBUG=false`.
- Run the test suite **locally/CI** and review GitHub Actions before merging changes.
- Verify the log files under private `storage/logs` if a 500 response occurs. Never expose the full log or secrets publicly.

### Do not publish credentials yet

The existing demo seeder does not run in production and the browser application's supervisor role can modify and release boxes. Until a separate isolated, read-only public presentation is implemented and reviewed, keep the site login private and do **not** publish a common supervisor password on DevAlejandre.

The existing LPA public read-only demo is a useful pattern, but Traceability needs its own safely isolated records and read-only views before visitors can use it anonymously.

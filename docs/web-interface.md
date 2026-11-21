# Web interface — development setup

This branch introduces a first, deliberately limited web interface with Laravel session authentication, a plant-scoped dashboard and AdminLTE 4 assets. The existing API endpoints and Sanctum Bearer-token authorization are unchanged.

## Installation

From the repository root:

```powershell
npm install
npm run build
php artisan test
php artisan serve
```

The dependency lockfile **must** be committed after `npm install` to keep `npm ci` reproducible. Do not merge this branch before that step and a passing build/test run.

## Local web sign-in

The demo seeder uses random, unknown passwords and never publishes default credentials. For local development only, change the password on a seeded supervisor in Tinker (never commit or paste your password):

```php
$user = \App\Models\User::where('email', 'supervisor@traceability.example.invalid')->firstOrFail();
$user->password = 'REPLACE-WITH-YOUR-OWN-LOCAL-PASSWORD';
$user->save();
```

Open `http://127.0.0.1:8000/login` and sign in. Change demo passwords only in a disposable local database.

## Current functionality

- Rate-limited web login, session regeneration, CSRF-protected logout.
- Protected dashboard displaying counts and the last ten boxes assigned to the logged-in user's plants.
- Menu entries for scans, boxes and release are clearly marked as not implemented.
- HTTP feature tests for login, logout, guest redirect and plant isolation.

## Not yet implemented

Box registration, scanning, release, and audit views on the web. Those remain available through the API. No screenshots or metrics from a production plant are included.

AdminLTE, Bootstrap and Bootstrap Icons are third-party open-source dependencies; retain their published license notices.

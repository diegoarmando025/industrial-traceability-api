# Technical reviewer walkthrough — PlantCore Traceability

This guide evaluates the actual Laravel web application included in this repository. All identifiers below are fictional; there is no public hosted installation or link to client systems.

## Prepare a clean local installation

Requirements: PHP 8.4.1+, Composer, Node.js 22 and npm, SQLite support.

```bash
git clone https://github.com/diegoarmando025/industrial-traceability-api.git
cd industrial-traceability-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=TraceabilityDemoSeeder
npm ci
npm run build
php artisan test
php artisan serve
```

For Windows PowerShell, use `Copy-Item .env.example .env`. If necessary, create `database/database.sqlite` before migrations, and ensure `DB_CONNECTION=sqlite` is configured. Use a **disposable local database** — do not use `migrate:fresh` on data you need to keep.

## Set a local browser password

The seeder creates fictional accounts with unknown randomized passwords. Set your own **local** password for the supervisor account:

```bash
php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'supervisor@traceability.example.invalid')->firstOrFail();
$user->password = 'REPLACE-WITH-A-STRONG-LOCAL-PASSWORD';
$user->save();
```

The web login uses a Laravel session and **does not create Sanctum Bearer tokens**. For the API workflow, create scoped tokens using the [Postman setup](postman/README.md).

## Inspect the working interface

| Step | Route | What to verify |
| --- | --- | --- |
| 1 | `/login` | Sign in as the fictional supervisor |
| 2 | `/dashboard` | Dashboard summarizes accessible plant data |
| 3 | `/boxes` | Search/filter the list and open a seeded box |
| 4 | `/boxes/create` | Register a new box with an invented code, part number and serial |
| 5 | `/boxes/{id}` | Submit the part-number and serial-number scans, then a package-label scan |
| 6 | `/boxes/{id}` | Deliberately enter one wrong value and inspect the rejected scan history; correct it and confirm validation |
| 7 | `/boxes/{id}` | Release the box as supervisor after all three **latest** scans are valid |
| 8 | `/boxes/{id}` | Inspect the scan and event history including the responsible user |

The demonstration package label uses `BOX:{code}:{part_number}:{serial_number}`. A rejected latest scan blocks release until a correct scan supersedes it. The audit history retains rejected attempts.

## API verification

The versioned API at `/api/v1` is separate from the browser interface. Import the [Postman collection](postman/industrial-traceability.postman_collection.json) and follow the [setup instructions](postman/README.md), or inspect the [OpenAPI contract](openapi.json). The API uses explicit token abilities **and** per-plant roles.

## What to capture for a portfolio

Use screenshots from the running application (not mockups): dashboard, list of boxes, validation state, rejected scan history and supervisor release event. Use only invented data and remove personal information, passwords and tokens.

## Architectural boundaries

This sample is not a deployed MES or production-ready identity service. There is no public hosted instance, ERP integration, production scanner connection or third-party customer data. Consult the [technical case study](portfolio-case-study.md) for access rules and known constraints.

## Related independent project

[PlantCore LPA](https://github.com/diegoarmando025/plantcore-lpa) demonstrates a different industrial workflow: layer-based process audits, calendar planning, corrective actions and plant-scoped reporting. The repositories are separate by design.

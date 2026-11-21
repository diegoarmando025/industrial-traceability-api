# Industrial Traceability API

REST API for registering production boxes, validating scanned identifiers, and controlling their release.

Built as an independent, fictional industrial workflow demonstration. It does not include customer data, proprietary validation rules, or code from a production system.

## Stack

- PHP 8.4.1+
- Laravel 13
- Laravel Sanctum (Bearer token authentication)
- SQLite for local development and automated tests
- PHPUnit

## Workflow

1. Register a box with a plant ID, box code, part number, and serial number.
2. Validate three scan types: `part_number`, `serial_number`, and `package_label`.
3. Every scan is recorded, including rejected values.
4. A release is permitted only when the **latest scan of each required type** is valid.
5. Successful and blocked release attempts are recorded as events.
6. A released box cannot be scanned or released again.

The expected package label in this demonstration follows the format `BOX:{code}:{part_number}:{serial_number}`.

## Plant access model

A box belongs to one plant. Users belong to explicitly assigned plants through a membership with one of three roles:

| Role | Read | Create | Scan | Release |
| --- | --- | --- | --- | --- |
| viewer | Yes | No | No | No |
| operator | Yes | Yes | Yes | No |
| supervisor | Yes | Yes | Yes | Yes |

A request must pass **both** the Sanctum token ability check and the plant membership/role check. Cross-plant access is returned as HTTP `404` to avoid disclosing box existence; insufficient role within a member plant returns `403`.

Plant and membership provisioning is currently performed through trusted administrative code or Tinker only: there is no public endpoint for creating plants or assigning roles. For a local demo, after creating a user:

```php
$plant = \App\Models\Plant::create(['code' => 'QRO', 'name' => 'Demo Plant']);
$user->plants()->attach($plant->id, ['role' => 'supervisor']);
```

Existing boxes with no assigned plant remain inaccessible until an administrator maps them to a valid plant. This migration preserves existing demo data instead of automatically guessing a plant.

## Quick start

Requirements: PHP 8.4.1+ with SQLite support, Composer.

```bash
git clone https://github.com/diegoarmando025/industrial-traceability-api.git
cd industrial-traceability-api
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty `database/database.sqlite` file if it does not exist, and set `DB_CONNECTION=sqlite` in `.env`.

```bash
php artisan migrate
php artisan serve
```

On Windows PowerShell, use `Copy-Item .env.example .env` in place of `cp`; create the SQLite file with `New-Item database/database.sqlite -ItemType File` if it is missing.

## Authentication

Every API route requires a Laravel Sanctum Bearer token. This demo does **not** include a public login or token-issuance endpoint.

For local manual testing only, create a user and token using `php artisan tinker`:

```php
$user = \App\Models\User::factory()->create();
$token = $user->createToken('local-development', ['boxes:read', 'boxes:scan'])->plainTextToken;
echo $token;
```

Keep the token private and never commit it to Git. Include it in requests as `Authorization: Bearer <token>`.

### Token abilities

Use least-privilege Sanctum token abilities for each operation:

| Ability | Allows |
| --- | --- |
| `boxes:create` | Create new boxes |
| `boxes:read` | View boxes and their history |
| `boxes:scan` | Submit scan validations |
| `boxes:release` | Attempt release of boxes |

Tokens missing an ability receive HTTP `403 Forbidden`. By default, Sanctum tokens created **without** an abilities list have wildcard (`*`) access; always specify the required abilities when provisioning tokens. These checks restrict **API token operations**, not access to a particular plant, customer, or individual box. First-party cookie-authenticated requests also need a separate authorization strategy before they can be supported safely.

## OpenAPI contract

The API contract is available at [`docs/openapi.json`](docs/openapi.json) (OpenAPI 3.0.3). It documents the five current HTTP endpoints, request bodies, responses, Sanctum Bearer authentication and plant access requirements. You can import the JSON file into an OpenAPI-compatible viewer or API client. This is a contract document, **not** a hosted Swagger UI or a public token-issuance service.

## Endpoints

All routes have the `/api/v1` prefix.

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/boxes` | Create a box |
| GET | `/boxes/{box}` | Show box details |
| POST | `/boxes/{box}/scans` | Record and validate a scan |
| POST | `/boxes/{box}/release` | Attempt box release |
| GET | `/boxes/{box}/history` | Retrieve scans and audit events |

Example box creation:

```http
POST /api/v1/boxes
Authorization: Bearer <token>
Content-Type: application/json

{
  "plant_id": 1,
  "code": "BX-0001",
  "part_number": "PN-100",
  "serial_number": "SN-200"
}
```

Example scan:

```json
{
  "scan_type": "package_label",
  "value": "BOX:BX-0001:PN-100:SN-200"
}
```

Response codes include `201` for successful creation or accepted scans, `422` for rejected scans or invalid request data, `409` for blocked release, `401` for unauthenticated access, and `403` for missing token abilities.

## Tests

```bash
php artisan test
```

The feature suite covers authentication, token abilities, plant isolation and role permissions, blocked releases, rejected scans, a successful one-time release, and invalidation by a later incorrect scan.

## Current scope and limitations

This is a **technical demonstration**, not a production-ready manufacturing execution system.

- Plant-level access is enforced. There is no tenant/company hierarchy, centralized role provisioning interface, or independent per-box ACL.
- No production-grade identity provisioning or token lifecycle workflow.
- No external scanner, ERP, or MES integration.
- Concurrent release behavior is transaction-based but has not been load-tested or verified on multiple database engines.
- Continuous integration runs Laravel tests in GitHub Actions. OpenAPI is documented, but automated contract validation is not implemented yet.

## License and provenance

Developed as an independent portfolio demonstration using fictional identifiers and generic validation rules.

# DevAlejandre Traceability — Web + REST API

Laravel application for registering production boxes, validating scanned identifiers, and controlling their release through a web interface and a versioned REST API.

Built as an independent, fictional industrial workflow demonstration. It does not include customer data, proprietary validation rules, or code from a production system.

**Live interactive demo:** [Open Traceability](https://traceability.devalejandre.com/login) and choose **Entrar al demo interactivo**. Create fictional boxes, scan valid or invalid identifiers, release a validated box, and consult history and operational reports. The visitor uses the real app with plant-scoped permissions and shared fictional data; no confidential customer records are included.

[Reviewer walkthrough](docs/INTERACTIVE_PORTFOLIO_DEMO.md) · [OpenAPI specification](docs/openapi.json) · [LPA live demo](https://lpa.devalejandre.com/login) · [Service Desk live demo](https://service-desk.devalejandre.com/login).

**Start here:** [Web walkthrough](docs/web-reviewer-guide.md) · [Technical case study](docs/portfolio-case-study.md) · [OpenAPI contract](docs/openapi.json) · [Postman collection](docs/postman/industrial-traceability.postman_collection.json) · [Reproducible local demo](docs/local-demo.md)

## Web application

The repository includes a server-rendered web interface built with Blade, Bootstrap 5, AdminLTE 4 and Vite. After signing in with a **local** seeded account, a reviewer can view a plant-scoped dashboard, create boxes, submit part-number/serial/package-label scans, inspect the complete history and release a validated box as a supervisor.

The browser interface uses Laravel session authentication; the REST API remains separately protected by Sanctum Bearer token abilities. An [interactive hosted demo](https://traceability.devalejandre.com/login) supports the full fictional write workflow. A [read-only public overview](https://traceability.devalejandre.com/demo) is also available.

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

For browser use, install the front-end dependencies and build the assets before opening `/login`:

```bash
npm ci
npm run build
php artisan db:seed --class=TraceabilityDemoSeeder
php artisan serve
```

The demo users have **random passwords**. Set your own local password using the steps in the [web walkthrough](docs/web-reviewer-guide.md).

## API authentication

Every API route requires a Laravel Sanctum Bearer token. This demo does **not** expose a public API token-issuance endpoint. Browser users sign in through a separate session-based `/login` form.

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

The API contract is available at [`docs/openapi.json`](docs/openapi.json) (OpenAPI 3.0.3). It documents the five current HTTP endpoints, request bodies, responses, Sanctum Bearer authentication and plant access requirements. You can import the JSON file into an OpenAPI-compatible viewer or API client. This contract is validated automatically in GitHub Actions using `python3 scripts/validate_openapi.py` (JSON, local references, declared schemas and authentication) and `OpenApiContractTest` (actual Laravel routes and documented methods). It is **not** a hosted Swagger UI or a public token-issuance service.

## Public demonstration

[Open the read-only Traceability demo](https://traceability.devalejandre.com/demo). Three fictional scenarios show a successful release, a blocked release and incomplete validation. This read-only presentation is isolated from authenticated operations. [Production demo setup and rollback](docs/PUBLIC_READONLY_DEMO.md).

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

## Sample data for local evaluation

Run `php artisan db:seed --class=TraceabilityDemoSeeder` after migrations to create fictional plants, users and boxes without publishing credentials. See [local demo instructions](docs/local-demo.md) for the records and safeguards.

## Postman HTTP demo

Import [the Postman collection](docs/postman/industrial-traceability.postman_collection.json) and follow the [local Postman setup guide](docs/postman/README.md). It runs a complete flow against your local server with scoped operator and supervisor tokens. No credentials are committed to the repository.

## End-to-end example

See [the full integration workflow](docs/workflow-test.md) for an executable scenario covering registration, validation, rejection, cross-plant access, supervisor release and audit history.

## Tests

```bash
php artisan test
```

The feature suite covers authentication, token abilities, plant isolation and role permissions, blocked releases, rejected scans, a successful one-time release, and invalidation by a later incorrect scan.

## Verification status

The Laravel suite and GitHub Actions have been run successfully. The 11 Postman HTTP requests were also exercised individually against a local server with the expected status codes. A consolidated, all-green Postman Runner report has not been independently confirmed.

## Current scope and limitations

The repository demonstrates an end-to-end traceability workflow, including plant-scoped authorization, validation rules, event history, CSV reporting and a versioned API. Operational deployment requirements depend on the customer's environment and integrations.

- Plant-level access is enforced. There is no tenant/company hierarchy, centralized role provisioning interface, or independent per-box ACL.
- No production-grade identity provisioning or token lifecycle workflow; browser accounts are provisioned locally for the demo.
- No external scanner, ERP, or MES integration.
- Concurrent release behavior is transaction-based but has not been load-tested or verified on multiple database engines.
- Continuous integration runs Laravel tests plus structural and route-parity OpenAPI checks. It does not yet perform exhaustive runtime response-schema verification across all error branches.

## License and provenance

Developed as an independent portfolio demonstration using fictional identifiers and generic validation rules.

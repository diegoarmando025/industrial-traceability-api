# PlantCore Traceability — Technical Case Study

## Problem

In a production workflow, registering a box is not the same as authorizing its release. Release decisions depend on scanned identifiers, permissions and a record of who performed each action.

This independent demonstration models a small part of that workflow with fictional data and generic validation rules. It is not derived from a customer's proprietary system.

## Implementation

**Stack:** Laravel 13, PHP 8.4, Laravel Sanctum, Eloquent, SQLite for local development and tests, PHPUnit and GitHub Actions.

The application provides a Blade/AdminLTE 4 web interface for session-based dashboard, box registration, scans, release and responsible-user history. Independently, the API exposes five versioned endpoints under `/api/v1`:

- Register a box and associate it with a plant.
- Retrieve the box's status.
- Record part-number, serial-number and package-label scans.
- Attempt release only after all required scan types have a valid *latest* result.
- Retrieve the sequence of scans and audit events.

Incorrect scans are stored, rather than discarded, so a corrected reading does not erase earlier failures. A successful release updates the box status and appends a release event. A repeated release returns a conflict.

## Authorization model

Authentication uses Sanctum Bearer tokens with action-specific abilities. Plant memberships add a second layer:

| Plant role | Read | Create | Scan | Release |
| --- | --- | --- | --- | --- |
| Viewer | Yes | No | No | No |
| Operator | Yes | Yes | Yes | No |
| Supervisor | Yes | Yes | Yes | Yes |

Requests to boxes outside a user's assigned plants respond with 404. A token must also include the ability for the requested operation.

## What was verified

- The automated PHPUnit suite covers validation, release rules, token abilities, role restrictions, plant isolation and audit behavior.
- GitHub Actions runs the PHP/SQLite test suite on commits and pull requests to `main`.
- The 11-step Postman collection was exercised manually against a local Laravel server: a new box was created, an incomplete release was blocked, an incorrect scan was recorded, the corrected identifiers were accepted, an operator was denied release, a supervisor released the box, the final status and history were retrieved, and a duplicate release was rejected.

The Postman steps were observed individually. This does **not** imply a clean, fully passing Postman Runner report unless that separate result is recorded.

## Inspect and reproduce

| Resource | Purpose |
| --- | --- |
| [Web walkthrough](web-reviewer-guide.md) | Evaluate the browser workflow from login to release |
| [API contract](openapi.json) | OpenAPI 3.0.3 route, schema and error documentation |
| [Postman collection](postman/industrial-traceability.postman_collection.json) | Sequential real HTTP example |
| [Postman setup](postman/README.md) | Local credentials and request setup |
| [Seeded sample data](local-demo.md) | Fictional plant, users and boxes |
| [Automated end-to-end scenario](workflow-test.md) | Laravel HTTP test of the full business flow |
| [GitHub Actions](../.github/workflows/tests.yml) | Automated tests |

Run the regression suite locally:

```bash
php artisan test
```

## Boundaries and tradeoffs

This repository is a focused engineering sample, **not production-ready MES software**.

- No production identity provisioning, token lifecycle dashboard or password reset workflow.
- Plant-level access exists, but there is no parent-company/tenant hierarchy or full user administration.
- Box codes remain globally unique rather than unique per plant; this may leak existence through validation conflicts.
- Concurrency behavior has not been load-tested across multiple database engines.
- No actual barcode scanner hardware, ERP/MES integration or public hosted demo.
- The OpenAPI document has not yet been contract-tested automatically against live responses.

The next useful engineering step is to validate the OpenAPI contract automatically and, before any public deployment, complete a security review. More endpoints alone would not address these limits.

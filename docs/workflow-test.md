# End-to-end validation workflow

The automated scenario lives in `tests/Feature/TraceabilityWorkflowTest.php`.

Run from the project root:

```bash
php artisan test --filter=TraceabilityWorkflowTest
```

The test uses Laravel's HTTP testing interface and an in-memory SQLite database. It is **not** an external-network or load test, and it does not require a live API server.

## Scenario

1. Create two fictional plants, QRO and MTY.
2. Assign an operator and a supervisor to QRO and another supervisor to MTY.
3. Create a box as the QRO operator using `POST /api/v1/boxes`.
4. Attempt to release without scans as the QRO supervisor; expect HTTP 409.
5. Submit an incorrect part number as the operator; expect HTTP 422 and an audit record.
6. Submit the correct part number, serial number, and package label; expect HTTP 201 for each.
7. Attempt release as the operator; expect HTTP 403.
8. Attempt to read and release from MTY; expect HTTP 404.
9. Release as the QRO supervisor; expect HTTP 200 and `status=released`.
10. Inspect the history endpoint and database to confirm four scans, creation/block/release events and actor IDs.
11. Attempt release again; expect HTTP 409 without another release event.

## Important distinctions

- HTTP status `422` is intentionally used for a rejected scan and for input validation errors.
- `409` is returned when release preconditions are not met, or a previously released box is submitted again.
- Plant-level role checks and token abilities are both required.
- The test demonstrates business-rule integration through Laravel's request lifecycle. It does not prove scanner hardware compatibility, deployment readiness or concurrency safety.
- All identifiers in this scenario are fictitious and independent of any customer system.

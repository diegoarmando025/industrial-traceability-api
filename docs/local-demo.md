# Local demo data

Use the dedicated seeder to populate a **local or test database only**:

```bash
php artisan migrate
php artisan db:seed --class=TraceabilityDemoSeeder
```

The seeder adds one fictional plant (`DEMO-QRO`), two fictional users assigned as operator and supervisor, and two sample boxes:

| Box | Status | Example |
| --- | --- | --- |
| `DEMO-QRO-001` | released | Three accepted scans and an attributed release event |
| `DEMO-QRO-002` | pending | One rejected part-number scan; release remains blocked |

The seeder is safe to repeat without duplicating these records and deliberately does not reset or wipe a database. The user accounts have randomized passwords that are **not displayed or shared**. There is no public login endpoint. To exercise protected HTTP endpoints manually, use trusted local Tinker commands to create your own user/token and assign appropriate plant membership and token abilities, as described in the README.

To inspect the records using Tinker:

```bash
php artisan tinker
```

```php
$plant = \App\Models\Plant::where('code', 'DEMO-QRO')->firstOrFail();
$plant->boxes()->with(['scans', 'events'])->get();
```

**Safety:** This demo must not be run against a production database. Application environment checks reject execution outside `local` and `testing`. All identifiers are invented, and the seeder does not copy any external source system or production data. It is not an integration with a live scanner or ERP.

Run the automated regression check:

```bash
php artisan test --filter=TraceabilityDemoSeederTest
```

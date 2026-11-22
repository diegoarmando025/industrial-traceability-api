# Traceability 2.0 — Plant validation rules (first phase)

A factory may use a different fictional package-label prefix or require a subset
of the three standard scan types. Validation is still scoped by **plant**:
there is no multi-customer company hierarchy in this version.

## Profile shape

The optional `plants.validation_rules` JSON supports:

```json
{
  "required_scans": ["part_number", "serial_number", "package_label"],
  "package_label_prefix": "BOX"
}
```

- `required_scans`: nonempty, unique list drawn from `part_number`, `serial_number`, `package_label`.
- `package_label_prefix`: 1–16 uppercase letters, numbers, underscores or dashes.
- For example, `PACK:BX-001:PN-100:SN-200` with prefix `PACK`.
- Missing plant rules use the existing three scans and `BOX` prefix.
- Invalid rules fail closed when a box is created; the application must not silently accept malformed configuration.

## Immutable box snapshot

When a box is created, its plant's normalized rules are stored in
`boxes.validation_rules`. Subsequent scans and release checks use the **box snapshot**,
not the current plant configuration. Changes to a plant therefore apply to
newly created boxes only.

Existing boxes without a snapshot retain the original fixed three-scan behavior.
This avoids retroactively allowing release after a profile change.

## Administrative configuration (no public endpoint)

The first phase does not expose a route for changing profiles. A trusted
administrator may provision a fictional plant locally using Tinker:

```php
$plant = \App\Models\Plant::where('code', 'DEMO-QRO')->firstOrFail();
$rules = \App\Support\BoxValidationRules::normalize([
    'required_scans' => ['part_number', 'package_label'],
    'package_label_prefix' => 'PACK',
]);
$plant->update(['validation_rules' => $rules]);
```

Use this procedure only with an authorized plant and after verifying the target
database. Do not apply changes to an active production plant without reviewing
open boxes, event history and rollback plans.

## Deployment

One additive migration, no Vite changes:

```bash
php artisan migrate --force
```

For production, take and verify a MariaDB backup first; use the hosting's PHP 8.4
CLI executable instead of the host default PHP. Roll back application code and
database schema as a coordinated recovery; this migration's `down` drops
snapshot/configuration data and must not be run casually.

## Limitations

This phase does **not** provide a customer-specific rule designer, a self-service
API, custom parsers or a version history of configuration edits. Prefix matching
and choosing required scan types are deliberately constrained to avoid arbitrary
code execution or user-supplied expressions.

The `part_number` and `serial_number` formats remain exact-match. The public
demo is still read-only, with its own fictional data.

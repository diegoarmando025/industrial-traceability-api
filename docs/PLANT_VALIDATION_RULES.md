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

## Supervisor configuration UI

After authentication, a **supervisor** can open `/plants/validation` from the
sidebar, select a plant where they hold the supervisor role, and edit its required
scan types and label prefix. Operators and viewers cannot access these views or
modify profiles. Other-plant profile URLs return 404.

Every effective change is recorded in `plant_validation_rule_changes`, including:
plant, actor, version, previous rules, new rules and timestamp. The UI shows
the recent change history. A version check under a database row lock prevents
stale forms from silently overwriting another supervisor's changes.

Identical updates do not generate history entries. Saving new rules does not
change snapshots already stored on boxes. The public showcase is unaffected and
does not allow profile editing.

## Deployment

Two additive migrations across v2 phases; this UI phase adds one migration for versioning and audit history. No Vite changes (Blade views only):

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

# Box History and CSV — Traceability

This feature adds per-box history for authenticated users authorized for that
box's plant. The `/boxes/{box}/history` screen offers separate scans/events
views, date filters, scan type and acceptance filters, and 20 rows per page.
Users can export the same filtered rows as UTF-8 CSV.

## Security
- Both `GET /boxes/{box}/history` and `GET /boxes/{box}/history/export`
  require session authentication and plant-scoped `read` authorization.
- Cross-plant access returns 404. No public-demo links are added.
- CSV escapes formulas by prefixing suspicious cells with an apostrophe,
  including potentially malicious scanned values.
- CSV exports of more than 10,000 rows return 422; narrow the date range.
- Exports contain user names and scanned values, so they must be treated as
  operational information and shared only with authorized users.

## Deploy
No migrations or new Composer dependencies. All changes are PHP, Blade,
routes, tests and documentation. No new Vite build is required.

For the manual Hostinger workflow, upload the prepared ZIP into the *private*
Laravel application folder, preserving its relative paths and existing `.env`,
`vendor`, `public/build`, and `storage`. Back up replaced files first,
then clear Laravel cache using the hosted PHP 8.4 CLI binary and verify the
authenticated history route. Do not deploy by Git pull immediately after
extracting an incremental ZIP, because it intentionally leaves local tracked
file modifications.

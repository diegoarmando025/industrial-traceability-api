# Traceability — public read-only showcase

This optional visitor-facing view uses fictional records from `TRACE-PUBLIC` only. It does **not** grant access to authenticated scanning/releasing endpoints.

## Safety model

- Disabled by default: `TRACEABILITY_PUBLIC_DEMO_ENABLED=false`.
- Public GET only: `/demo`, `/demo/boxes/{box}`.
- Always filters the reserved demo plant server-side; other-plant box IDs return HTTP 404.
- No public login credentials, API tokens, or plant memberships are created.
- Responds with `X-Robots-Tag: noindex, nofollow` and `Cache-Control: no-store, private`.
- The interactive production endpoints remain protected by existing session and authorization logic.
- `throttle:60,1` limits traffic, but **does not replace** security controls.

## Upgrade on Hostinger

Create a verified backup of the **Traceability** MariaDB database before running the new seed. Do not touch the LPA database or change APP_ENV.

```bash
cd /home/u641948971/domains/devalejandre.com/apps/traceability
git status --short
git pull --ff-only origin main
/opt/alt/php84/usr/bin/php artisan optimize:clear
```

If Git reports local edits or conflicts, stop and resolve them rather than resetting the checkout.

Inspect your private `.env` and confirm Traceability's dedicated DB is selected. Then initialize isolated fictional records:

```bash
/opt/alt/php84/usr/bin/php artisan traceability:public-demo-seed --force
```

Creates three fictional boxes: `TRACE-PUBLIC-001` fully validated and released, `TRACE-PUBLIC-002` with a rejected part number and blocked release, and `TRACE-PUBLIC-003` partially validated. Re-running does not duplicate events.

Now edit `apps/traceability/.env`:

```dotenv
TRACEABILITY_PUBLIC_DEMO_ENABLED=true
```

Refresh the configuration:

```bash
/opt/alt/php84/usr/bin/php artisan optimize:clear
```

Open `https://traceability.devalejandre.com/demo` in an incognito window and verify:
- All three fictitious boxes are shown and the status filter works.
- The released box shows three accepted scans and a release event.
- The blocked box shows a rejected scan and a blocked-release event.
- The incomplete box is still pending.
- A random private box identifier at `/demo/boxes/{id}` is not displayed (HTTP 404).
- Without login, `/dashboard` redirects to `/login`.
- `https://devalejandre.com/traceability/` remains blocked by the subdomain Host rule.
- DevAlejandre and LPA continue working normally.

No new database migrations, npm dependencies or Vite build steps are required for this update. Public views use independent inline CSS and the existing Laravel runtime.

## Emergency disable

Set `TRACEABILITY_PUBLIC_DEMO_ENABLED=false` in the private `.env`, then run `php artisan optimize:clear` with the PHP 8.4 binary. The public demo returns HTTP 404. Internal workflows remain intact.

Do not replace this read-only presentation with a shared supervisor login. Never seed client data or override the `TraceabilityDemoSeeder` production restriction.

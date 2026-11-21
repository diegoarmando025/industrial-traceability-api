# Run the API workflow in Postman

The collection at [`docs/postman/industrial-traceability.postman_collection.json`](postman/industrial-traceability.postman_collection.json) sends **11 sequential HTTP requests** to the local Laravel application.

It covers creation, blocked release, rejected and corrected scans, operator denial, supervisor release, box status, audit history and duplicate-release prevention.

## 1. Prepare a local database

From the project root:

```powershell
php artisan migrate
php artisan db:seed --class=TraceabilityDemoSeeder
```

Run the application in a separate terminal:

```powershell
php artisan serve
```

By default it serves on `http://127.0.0.1:8000`.

**Do not run this demonstration against a shared or production database.** The collection writes new box and audit records. It does not clean them up.

## 2. Create temporary personal access tokens

The demo seeder created fictitious users with plant memberships, but intentionally did **not** expose passwords or API tokens.

Run:

```powershell
php artisan tinker
```

In Tinker, enter the following to get the plant ID and issue scoped local tokens:

```php
$plant = \App\Models\Plant::where('code', 'DEMO-QRO')->firstOrFail();
$plant->id;

$operator = \App\Models\User::where('email', 'operator@traceability.example.invalid')->firstOrFail();
$operatorToken = $operator->createToken('postman-operator', ['boxes:create', 'boxes:read', 'boxes:scan'])->plainTextToken;
echo $operatorToken;

$supervisor = \App\Models\User::where('email', 'supervisor@traceability.example.invalid')->firstOrFail();
$supervisorToken = $supervisor->createToken('postman-supervisor', ['boxes:read', 'boxes:release'])->plainTextToken;
echo $supervisorToken;
```

The printed values are credentials. **Do not paste them into source files, GitHub, screenshots, logs or conversations.** Use the local Postman environment described below. If exposed, revoke the tokens in the `personal_access_tokens` table.

## 3. Import and configure Postman

1. Import the collection JSON into Postman.
2. Create a new **local** environment (for example, `Traceability Local`). Do not share or export it with populated credentials.
3. Create environment variables `operator_token` and `supervisor_token`, and paste the corresponding tokens from Tinker into their **local values**. Mark these variables as **secret** where supported.
4. Set the collection variable `plant_id` to the numeric plant ID returned by Tinker. Keep `base_url` as `http://127.0.0.1:8000` unless Laravel serves on a different port.
5. Select your local environment.
6. Use **Run collection** and execute all requests **in order**, one iteration. Do not run requests in parallel. The first request creates a fresh box code and stores its ID for later calls.

The collection intentionally contains **no live tokens**. Its Bearer authentication uses `{{operator_token}}` or `{{supervisor_token}}`, read from your local environment.

## 4. Expected results

| Request | Expected HTTP |
| --- | --- |
| Create box | 201 |
| Attempt release with missing scans | 409 |
| Submit an incorrect part number | 422 |
| Correct part number | 201 |
| Correct serial number | 201 |
| Correct package label | 201 |
| Operator attempts release | 403 |
| Supervisor releases box | 200 |
| Check box status | 200 |
| Check audit history | 200 |
| Second release attempt | 409 |

Postman test scripts verify the returned status and important JSON properties. Each run creates a uniquely named demo box. If you want a clean local database, use a disposable SQLite database copy rather than wiping any database with useful records.

## 5. Revoke tokens afterward

Return to Tinker and revoke the specifically named test tokens:

```php
$operator->tokens()->where('name', 'postman-operator')->delete();
$supervisor->tokens()->where('name', 'postman-supervisor')->delete();
```

The collection is a real HTTP demonstration against a **locally running server**, unlike the in-process Laravel feature tests. It does not test a remote deployment, physical scanners, concurrency or external integrations.

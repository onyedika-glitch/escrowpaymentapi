# Milestone Escrow Payment API

Backend-only REST API for a freelance marketplace: clients create projects, define payment
milestones, fund a simulated escrow wallet, lock funds into milestones and release them to
freelancers. Every status change fires a webhook carrying a KYC snapshot of both parties.

**Stack:** PHP 8.4 · Laravel 13 · PostgreSQL 16

## Quick start

```bash
docker compose up -d                # Postgres on localhost:5433 (escrow/escrow)
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve --no-reload       # http://127.0.0.1:8000  (--no-reload enables 4 workers, see below)
```

Run the full lifecycle demo (needs `curl` + `jq`) and the test suite:

```bash
./scripts/demo.sh                   # prints every request/response
docker exec escrow-pg psql -U escrow -c 'CREATE DATABASE escrow_test'   # once
php artisan test
```

## Design overview

| Concern | Approach |
|---|---|
| **Money** | Stored as integer cents (`bigint`) — no float rounding. API accepts/returns decimals (max 2 dp). |
| **Wallets** | One per user, created at registration. Two buckets: `available` and `escrow` (locked in funded milestones). |
| **Ledger** | Append-only `ledger_entries`; each row stores the balances *after* the movement, so history is auditable. |
| **State machine** | Milestone `Created → Funded → Released`; any other transition returns `409`. |
| **Concurrency** | Fund/release run in a DB transaction with `SELECT … FOR UPDATE` on milestone + wallet(s), so double-funding, double-release and overdrafts are impossible under concurrent requests. Wallets are locked in id order to avoid deadlocks. |
| **Auth (mock)** | Write endpoints require `X-User-Id: <id>` (or `?user_id=`). Ownership is enforced: only the project's client can add/fund/release milestones; you can only fund your own wallet. |
| **KYC** | Stored in `kyc_profiles`; government ID is **encrypted at rest** and **masked** (`*****5678`) in every API response and webhook. Verification is simulated (auto-`Verified`). |
| **Webhooks** | Fired *after* the transaction commits; best-effort (a failed delivery never rolls back money). Every attempt is stored in `webhook_deliveries` and logged to `storage/logs/webhooks.log`. |
| **Errors** | Uniform `{"error": {"code", "message", "details?/fields?"}}` envelope. |

Money flow:

```
POST /wallets/:id/fund          client.available += amt
POST .../milestones/:id/fund    client.available -= amt; client.escrow += amt      Created  → Funded
POST .../milestones/:id/release client.escrow    -= amt; freelancer.available += amt Funded → Released
```

### Configuring the webhook URL

Set `ESCROW_WEBHOOK_URL` in `.env` (read by `config/escrow.php`). The default points to the
built-in mock receiver `POST /mock/webhook`, which logs what it gets. To see payloads externally,
use e.g. `ESCROW_WEBHOOK_URL=https://webhook.site/<your-id>`.

> `php artisan serve` is single-threaded by default, so the app can't call its own mock endpoint.
> `.env` sets `PHP_CLI_SERVER_WORKERS=4`, which Laravel only honours with `--no-reload`.

## Project layout

```
app/Services/EscrowService.php     fund wallet / fund milestone / release — all money movement
app/Services/WebhookNotifier.php   builds + sends + records webhook payloads
app/Http/Controllers/              thin controllers: validation + authorization + resources
app/Http/Middleware/ResolveActingUser.php   mock X-User-Id auth
app/Http/Resources/                response shapes (masks KYC, formats money)
app/Exceptions/EscrowException.php domain errors (insufficient_funds, invalid_status_transition, forbidden)
app/Enums/                         Role, MilestoneStatus, LedgerType
database/migrations/               schema (ORM definitions)
database/schema.sql                plain-SQL schema dump
tests/Feature/MilestoneLifecycleTest.php
scripts/demo.sh                    curl walkthrough of the full lifecycle + error cases
docs/API.md                        endpoint reference
docs/demo-output.md                captured output of scripts/demo.sh
docs/webhook-log.txt               captured webhook log (sent + received)
```

## Stretch / not done (what I'd add for production)

- Real auth (Sanctum/JWT) and policies instead of the `X-User-Id` header.
- Queue webhook delivery as a job with exponential-backoff retries, and sign payloads
  (`X-Signature: HMAC-SHA256`) so receivers can verify them.
- `Idempotency-Key` header on fund/release endpoints for safe client retries.
- Refund / cancel flow (`Funded → Refunded`) and dispute state.
- Platform fee split on release; multi-currency wallets.
- Pagination + filters on `/wallets/:id/history`; `GET /projects` listing.
- Real KYC provider integration with `Pending/Rejected` statuses blocking payouts.

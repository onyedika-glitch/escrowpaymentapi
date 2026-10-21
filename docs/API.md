# API Reference

Base URL: `http://127.0.0.1:8000` · JSON in/out · send `Accept: application/json`.

**Auth (mock):** endpoints marked 🔒 require `X-User-Id: <userId>` (or `?user_id=`).
Amounts are decimals in USD with at most 2 decimal places.

## Errors

```json
{ "error": { "code": "insufficient_funds", "message": "...", "details": { "available": 0, "required": 500 } } }
```

| HTTP | code | When |
|---|---|---|
| 401 | `unauthenticated` | 🔒 endpoint without a valid `X-User-Id` |
| 403 | `forbidden` | acting user isn't the project owner / wallet owner / a client |
| 404 | `not_found` | user, project or milestone missing (milestone must belong to the project in the URL) |
| 409 | `invalid_status_transition` | e.g. release a `Created` milestone, fund twice, release twice |
| 422 | `insufficient_funds` | wallet available balance < milestone amount |
| 422 | `validation_failed` | bad input — field errors in `error.fields` |

---

## Users

### `POST /register`
```json
{
  "name": "Ada Client", "email": "ada@example.com", "role": "client",
  "kyc": {
    "legal_full_name": "Ada Lovelace", "country": "GB",
    "government_id_type": "passport", "government_id_number": "P12345678",
    "date_of_birth": "1990-12-10"
  }
}
```
`role`: `client | freelancer` · `government_id_type`: `passport | national_id | drivers_license` · `country`: ISO-2 · `date_of_birth` optional (must be 18+).

**201**
```json
{ "data": {
  "id": 1, "name": "Ada Client", "email": "ada@example.com", "role": "client",
  "kyc": { "legal_full_name": "Ada Lovelace", "country": "GB", "government_id_type": "passport",
           "government_id_number": "*****5678", "date_of_birth": "1990-12-10",
           "status": "Verified", "verified_at": "2026-10-21T11:47:38.000000Z" },
  "wallet": { "currency": "USD", "available_balance": 0, "escrow_balance": 0 },
  "created_at": "2026-10-21T11:47:38.000000Z" } }
```

### `GET /users/:userId`
Same shape as above, with current wallet balances.

---

## Projects & milestones

### 🔒 `POST /projects` (client only)
```json
{ "title": "Marketplace MVP", "description": "Build the escrow API", "freelancer_ids": [2] }
```
**201** → project (see `GET /projects/:id`). 422 if any id isn't a registered freelancer.

### 🔒 `POST /projects/:id/milestones` (project owner)
Single milestone, or bulk via `milestones` array:
```json
{ "milestones": [
  { "title": "API design", "description": "Routes + schema", "amount": 500.00, "due_date": "2030-01-15" },
  { "title": "Implementation", "amount": 1500.50, "due_date": "2030-02-15", "freelancer_id": 2 }
] }
```
`freelancer_id` is optional when exactly one freelancer is assigned; otherwise required and must be assigned to the project. `due_date` must be today or later.

**201**
```json
{ "data": [ { "id": 1, "project_id": 1, "title": "API design", "description": "Routes + schema",
  "amount": 500, "currency": "USD", "due_date": "2030-01-15", "status": "Created",
  "funded_at": null, "released_at": null, "freelancer_id": 2, "created_at": "..." }, ... ] }
```

### `GET /projects/:id`
```json
{ "data": {
  "id": 1, "title": "Marketplace MVP", "description": "...", "status": "Open",
  "client": { "id": 1, "name": "Ada Client", "email": "...", "role": "client" },
  "freelancers": [ { "id": 2, "name": "Linus Dev", "email": "...", "role": "freelancer" } ],
  "milestones": [ { "id": 1, "status": "Released", "amount": 500, ... } ],
  "totals": { "milestone_count": 2, "total_amount": 2000.5, "funded_in_escrow": 0, "released": 500 },
  "created_at": "..." } }
```

### `GET /projects/:projectId/milestones/:milestoneId`
Milestone plus `involved_users: { client, freelancer }`.

---

## Escrow & payments

### 🔒 `POST /wallets/:userId/fund` (own wallet, client only)
```json
{ "amount": 1000, "reference": "demo-topup-1" }
```
**201**
```json
{ "data": {
  "transaction": { "id": 1, "type": "wallet_funded", "direction": "credit", "amount": 1000,
                   "available_balance_after": 1000, "escrow_balance_after": 0, "reference": "demo-topup-1", ... },
  "user": { "id": 1, ..., "wallet": { "currency": "USD", "available_balance": 1000, "escrow_balance": 0 } } } }
```

### 🔒 `POST /projects/:projectId/milestones/:milestoneId/fund` (project owner)
`Created → Funded`. Moves amount from client's available balance into escrow. Fires `milestone.funded`.
**200** → milestone with `"status": "Funded"`. Errors: 422 `insufficient_funds`, 409 if not `Created`.

### 🔒 `POST /projects/:projectId/milestones/:milestoneId/release` (project owner)
`Funded → Released`. Moves amount from client's escrow to the freelancer's available balance. Fires `milestone.released`.
**200** → milestone with `"status": "Released"`. Errors: 409 if not `Funded`.

### `GET /wallets/:userId/history`
```json
{ "data": { "user_id": 1, "currency": "USD", "available_balance": 500, "escrow_balance": 0,
  "entries": [
    { "id": 1, "type": "wallet_funded",      "direction": "credit", "amount": 1000, "available_balance_after": 1000, "escrow_balance_after": 0,   "timestamp": "..." },
    { "id": 2, "type": "milestone_funded",   "direction": "hold",   "amount": 500,  "available_balance_after": 500,  "escrow_balance_after": 500, "milestone_id": 1, ... },
    { "id": 3, "type": "milestone_released", "direction": "debit",  "amount": 500,  "available_balance_after": 500,  "escrow_balance_after": 0,   "milestone_id": 1, ... }
  ] } }
```
Freelancers see `payment_received` (credit) entries.

---

## Webhooks

Sent as `POST $ESCROW_WEBHOOK_URL` with header `X-Escrow-Event: milestone.funded|milestone.released`:

```json
{
  "id": "b4f6be27-a1a9-45df-b804-3bc38418cc08",
  "event": "milestone.released",
  "occurred_at": "2026-10-21T11:47:42+00:00",
  "data": {
    "project_id": 1, "milestone_id": 1, "status": "Released", "amount": 500, "currency": "USD",
    "client":     { "user_id": 1, "name": "Ada Client", "email": "...", "role": "client",
                    "kyc": { "legal_full_name": "Ada Lovelace", "country": "GB", "government_id_type": "passport",
                             "government_id_number": "*****5678", "status": "Verified" } },
    "freelancer": { "user_id": 2, "name": "Linus Dev", "email": "...", "role": "freelancer",
                    "kyc": { "legal_full_name": "Linus Torvalds", "country": "FI", "government_id_type": "national_id",
                             "government_id_number": "*****8877", "status": "Verified" } }
  }
}
```

- `POST /mock/webhook` — built-in receiver (default target); logs to `storage/logs/webhooks.log`.
- `GET /webhooks/deliveries` — last 50 outbound attempts with payload, HTTP status, and error.

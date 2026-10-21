#!/usr/bin/env bash
# Runs one complete milestone lifecycle plus the main error cases against a running server.
# Usage: ./scripts/demo.sh [base_url]     (requires curl + jq)
set -euo pipefail
BASE=${1:-http://127.0.0.1:8000}
SUFFIX=$(date +%s)

call() { # call METHOD PATH [USER_ID] [JSON]
  local method=$1 path=$2 uid=${3:-} body=${4:-}
  echo "### $method $path${uid:+   (X-User-Id: $uid)}" >&2
  [ -n "$body" ] && echo "$body" | jq . >&2
  local args=(-s -X "$method" "$BASE$path" -H 'Accept: application/json' -H 'Content-Type: application/json')
  [ -n "$uid" ] && args+=(-H "X-User-Id: $uid")
  [ -n "$body" ] && args+=(-d "$body")
  local out; out=$(curl "${args[@]}" -w '\n%{http_code}')
  echo "--> HTTP $(tail -n1 <<<"$out")" >&2
  local json; json=$(head -n -1 <<<"$out")
  jq . <<<"$json" >&2; echo >&2
  echo "$json"
}

echo "===== 1. Register client + freelancer =====" >&2
CLIENT=$(call POST /register "" "{\"name\":\"Ada Client\",\"email\":\"ada+$SUFFIX@example.com\",\"role\":\"client\",
  \"kyc\":{\"legal_full_name\":\"Ada Lovelace\",\"country\":\"GB\",\"government_id_type\":\"passport\",\"government_id_number\":\"P12345678\",\"date_of_birth\":\"1990-12-10\"}}" | jq .data.id)
FREEL=$(call POST /register "" "{\"name\":\"Linus Dev\",\"email\":\"linus+$SUFFIX@example.com\",\"role\":\"freelancer\",
  \"kyc\":{\"legal_full_name\":\"Linus Torvalds\",\"country\":\"FI\",\"government_id_type\":\"national_id\",\"government_id_number\":\"FI-998877\"}}" | jq .data.id)

echo "===== 2. Client creates project =====" >&2
PROJECT=$(call POST /projects "$CLIENT" "{\"title\":\"Marketplace MVP\",\"description\":\"Build the escrow API\",\"freelancer_ids\":[$FREEL]}" | jq .data.id)

echo "===== 3. Client adds milestones (bulk) =====" >&2
MS=$(call POST /projects/$PROJECT/milestones "$CLIENT" '{"milestones":[
  {"title":"API design","description":"Routes + schema","amount":500.00,"due_date":"2030-01-15"},
  {"title":"Implementation","amount":1500.50,"due_date":"2030-02-15"}]}')
M1=$(jq '.data[0].id' <<<"$MS"); M2=$(jq '.data[1].id' <<<"$MS")

echo "===== 4. Fund milestone BEFORE wallet funded (expect 422 insufficient_funds) =====" >&2
call POST /projects/$PROJECT/milestones/$M1/fund "$CLIENT" > /dev/null

echo "===== 5. Client funds wallet =====" >&2
call POST /wallets/$CLIENT/fund "$CLIENT" '{"amount":1000,"reference":"demo-topup-1"}' > /dev/null

echo "===== 6. Release BEFORE funding milestone (expect 409) =====" >&2
call POST /projects/$PROJECT/milestones/$M1/release "$CLIENT" > /dev/null

echo "===== 7. Fund milestone 1 (Created -> Funded, webhook fires) =====" >&2
call POST /projects/$PROJECT/milestones/$M1/fund "$CLIENT" > /dev/null

echo "===== 8. Freelancer tries to release (expect 403) =====" >&2
call POST /projects/$PROJECT/milestones/$M1/release "$FREEL" > /dev/null

echo "===== 9. Client releases milestone 1 (Funded -> Released, webhook fires) =====" >&2
call POST /projects/$PROJECT/milestones/$M1/release "$CLIENT" > /dev/null

echo "===== 10. Double release (expect 409) =====" >&2
call POST /projects/$PROJECT/milestones/$M1/release "$CLIENT" > /dev/null

echo "===== 11. Milestone 2 exceeds remaining balance (expect 422) =====" >&2
call POST /projects/$PROJECT/milestones/$M2/fund "$CLIENT" > /dev/null

echo "===== 12. Read endpoints =====" >&2
call GET /projects/$PROJECT > /dev/null
call GET /projects/$PROJECT/milestones/$M1 > /dev/null
call GET /users/$CLIENT > /dev/null
call GET /users/$FREEL > /dev/null
call GET /wallets/$CLIENT/history > /dev/null
call GET /wallets/$FREEL/history > /dev/null
call GET /users/999999 > /dev/null

echo "===== 13. Webhook deliveries =====" >&2
call GET /webhooks/deliveries > /dev/null

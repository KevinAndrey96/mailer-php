#!/bin/sh
# End-to-end smoke test against the running compose stack: ./scripts/smoke-test.sh [api-port] [mailpit-port]
set -eu
API="http://localhost:${1:-8080}"
MAILPIT="http://localhost:${2:-8025}"
TOKEN=$(grep '^API_TOKEN=' .env | cut -d= -f2-)
OUT=$(mktemp)
fail() { echo "FAIL: $*"; cat "$OUT"; exit 1; }

curl -s -X DELETE "$MAILPIT/api/v1/messages" > /dev/null

code=$(curl -s -o "$OUT" -w '%{http_code}' "$API/health")
[ "$code" = 200 ] || fail "health $code"

code=$(curl -s -o "$OUT" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d '{}' "$API/messages")
[ "$code" = 401 ] || fail "missing token $code"

code=$(curl -s -o "$OUT" -w '%{http_code}' -X POST -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"to":"victim@gmail.com","subject":"Spam","text":"x"}' "$API/messages")
[ "$code" = 403 ] || fail "recipient allowlist $code"

code=$(curl -s -o "$OUT" -w '%{http_code}' -X POST -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"to":["cliente@example.com"],"subject":"Confirmación de registro","text":"Hola, tu registro está completo.","html":"<p>Hola, tu registro <b>está completo</b>.</p>"}' \
  "$API/messages")
[ "$code" = 202 ] || fail "send $code"
grep -q '"message_id"' "$OUT" || fail "no message id"

curl -s "$MAILPIT/api/v1/messages" > "$OUT"
grep -q '"total":1' "$OUT" || fail "mailpit did not receive the message"
grep -q 'Confirmación de registro' "$OUT" || fail "subject is not utf-8"

curl -sI "$API/health" | grep -qi "x-powered-by" && fail "x-powered-by leaked"
rm -f "$OUT"
echo "smoke ok"

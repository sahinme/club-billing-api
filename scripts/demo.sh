#!/bin/sh
# Walks through every scenario of POST /v1/invoices/{id}/payments.
# Runs inside the app container (make demo), so the only requirement on your machine is Docker.
set -eu

API=http://nginx
PASSWORD=demo-password

step() { printf '\n\033[1;36m== %s\033[0m\n' "$1"; }

# call METHOD PATH TOKEN BODY [IDEMPOTENCY_KEY]  -> prints status line + JSON body
call() {
    method=$1 path=$2 token=$3 body=$4 key=${5:-}
    set -- -s -i -X "$method" "$API$path" -H 'Content-Type: application/json'
    [ -n "$token" ] && set -- "$@" -H "Authorization: Bearer $token"
    [ -n "$key" ] && set -- "$@" -H "Idempotency-Key: $key"
    [ -n "$body" ] && set -- "$@" -d "$body"
    curl "$@" | awk 'NR==1 {print "HTTP " $2} /^Idempotent-Replayed/ {print} /^\{/ {print}' | while read -r line; do
        case $line in \{*) echo "$line" | jq . ;; *) echo "$line" ;; esac
    done
}

login() {
    curl -s -X POST "$API/v1/auth/login" -H 'Content-Type: application/json' \
        -d "{\"email\":\"$1\",\"password\":\"$PASSWORD\"}" | jq -r .access_token
}

key() { cat /proc/sys/kernel/random/uuid; }

php bin/seed.php >/dev/null
echo "Demo data reset."

step "1. Login as front desk staff (Amsterdam)"
call POST /v1/auth/login "" "{\"email\":\"frontdesk@amsterdam.test\",\"password\":\"$PASSWORD\"}"
DESK=$(login frontdesk@amsterdam.test)

step "2. Invoice 1 before payment"
call GET /v1/invoices/1 "$DESK" ""

step "3. No token -> 401"
call POST /v1/invoices/1/payments "" '{"amount":2000,"currency":"EUR","payment_method":"pm_card_ok"}' "$(key)"

step "4. Invalid input -> 422 with every problem listed"
call POST /v1/invoices/1/payments "$DESK" '{"amount":20.5,"currency":"eur","payment_method":"4111111111111111"}'

step "5. Pay 20.00 of 49.99 -> 201"
KEY=$(key)
call POST /v1/invoices/1/payments "$DESK" '{"amount":2000,"currency":"EUR","payment_method":"pm_card_ok"}' "$KEY"

step "6. Network retry with the SAME Idempotency-Key -> same payment, not charged twice"
call POST /v1/invoices/1/payments "$DESK" '{"amount":2000,"currency":"EUR","payment_method":"pm_card_ok"}' "$KEY"

step "7. Same key, different amount -> 422"
call POST /v1/invoices/1/payments "$DESK" '{"amount":2500,"currency":"EUR","payment_method":"pm_card_ok"}' "$KEY"

step "8. Pay the remaining 29.99 -> 201, invoice becomes paid"
call POST /v1/invoices/1/payments "$DESK" '{"amount":2999,"currency":"EUR","payment_method":"pm_card_ok"}' "$(key)"
call GET /v1/invoices/1 "$DESK" ""

step "9. Pay a paid invoice -> 422"
call POST /v1/invoices/1/payments "$DESK" '{"amount":100,"currency":"EUR","payment_method":"pm_card_ok"}' "$(key)"

step "10. Card declined -> 402, invoice untouched"
call POST /v1/invoices/2/payments "$DESK" '{"amount":2500,"currency":"EUR","payment_method":"pm_card_declined"}' "$(key)"

step "11. Auditor role (read-only) tries to collect -> 403"
call POST /v1/invoices/2/payments "$(login auditor@amsterdam.test)" '{"amount":100,"currency":"EUR","payment_method":"pm_card_ok"}' "$(key)"

step "12. Staff of ANOTHER club tries an Amsterdam invoice -> 404 (not even its existence leaks)"
call POST /v1/invoices/2/payments "$(login frontdesk@rotterdam.test)" '{"amount":100,"currency":"EUR","payment_method":"pm_card_ok"}' "$(key)"

ANNA=$(login anna@amsterdam.test)
step "13. Member Anna pays Bob's invoice -> 404"
call POST /v1/invoices/2/payments "$ANNA" '{"amount":100,"currency":"EUR","payment_method":"pm_card_ok"}' "$(key)"

TMP=$(mktemp -d)
step "14. Race: 5 parallel requests, 5 different keys, each paying Bob's full invoice"
for i in 1 2 3 4 5; do
    curl -s -o /dev/null -w '%{http_code}\n' -X POST "$API/v1/invoices/2/payments" \
        -H 'Content-Type: application/json' -H "Authorization: Bearer $DESK" -H "Idempotency-Key: $(key)" \
        -d '{"amount":2500,"currency":"EUR","payment_method":"pm_card_ok"}' > "$TMP/$i" &
done
wait
echo "Status codes (count x code): $(cat "$TMP"/[0-9] | sort | uniq -c | tr -s ' ' | tr '\n' ' ')"
echo "Exactly one 201. The others wait on the row lock, then get 409 (payment in progress) or 422 (already paid)."
call GET /v1/invoices/2 "$DESK" ""

step "15. Race: 5 parallel requests with the SAME Idempotency-Key"
SAME=$(key)
for i in 1 2 3 4 5; do
    curl -s -X POST "$API/v1/invoices/4/payments" \
        -H 'Content-Type: application/json' -H "Authorization: Bearer $ANNA" -H "Idempotency-Key: $SAME" \
        -d '{"amount":500,"currency":"EUR","payment_method":"pm_card_ok"}' > "$TMP/same-$i" &
done
wait
echo "Payment ids returned (count x id): $(cat "$TMP"/same-* | jq -r '.id' | sort | uniq -c | tr -s ' ' | tr '\n' ' ')"
echo "All five answers point to one payment. It was charged once."
rm -rf "$TMP"

step "16. Provider timeout -> 202 pending (we never guess whether money moved)"
call POST /v1/invoices/4/payments "$DESK" '{"amount":1000,"currency":"EUR","payment_method":"pm_card_timeout"}' "$(key)"

step "17. Another payment while one is pending -> 409"
call POST /v1/invoices/4/payments "$DESK" '{"amount":1000,"currency":"EUR","payment_method":"pm_card_ok"}' "$(key)"

printf '\n\033[1;32mDone.\033[0m Events published to Kafka: run  make events\n'

#!/usr/bin/env bash
# Bot-attack smoke test for Commercepundit_PaymentTransactionFailed
#
# LOCAL DOCKER (run INSIDE php-fpm container):
#   # 1) Map domain to nginx inside this container (nginx listens on 127.0.0.1:80)
#   grep -q 'dev.bigcitysportswear.com' /etc/hosts || \
#     echo '127.0.0.1 dev.bigcitysportswear.com' >> /etc/hosts
#
#   # 2) Run test
#   export BASE_URL="http://dev.bigcitysportswear.com"
#   export SKU="2146-White-XS"
#   export ATTEMPTS=5
#   bash app/code/Commercepundit/PaymentTransactionFailed/dev/attack_test.sh
#
# OR without editing hosts (Host header):
#   export BASE_URL="http://127.0.0.1"
#   export HOST_HEADER="dev.bigcitysportswear.com"
#   export SKU="2146-White-XS"
#   bash app/code/Commercepundit/PaymentTransactionFailed/dev/attack_test.sh
#
# FROM HOST MACHINE (if nginx port is published):
#   export BASE_URL="http://dev.bigcitysportswear.com"
#   export SKU="2146-White-XS"
#   bash .../attack_test.sh

set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1}"
BASE_URL="${BASE_URL%/}"
STORE="${STORE:-default}"
SKU="${SKU:-YOUR-SIMPLE-SKU}"
SHIP_CARRIER="${SHIP_CARRIER:-flatrate}"
SHIP_METHOD="${SHIP_METHOD:-flatrate}"
EMAIL="${EMAIL:-probe@example.invalid}"
ATTEMPTS="${ATTEMPTS:-5}"
HOST_HEADER="${HOST_HEADER:-}"

API="${BASE_URL}/rest/${STORE}/V1"

CURL_OPTS=(-sS)
if [[ -n "${HOST_HEADER}" ]]; then
  CURL_OPTS+=(-H "Host: ${HOST_HEADER}")
fi

echo "BASE_URL=${BASE_URL}"
echo "HOST_HEADER=${HOST_HEADER:-"(none)"}"
echo "SKU=${SKU}"
echo

echo "==> 0) Connectivity check"
HTTP_CODE=$(curl "${CURL_OPTS[@]}" -o /tmp/ptf_ping.html -w "%{http_code}" --connect-timeout 5 "${BASE_URL}/" || true)
echo "GET ${BASE_URL}/ -> HTTP ${HTTP_CODE}"
if [[ "${HTTP_CODE}" == "000" || -z "${HTTP_CODE}" ]]; then
  echo
  echo "ERROR: Cannot reach Magento from this shell."
  echo "You are likely inside the php container without DNS for the domain."
  echo
  echo "Fix (pick one):"
  echo "  A) echo '127.0.0.1 dev.bigcitysportswear.com' >> /etc/hosts"
  echo "     export BASE_URL='http://dev.bigcitysportswear.com'"
  echo
  echo "  B) export BASE_URL='http://127.0.0.1'"
  echo "     export HOST_HEADER='dev.bigcitysportswear.com'"
  echo
  echo "Also confirm nginx is running in this container: service nginx status || nginx -t"
  exit 1
fi

echo "==> 1) Create guest cart"
CART_ID=$(curl "${CURL_OPTS[@]}" -X POST "${API}/guest-carts" -H "Content-Type: application/json" | tr -d '"')
echo "guest_cart_id=${CART_ID}"
if [[ -z "${CART_ID}" || "${CART_ID}" == *"message"* || "${CART_ID}" == *"error"* ]]; then
  echo "Failed to create guest cart. Response above may be an error JSON."
  exit 1
fi

echo "==> 2) Add item ${SKU}"
curl "${CURL_OPTS[@]}" -X POST "${API}/guest-carts/${CART_ID}/items" \
  -H "Content-Type: application/json" \
  -d "{\"cartItem\":{\"sku\":\"${SKU}\",\"qty\":1,\"quote_id\":\"${CART_ID}\"}}" | head -c 400
echo

echo "==> 3) Set shipping/billing"
curl "${CURL_OPTS[@]}" -X POST "${API}/guest-carts/${CART_ID}/shipping-information" \
  -H "Content-Type: application/json" \
  -d "{
    \"addressInformation\": {
      \"shipping_address\": {
        \"firstname\": \"Probe\", \"lastname\": \"Lab\",
        \"street\": [\"1 Attack St\"], \"city\": \"London\",
        \"postcode\": \"E1 1AA\", \"country_id\": \"GB\",
        \"telephone\": \"000\", \"email\": \"${EMAIL}\"
      },
      \"billing_address\": {
        \"firstname\": \"Probe\", \"lastname\": \"Lab\",
        \"street\": [\"1 Attack St\"], \"city\": \"London\",
        \"postcode\": \"E1 1AA\", \"country_id\": \"GB\",
        \"telephone\": \"000\", \"email\": \"${EMAIL}\"
      },
      \"shipping_carrier_code\": \"${SHIP_CARRIER}\",
      \"shipping_method_code\": \"${SHIP_METHOD}\"
    }
  }" | head -c 400
echo

echo "==> 4) Attack payment-information ${ATTEMPTS}x (fake method forces failure)"
for i in $(seq 1 "${ATTEMPTS}"); do
  echo "--- attempt ${i}/${ATTEMPTS} ---"
  HTTP_CODE=$(curl "${CURL_OPTS[@]}" -o /tmp/ptf_attack_body.json -w "%{http_code}" \
    -X POST "${API}/guest-carts/${CART_ID}/payment-information" \
    -H "Content-Type: application/json" \
    -d "{
      \"email\": \"${EMAIL}\",
      \"paymentMethod\": { \"method\": \"fake_bot_payment\" },
      \"billingAddress\": {
        \"firstname\": \"Probe\", \"lastname\": \"Lab\",
        \"street\": [\"1 Attack St\"], \"city\": \"London\",
        \"postcode\": \"E1 1AA\", \"country_id\": \"GB\",
        \"telephone\": \"000\", \"email\": \"${EMAIL}\"
      }
    }")
  echo "HTTP ${HTTP_CODE}"
  head -c 500 /tmp/ptf_attack_body.json; echo
  sleep 0.3
done

echo
echo "Done. Verify inside container:"
echo "  tail -n 50 var/log/BotPaymentFail.log"
echo "  mysql ... -e \"SELECT entity_id, quote_id, customer_email, remote_ip, reason, created_at FROM payment_transaction_failed ORDER BY entity_id DESC LIMIT 20;\""
echo "After bot limit (default 3, no browse), response should include:"
echo "  Multiple unsuccessful attempts stopped your order from being placed"

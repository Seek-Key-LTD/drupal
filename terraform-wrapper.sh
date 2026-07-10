#!/bin/bash
set -euo pipefail

export VAULT_ADDR=https://vault.git4ta.fun

ROLE_ID="$1"
SECRET_ID="$2"

# Get token from AppRole
TOKEN=$(curl -s -X POST "$VAULT_ADDR/v1/auth/approle/login" \
  -H "Content-Type: application/json" \
  -d "{\"role_id\":\"$ROLE_ID\",\"secret_id\":\"$SECRET_ID\"}" | python3 -c "import sys,json; print(json.load(sys.stdin)['auth']['client_token'])")

export VAULT_TOKEN="$TOKEN"
unset ROLE_ID SECRET_ID TOKEN

shift 2
exec "$@"

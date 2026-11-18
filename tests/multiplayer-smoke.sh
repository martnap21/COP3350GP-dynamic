#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
tmp="$(mktemp -d)"
pid=""
cleanup() {
  if [[ -n "$pid" ]]; then kill "$pid" 2>/dev/null || true; wait "$pid" 2>/dev/null || true; fi
  rm -rf "$tmp"
}
trap cleanup EXIT
export POKER_STATE_FILE="$tmp/state.json"
export POKER_TEST_URL="http://127.0.0.1:18987"
php -S 127.0.0.1:18987 -t . > "$tmp/php.log" 2>&1 &
pid="$!"
for i in {1..50}; do
  if curl -fsS "$POKER_TEST_URL/" > /dev/null 2>&1; then break; fi
  sleep .1
done
python3 tests/holdem-http-smoke.py || {
  cat "$tmp/php.log"
  exit 1
}

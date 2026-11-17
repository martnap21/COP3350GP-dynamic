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
port=18987
php -S "127.0.0.1:$port" -t . > "$tmp/php.log" 2>&1 &
pid="$!"
base="http://127.0.0.1:$port"
for i in {1..30}; do
  if curl -fsS "$base/" > /dev/null 2>&1; then break; fi
  sleep .1
done

get_token() {
  local jar="$1"
  curl -fsS -c "$jar" -b "$jar" "$base/" |
    grep -oE 'name="csrf-token" content="[a-f0-9]+"' |
    head -n 1 | cut -d '"' -f 4
}
post() {
  local jar="$1" token="$2" body="$3"
  curl -fsS -c "$jar" -b "$jar" -X POST "$base/api.php" \
    -H 'Content-Type: application/json' -H "X-CSRF-Token: $token" \
    --data "$body"
}
alice="$tmp/alice.cookies"
bob="$tmp/bob.cookies"
a_token="$(get_token "$alice")"
b_token="$(get_token "$bob")"
[[ -n "$a_token" && -n "$b_token" ]] || { echo 'Missing CSRF token'; exit 1; }
post "$alice" "$a_token" '{"action":"join","name":"Alice"}' > /dev/null
post "$bob" "$b_token" '{"action":"join","name":"Bob"}' > /dev/null
start="$(post "$alice" "$a_token" '{"action":"start"}')"
python3 -c 'import json,sys
d=json.loads(sys.argv[1])
assert d["phase"]=="drawing"
assert len(d["players"])==2
me=[p for p in d["players"] if p["id"]==d["you"]][0]
op=[p for p in d["players"] if p["id"]!=d["you"]][0]
assert len(me["hand"])==5 and len(op["hand"])==0
' "$start"

bob_view="$(curl -fsS -c "$bob" -b "$bob" "$base/api.php")"
python3 -c 'import json,sys
d=json.loads(sys.argv[1]); me=[p for p in d["players"] if p["id"]==d["you"]][0]
op=[p for p in d["players"] if p["id"]!=d["you"]][0]
assert len(me["hand"])==5 and len(op["hand"])==0
' "$bob_view"

post "$alice" "$a_token" '{"action":"draw","positions":[0,2]}' > /dev/null
final="$(post "$bob" "$b_token" '{"action":"draw","positions":[]}' )"
python3 -c 'import json,sys
d=json.loads(sys.argv[1]); assert d["phase"]=="showdown"
assert len(d["winners"])>=1
assert all(len(p["hand"])==5 for p in d["players"])
assert sum(p["wins"] for p in d["players"])==len(d["winners"])
' "$final"
lobby="$(post "$bob" "$b_token" '{"action":"lobby"}')"
python3 -c 'import json,sys
d=json.loads(sys.argv[1]); assert d["phase"]=="lobby"
assert all(len(p["hand"])==0 for p in d["players"])
' "$lobby"
echo "Two-browser simulated multiplayer flow passed."

#!/usr/bin/env bash
# Walks the whole Bloom API against a server: register -> verify -> me -> profile ->
# centres -> appointment -> cancel -> login -> logout -> delete account.
#
# Needs BLOOM_OTP_DEBUG=true on the server (STAGING ONLY) so the code comes back in the response.
#   API=https://your-staging-host/api PHONE=08031234567 ./scripts/bloom-smoke.sh
set -u
API="${API:?set API, e.g. https://staging.example.com/api}"
PHONE="${PHONE:?set PHONE, e.g. 08031234567}"
PASS="${PASS:-Bloom2026pass}"
H=(-H "Accept: application/json" -H "Content-Type: application/json")

j() { python3 -c "import sys,json
try:
  d=json.load(sys.stdin)
  for k in sys.argv[1].split('.'):
    d=d[int(k)] if isinstance(d,list) else d[k]
  print(d)
except Exception: print('')" "$1"; }
step() { printf '\n\033[1m== %s\033[0m\n' "$1"; }

step "register"
R=$(curl -s "${H[@]}" -X POST "$API/bloom/auth/register" -d "{\"name\":\"Smoke Test\",\"phone\":\"$PHONE\",\"password\":\"$PASS\",\"age\":42,\"need\":\"screening\"}")
echo "$R"; CODE=$(echo "$R" | j debug_code)
[ -z "$CODE" ] && { echo "No debug_code returned. Set BLOOM_OTP_DEBUG=true on staging (or type the SMS code):"; read -r CODE; }

step "wrong code is refused"
curl -s "${H[@]}" -X POST "$API/bloom/auth/verify" -d "{\"phone\":\"$PHONE\",\"code\":\"000000\"}"; echo

step "verify"
V=$(curl -s "${H[@]}" -X POST "$API/bloom/auth/verify" -d "{\"phone\":\"$PHONE\",\"code\":\"$CODE\"}")
echo "$V"; TOKEN=$(echo "$V" | j access_token)
[ -z "$TOKEN" ] && { echo "FAILED: no token"; exit 1; }
A=(-H "Authorization: Bearer $TOKEN")

step "me";       curl -s "${H[@]}" "${A[@]}" "$API/bloom/auth/me"; echo
step "update profile"; curl -s "${H[@]}" "${A[@]}" -X PATCH "$API/bloom/profile" -d '{"name":"Smoke Tested","age":43}'; echo
step "centres near Abuja"; C=$(curl -s "${H[@]}" "$API/bloom/centres?lat=9.0579&lng=7.4951&limit=3"); echo "$C"
FID=$(echo "$C" | j data.0.id)

if [ -n "$FID" ]; then
  D=$(date -d "+2 days" +%F 2>/dev/null || date -v+2d +%F)
  step "request appointment at centre $FID"
  P=$(curl -s "${H[@]}" "${A[@]}" -X POST "$API/bloom/appointments" -d "{\"facility_id\":$FID,\"type\":\"mammogram\",\"preferred_date\":\"$D\",\"preferred_time\":\"10:30\"}")
  echo "$P"; AID=$(echo "$P" | j data.id)
  step "list appointments"; curl -s "${H[@]}" "${A[@]}" "$API/bloom/appointments"; echo
  [ -n "$AID" ] && { step "cancel"; curl -s "${H[@]}" "${A[@]}" -X POST "$API/bloom/appointments/$AID/cancel"; echo; }
else
  echo "(no centres returned: check isScreeningCenter / latitude / longitude / status on facilities)"
fi

step "staff token must NOT work here (expect 401)"; curl -s -o /dev/null -w "%{http_code}\n" "${H[@]}" -H "Authorization: Bearer not.a.token" "$API/bloom/auth/me"

step "logout";  curl -s "${H[@]}" "${A[@]}" -X POST "$API/bloom/auth/logout"; echo
step "old token now refused (expect 401)"; curl -s -o /dev/null -w "%{http_code}\n" "${H[@]}" "${A[@]}" "$API/bloom/auth/me"
step "login"; L=$(curl -s "${H[@]}" -X POST "$API/bloom/auth/login" -d "{\"phone\":\"$PHONE\",\"password\":\"$PASS\"}"); echo "$L"
TOKEN=$(echo "$L" | j access_token); A=(-H "Authorization: Bearer $TOKEN")
step "wrong password (expect 401)"; curl -s -o /dev/null -w "%{http_code}\n" "${H[@]}" -X POST "$API/bloom/auth/login" -d "{\"phone\":\"$PHONE\",\"password\":\"nope\"}"
step "delete account"; curl -s "${H[@]}" "${A[@]}" -X DELETE "$API/bloom/account" -d "{\"password\":\"$PASS\"}"; echo
echo; echo "Done."

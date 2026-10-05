#!/usr/bin/env bash
#
# Taab-avari ru mohit e VAGHEI — na fake.
#
# ── Chera in file، vaghti `TickPipelineTest` hamin ha ro test mikone ──
# Un test ha ba FAKE ejra mishan: un ha sabt mikonan ke pipeline CHETOR be
# shekast vakonesh neshun mide. In script sabt mikone ke adapter haye VAGHEI
# hamun shekasti ro mikonan ke fake ha farz karde-and. Do chiz e joda-an —
# va faseleh beyn eshun daghighan jayi-e ke ghat'i ha ghayem mishan.
#
# Masraf (az HOST، na az tu container):
#   bash scripts/resilience-check.sh
#
# ⚠️ `price-worker` motevaghef mishe ta tick ha dast e MA bashan، va tu
# `trap` barmigarde — hatta agar script vasat beterke.
# ⚠️ Hich volume-i pak nemishe. Faghat `stop`/`start`.

set -uo pipefail

DC="docker compose -f docker-compose.local.yml"
PROVIDER="${PROVIDER:-tgju}"
SYMBOL="${SYMBOL:-xau-oz-usd}"

PASS=0
FAIL=0

say()  { printf '\n\033[1m%s\033[0m\n' "$*"; }
info() { printf '   %s\n' "$*"; }

check() { # check <tozih> <actual> <entezar>
  if [[ "$2" == "$3" ]]; then
    printf '   \033[32m✓\033[0m %s\n' "$1"
    PASS=$((PASS + 1))
  else
    printf '   \033[31m✗\033[0m %s  (entezar: %s، vaghe'\''iat: %s)\n' "$1" "$3" "$2"
    FAIL=$((FAIL + 1))
  fi
}

check_gt() { # check_gt <tozih> <actual> <hadaghal>
  if [[ "$2" =~ ^[0-9]+$ ]] && (( $2 > $3 )); then
    printf '   \033[32m✓\033[0m %s  (%s)\n' "$1" "$2"
    PASS=$((PASS + 1))
  else
    printf '   \033[31m✗\033[0m %s  (bayad > %s bud، vaghe'\''iat: %s)\n' "$1" "$3" "$2"
    FAIL=$((FAIL + 1))
  fi
}

in_app() { $DC exec -T octane "$@"; }

probe() { in_app php scripts/resilience-probe.php "$1" "$2" "$PROVIDER" "$SYMBOL" 2>/dev/null | tail -1; }

# `--force` ejbari-e: bedun esh، yek tick e dasti bala-fasele ba'd az tick e
# worker «hich provider e fa'ali baraye poll nist» mide (chun `next_poll_at`
# 15 saniye jolo-st) va kol e senario bi-ma'ni mishe.
tick() { in_app php artisan pricing:tick --json --force 2>/dev/null | grep -E '^\{' | tail -1; }

jget() { python3 -c 'import json,sys
d=json.loads(sys.stdin.read() or "{}")
for k in sys.argv[1].split("."):
    if isinstance(d,list): d=d[int(k)]
    else: d=d.get(k) if isinstance(d,dict) else None
    if d is None: break
print("" if d is None else (str(d).lower() if isinstance(d,bool) else d))' "$1"; }

wait_healthy() { # wait_healthy <service> <saniye>
  local name id state
  name="$1"
  for _ in $(seq 1 "${2:-60}"); do
    id=$($DC ps -q "$name" 2>/dev/null)
    if [[ -n "$id" ]]; then
      state=$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$id" 2>/dev/null)
      [[ "$state" == "healthy" || "$state" == "running" ]] && return 0
    fi
    sleep 2
  done
  info "⚠️  «$name» salem nashod."
  return 1
}

restore() {
  say "Bargardandan e mohit"
  $DC start influxdb postgresql redis redis-prices >/dev/null 2>&1
  wait_healthy postgresql 60 >/dev/null
  wait_healthy redis-prices 60 >/dev/null
  wait_healthy influxdb 60 >/dev/null
  $DC start price-worker >/dev/null 2>&1
  info "price-worker barmigardid."
}
trap restore EXIT

# ──────────────────────────────────────────────────────────────────────
say "Amade-sazi"
$DC stop price-worker >/dev/null 2>&1
info "price-worker motevaghef shod (tick ha dast e ma-st)."

in_app php artisan pricing:influx:drain >/dev/null 2>&1
BASE=$(probe "$(( $(date +%s) - 600 ))" "$(date +%s)")
check "spool khali-e (noghte ye shoru' e tamiz)" "$(jget spool_size <<<"$BASE")" "0"

# ══════════════════════════════════════════════════════════════════════
say "Senario A — Influx ghat' ast"
A_FROM=$(date +%s)
$DC stop influxdb >/dev/null 2>&1

T1=$(tick); sleep 16; T2=$(tick)
A_TO=$(date +%s)

check "tick e 1 rad nashod"                  "$(jget skipped <<<"$T1")" "false"
check "tick e 2 rad nashod"                  "$(jget skipped <<<"$T2")" "false"
check_gt "gheymat paziresh shod"             "$(jget accepted <<<"$T2")" 0
check "sink e influx shekast khord"          "$(jget sinks.influx.ok <<<"$T2")" "false"
check "sink e redis salem mund"              "$(jget sinks.redis.ok <<<"$T2")" "true"
check "sink e postgres salem mund"           "$(jget sinks.postgres.ok <<<"$T2")" "true"

DURING=$(probe "$A_FROM" "$A_TO")
check_gt "spool por shod"                    "$(jget spool_size <<<"$DURING")" 0
check "tablo zende mund"                     "$(jget board.degraded <<<"$DURING")" "false"
check_gt "tablo radif dare"                  "$(jget board.rows <<<"$DURING")" 0

say "Senario A — bargasht"
$DC start influxdb >/dev/null 2>&1
wait_healthy influxdb 90

# 🔴 Shomaresh e GHABL az drain — paye ye moghayese.
#
# ⚠️ Inja «bayad daghighan 0 bashe» ghalat ast: `CandleRequestDto::make()`
# marz e `from` ro be PAEEN quantize mikone (60s baraye m1، baraye hit rate
# e cache). Pas panjare ta 59 saniye AGHAB tar shoru' mishe va yek daghighe
# ye GHABL az ghat'i ro ham mishmare. Test e avval hamin tor ghermez shod.
#
# Chizi ke VAGHE'AN ma'ni dare: ba'd az drain bayad BISHTAR beshe.
PRE=$(probe "$A_FROM" "$A_TO")
PRE_M1=$(jget m1.count <<<"$PRE")
info "candle haye m1 ghabl az drain: ${PRE_M1}"
check_gt "spool hanuz tick ha ro dare"       "$(jget spool_size <<<"$PRE")" 0

say "Senario A — drain"
in_app php artisan pricing:influx:drain 2>&1 | tail -3 | sed 's/^/   /'

AFTER=$(probe "$A_FROM" "$A_TO")
check "spool khali shod"                     "$(jget spool_size <<<"$AFTER")" "0"
check "Influx bargasht"                      "$(jget influx_available <<<"$AFTER")" "true"
# 🔴 Check e ASLI ye kol e senario: tedad e candle haye m1 tu HAMUN panjare
# bayad ZIAD shode bashe. `Interval::M1` zende az bucket e `ticks` khunde
# mishe، pas in daghighan ya'ni tick haye spool-shode bargashtan va gap por
# shod.
check_gt "ba'd az drain، candle haye m1 ziad shodan" "$(jget m1.count <<<"$AFTER")" "$PRE_M1"

# ══════════════════════════════════════════════════════════════════════
say "Senario B — redis-prices ghat' ast"
$DC stop redis-prices >/dev/null 2>&1

T3=$(tick)
check "tick rad nashod"                      "$(jget skipped <<<"$T3")" "false"
check_gt "gheymat paziresh shod"             "$(jget accepted <<<"$T3")" 0
check "sink e redis shekast khord"           "$(jget sinks.redis.ok <<<"$T3")" "false"
check "sink e postgres salem mund"           "$(jget sinks.postgres.ok <<<"$T3")" "true"
check "sink e influx salem mund"             "$(jget sinks.influx.ok <<<"$T3")" "true"

B=$(probe "$(( $(date +%s) - 600 ))" "$(date +%s)")
check "Redis gheyr e dastres e'lam shod"     "$(jget redis_available <<<"$B")" "false"
# 🔴 Tablo bayad az POSTGRES biad va KHODESH ro `degraded` e'lam kone.
check "tablo be Postgres fallback kard"      "$(jget board.degraded <<<"$B")" "true"
check_gt "tablo hanuz radif dare"            "$(jget board.rows <<<"$B")" 0

say "Senario B — bargasht"
$DC start redis-prices >/dev/null 2>&1
wait_healthy redis-prices 60
T4=$(tick)
B2=$(probe "$(( $(date +%s) - 600 ))" "$(date +%s)")
check "sink e redis bargasht"                "$(jget sinks.redis.ok <<<"$T4")" "true"
check "tablo dige degraded nist"             "$(jget board.degraded <<<"$B2")" "false"

# ══════════════════════════════════════════════════════════════════════
say "Senario C — Postgres ghat' ast"
$DC stop postgresql >/dev/null 2>&1

T5=$(tick)
# 🔴 Inja AMDAN tick rad mishe — va in ba matn e `docs/PLAN.md` farg dare.
# Stage e load e gheymat e ghabli `critical` ast: bedun e snapshot e ghabli
# `PriceSanity` (enheraf e tick-be-tick) va `QuoteEnricher` (dayOpen،
# direction) khamoosh mishan. Neveshtan e gheymat ba mohafez haye khamoosh
# badtar az nanveshtan ast: yek khata ye 10x mirese be Influx va unja
# DAEMI mishe.
check "tick amdan rad shod (mohafez ha bedun e snapshot kar nemikonan)" \
      "$(jget skipped <<<"$T5")" "true"

C=$(probe "$(( $(date +%s) - 600 ))" "$(date +%s)")
# Chizi ke VAGHE'AN mohem ast: site zende mimune.
check "tablo az Redis zende mund"            "$(jget board.degraded <<<"$C")" "false"
check_gt "tablo radif dare"                  "$(jget board.rows <<<"$C")" 0

say "Senario C — bargasht"
$DC start postgresql >/dev/null 2>&1
wait_healthy postgresql 90
T6=$(tick)
check "tick bargasht"                        "$(jget skipped <<<"$T6")" "false"
check "sink e postgres bargasht"             "$(jget sinks.postgres.ok <<<"$T6")" "true"

# ──────────────────────────────────────────────────────────────────────
say "Natije"
printf '   \033[32m%d sabz\033[0m، \033[31m%d ghermez\033[0m\n' "$PASS" "$FAIL"
[[ "$FAIL" -eq 0 ]] || exit 1

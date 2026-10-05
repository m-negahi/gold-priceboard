---
name: docker-dev
description: Ejra, debug va amaliat e mohit e Docker e PriceBoard — port ha, service ha, dastur haye artisan/composer tu container, log ha, va moshkelat e ma'mul. Invoke kon vaghti mohit ro bala miari, container kharab shode, ya nemidooni log koja-st.
---

# Docker — ejra va debug

Compose file: **`docker-compose.local.yml`** (na `docker-compose.yml`).

```bash
alias dc='docker compose -f docker-compose.local.yml'
```

## Bala avordan

```bash
docker compose -f docker-compose.local.yml up -d
docker compose -f docker-compose.local.yml ps          # hame bayad healthy bashan
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8300/up    # 200
```

## Port ha — 🔴 avazeshun nakon

Ru in machine projeh haye digar ham hastan. Port haye ma **amdan shift shodan**:

| Service | Host port | Chera in adad |
|---|---|---|
| Octane | **8300** | 8000=livewire_learn، 8100=hg، 8200=b2b |
| Vite | **5373** | 5173=hg/livewire_learn، 5273=b2b |
| Postgres | **55434** | 55432=hg، 55433=b2b |
| Redis (cache/queue) | **56381** | 56379=hg، 56380=b2b |
| Redis (prices) | **56382** | — |
| InfluxDB | **8386** | — |

Ghabl az har taghir e port:
```bash
for c in $(docker ps -a --format '{{.Names}}'); do
  echo "--- $c"; docker inspect "$c" --format '{{range $p, $conf := .HostConfig.PortBindings}}{{$p}} -> {{(index $conf 0).HostPort}} {{end}}'
done
```

## Service ha

| Service | Kar |
|---|---|
| `octane` | app (Swoole). Port 8000 e dakheli → 8300 e host |
| `vite` | HMR |
| `horizon` | queue worker |
| `scheduler` | `schedule:work` |
| **`price-worker`** | halghe ye 15s e `pricing:tick` — **grid-aligned** |
| `postgresql` | DB e asli |
| `redis` | cache / session / queue |
| **`redis-prices`** | faghat gheymat — `noeviction` |
| `influxdb` | tick + candle |

### Chera do ta Redis
`redis` e asli `--maxmemory-policy allkeys-lru` dare → tu feshar e memory **har kelidi** ro evict mikone، **shamel e gheymat ha**، dar hali ke khodesh ro salem gozaresh mide → board khali mishe bedun e hich error.
`redis-prices` ba `noeviction` + kelid haye **bedun e TTL** in ro hall mikone. **Gheymat ro ru `redis` e asli nanevis.**

### Chera `price-worker` halghe-ast، na scheduler
Har tick yek process e taze → bedun e memory leak. Tartibi → bedun e overlap. Va `Cache::lock('pricing:tick', 14)` be onvan e kamarband e dovom.
Halghe **grid-aligned** ast:
```sh
while :; do php artisan pricing:tick; sleep $((15 - $(date +%s) % 15)); done
```
`sleep 15` e sade drift mikone va tick ha ru grid e 15s nemioftan — ke **idempotency e Influx ro mishkane**.

## Dastur haye ma'mul

```bash
dc exec octane php artisan <cmd>
dc exec octane composer <cmd>
dc exec octane php artisan test
dc exec octane php artisan pricing:tick
dc exec octane php artisan pricing:influx:health
dc exec octane vendor/bin/pint --dirty

dc exec postgresql psql -U priceboard -d priceboard
dc exec redis-prices redis-cli -a "$REDIS_PASSWORD"
dc exec influxdb influx query 'from(bucket:"ticks") |> range(start:-5m) |> limit(n:5)'
```

## Log ha

```bash
dc logs -f octane
dc logs -f price-worker          # khoruji e har tick (Finglish)
dc exec octane tail -f storage/logs/pricing/pricing-$(date +%F).log   # audit e JSON e feed
dc exec octane tail -f storage/logs/laravel.log
```
Channel e `pricing` JSON-per-line ast (Filebeat/Kibana-ready) va se event dare: `pricing.upstream` (chi gereftim)، `pricing.stored` (chi neshast)، `pricing.rejected` / `pricing.failed`.

## Moshkelat e ma'mul

| Alamat | Dalil | Hall |
|---|---|---|
| Taghir e `.env` asar nadare | Octane process e long-running ast | `dc up -d --force-recreate` (na `octane:reload`) |
| `octane` healthy nemishe | migration ya `.env` kharab | `dc logs octane` |
| Chart khali | task e Influx morde | `php artisan pricing:influx:health` + skill e `influx-timeseries` |
| Board khali vali tick salem | kelid e Redis evict shode | motma'en sho ru `redis-prices` minevisi va **bedun e TTL** |
| `pricing:tick` migé lock gerefte shode | tick e ghabli gir karde | `dc restart price-worker` |
| Composer/npm timeout (Iran) | mirror | `USE_IRAN_MIRROR=true` tu `.env` ba'd rebuild |
| `ext-curl` nist | image | `dc exec octane php -m \| grep -E 'curl\|mbstring\|json'` → nabud، be Dockerfile ezafe kon |

## Check e taab-avari (`scripts/resilience-check.sh`)

```bash
bash scripts/resilience-check.sh          # az HOST، na az tu container
PROVIDER=estjt SYMBOL=xau-18k-g-irr bash scripts/resilience-check.sh
```

Se senario ro ru mohit e **VAGHEI** mizane (na fake): `influxdb` ghat' → spool por → `start` + `drain` → **gap bayad por beshe**؛ `redis-prices` ghat' → tablo bayad be Postgres fallback kone va khodesh ro `degraded` e'lam kone؛ `postgresql` ghat' → tick amdan rad mishe vali tablo az Redis zende mimune.

| Nokte | Chera |
|---|---|
| `price-worker` motevaghef mishe | tick ha bayad dast e script bashan، vagarna assert ha flaky mishan. Tu `trap` barmigarde — hatta agar script beterke. |
| Hich volume-i pak nemishe | faghat `stop`/`start`. |
| `--force` ejbari-e | bedun esh tick e dasti «hich provider e fa'ali baraye poll nist» mide (chun `next_poll_at` 15 saniye jolo-st). |
| ~90 saniye tool mikeshe | do ta sabr e 16 saniye-i baraye rad shodan az grid e 15s + montazer e healthy shodan. |

`scripts/resilience-probe.php` vaziat ro yek khat JSON mide (spool، tablo، store، dastresi ye Redis/Influx، candle haye `m1`). Har bakhsh try/catch e khodesh ro dare chun **kol e noghte ash in ast ke vaghti service ha ghat'-and ejra beshe**.

## Tick e dasti

```bash
dc exec octane php artisan pricing:tick --force            # `next_poll_at` ro nadide begir
dc exec octane php artisan pricing:tick --force --json      # baraye script/monitoring
```
🔴 **Bedun e `--force`**، yek tick e dasti ke bala-fasele ba'd az tick e worker ejra she «hich provider e fa'ali baraye poll nist» mide — chun worker `next_poll_at` ro 15 saniye jolo gozashte. Un payam mesl e yek khata ye **config** be nazar mirese، na mesl e «15 saniye sabr kon». `--force` grid ro dast nemizane (timestamp hanuz floor mishe، pas idempotent ast).

## Reset (faghat dev)

```bash
dc down                 # container ha — data mimune
dc down -v              # 🔴 volume ha ham pak mishan: Postgres + Redis + Influx
```
`-v` ya'ni **kol e tarikhche ye chart az beyn mire** va ba tavajoh be inke backfill nadarim، az sefr jam' mishe. Faghat vaghti vaghe'an lazem ast.

---
name: influx-timeseries
description: Neveshtan ya taghir e Flux query / task e InfluxDB 2.7, candle (OHLC) baraye KLineCharts, bucket va retention, va Octane-safety e client. Invoke kon har vaght be Influx dast mizani ya chart data khali/eshtebah dar miad.
---

# InfluxDB 2.7 — Flux، candle، task

## Bucket ha

| Bucket | Measurement | Manba' | Retention |
|---|---|---|---|
| `ticks` | `price` | write path | **30d** |
| `candles_1m` | `ohlc` | `ticks` | 90d |
| `candles_5m` | `ohlc` | `candles_1m` | 180d |
| `candles_15m` | `ohlc` | `candles_1m` | 400d |
| `candles_1h` | `ohlc` | `candles_15m` | 1095d |
| `candles_4h` | `ohlc` | `candles_1h` | ∞ |
| `candles_1d` | `ohlc` | `candles_1h` | ∞ |

Yek bucket be ezaye har interval (na yek bucket ba tag e `interval`) chun **retention tu Influx 2 khasiat e bucket ast**. Measurement hame yeki (`ohlc`) ta yek template e read faghat ba esm e bucket parameterize she.

**Cascade** (na hame az `ticks`): `candles_1d` az `candles_1h` sakhte mishe، na az `ticks`. Chera: `ticks` 30 rooz retention dare — ba'd az yek ghat'i ye chand-hafte-i ye task، sakhtan e candle e ruzane az `ticks` **ghabl e jobran nist**. OHLC-of-OHLC daghigh ast (open=first open، close=last close، high=max، low=min).

## Tag vs Field

**Tag** (indexed): `provider`, `symbol`, `side`, `quote`
**Field**: `ticks` → `value`, `lag` · candle → `open, high, low, close, count`

- **Hame ye field ha `float`-and، hatta `count`.** Int/float mismatch tu `acc.count + r.count` **shayeh tarin marg e saket e task** ast — task fail mishe va kasi ta hafte ha nemifahme.
- Cardinality e fe'li ≈ **165 series**. Influx ta 10⁶ ro dare. **Hichi azash kharj nakon**: `unit`, `metal`, `purity`, `label`, `scale`, `upstream_ts` **hargez tag nashan** (ya takrari-an ya per-point churn).

## Timestamp — floor ru grid e 15s

```php
$tickAt = intdiv(time(), 15) * 15;   // yek timestamp baraye KOL e tick
```
1. **Idempotency**: hoviyat e point = `(measurement, tagset, field, timestamp)` → replay e spool **overwrite** mikone na duplicate.
2. **Hamtarazi** beyn e provider ha baraye `pivot`. Timestamp e ms e floor-nashode har pivot ro nim-khali mikone.

Loop e worker ham bayad grid-aligned bashe (`sleep $((15 - $(date +%s) % 15))`)، na `sleep 15` e sade ke drift mikone.

## 🔴 Chahar tale-i ke chart ro saket kharab mikonan

### 1. `timeSrc: "_start"`
```flux
|> aggregateWindow(every: 1m, fn: first, timeSrc: "_start", createEmpty: false)
```
Default `"_stop"` ast → candle mioftad ru **akhar** e window → kol e chart yek candle jolo. Bug e klassik.

### 2. `drop(_field)` ghabl az `set(_field)`
```flux
o = src |> aggregateWindow(every: 1m, fn: first, timeSrc: "_start", createEmpty: false)
        |> drop(columns: ["_start","_stop","_field"])      // ⬅️ ejbari
        |> set(key: "_field", value: "open")
```
`_field` tu **group key** ast. `set` e tanha group key e bayat ja mizare va `union`/`pivot` **saket** soton mindaze.

### 3. `sort → limit → sort`
```flux
|> sort(columns: ["_time"], desc: true)
|> limit(n: 2000)
|> sort(columns: ["_time"])
```
`limit` e ba'd az sort e soudi **ghadimi tarin** N ro mide، na jadid tarin → chart e khali. `n` ro server-side ru **5000** cap kon.

### 4. `timezone.fixed(3h30m)` ru task haye `4h` va `1d`
```flux
import "timezone"
option location = timezone.fixed(offset: 3h30m)
```
Bedun esh `window(every: 1d)` be **nime-shab e UTC = 03:30 Tehran** align mishe → har candle e ruzane **do rooz e kari ye Irani** ro sovar mishe. Eshtebah be shekli ke mah ha kasi nemifahme.
`timezone.fixed` na `location(name: "Asia/Tehran")` — Iran az 2022 DST nadare va offset e sabet vabastegi be tzdata ro hazf mikone. Offset tu `config/pricing.php` (`PRICING_TZ_OFFSET`).

## Query e candle (shape e KLineCharts)

`m5`–`d1` → bucket e candle. **`m1` zende az `ticks`** (hamishe current، bedun e vabastegi be latency e task).

```flux
from(bucket: "candles_5m")
  |> range(start: 2026-09-04T00:00:00Z, stop: 2026-10-04T09:00:00Z)
  |> filter(fn: (r) => r._measurement == "ohlc")
  |> filter(fn: (r) => r.provider == "tgju" and r.symbol == "xau-18k-g-irr" and r.side == "last")
  |> filter(fn: (r) => r._field == "open" or r._field == "high"
                    or r._field == "low"  or r._field == "close" or r._field == "count")
  |> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")
  |> keep(columns: ["_time","open","high","low","close","count"])
  |> sort(columns: ["_time"], desc: true) |> limit(n: 2000) |> sort(columns: ["_time"])
```

Khoruji → `{timestamp: ms, open, high, low, close, volume}`. `timestamp` bayad **millisecond** bashe (KLineCharts).
`volume` = `count` (tedad e tick) — KLineCharts in key ro mikhahe va pane e sefr e saf shekaste be nazar mirese.

## Task ha

6 ta، ba `pricing:influx:provision` e **idempotent** sakhte mishan. Flux ha tu `resources/influx/tasks/*.flux` **checked-in**-and (manba' e haghighat — na click kardan tu UI).

| Task | `every` | `offset` | lookback | manba' → hadaf |
|---|---|---|---|---|
| `candles_1m` | 1m | 15s | `-3m` | `ticks` → `candles_1m` |
| `candles_5m` | 1m | 20s | `-11m` | `candles_1m` → `candles_5m` |
| `candles_15m` | 5m | 30s | `-31m` | `candles_1m` → `candles_15m` |
| `candles_1h` | 5m | 40s | `-3h` | `candles_15m` → `candles_1h` |
| `candles_4h` | 15m | 1m | `-9h` | `candles_1h` → `candles_4h` |
| `candles_1d` | 15m | 2m | `-50h` | `candles_1h` → `candles_1d` |

**`every` amdan joda az andaze ye bar ast** ba lookback ≥ 2 bar: task e `1d` ke rooz-i yek bar bezane، candle e emruz ta farda vojud nadare. Ba `every: 15m` + `-50h` bar e emruz pey-dar-pey refresh mishe (upsert) va **hich partial-bar stitching lazem nist**.
`offset` be write haye dir-res (va drain e spool) vaght mide ke beresan.

Olgu ye `reduce` (single-pass، tartib e daghigh e open/close):
```flux
|> window(every: 1m)
|> reduce(
    identity: {open: 0.0, high: 0.0, low: 0.0, close: 0.0, count: 0.0, seeded: false},
    fn: (r, accumulator) => ({
      open:   if accumulator.seeded then accumulator.open else r._value,
      high:   if accumulator.seeded and accumulator.high > r._value then accumulator.high else r._value,
      low:    if accumulator.seeded and accumulator.low  < r._value then accumulator.low  else r._value,
      close:  r._value,
      count:  accumulator.count + 1.0,
      seeded: true,
    }))
|> drop(columns: ["seeded"])
|> duplicate(column: "_start", as: "_time")     // ⬅️ timestamp = zaman e BAZ shodan e bar
|> window(every: inf)
|> to(bucket: "candles_1m", org: "priceboard", measurement: "ohlc",
      tagColumns: ["provider","symbol","side","quote"],
      fieldFn: (r) => ({open: r.open, high: r.high, low: r.low, close: r.close, count: r.count}))
```

## 🔴 Octane safety

`WriteApi` e `BATCHING` yek **buffer e mutable** ast. Singleton zire Octane → buffer beyn e request ha mimune، mitune tavasot e request e bi-rabt flush she، memory leak mikone، va ba recycle e worker **saket** khali mishe. `CurlMultiHandler` e Guzzle ham handle e persistent dare ke share kardanesh beyn e coroutine haye Swoole response ha ro ghati mikone.

| Chi | Bind |
|---|---|
| `InfluxTimeSeries` (adapter e ma، `final readonly`، faghat config) | ✅ `singleton` |
| `InfluxDB2\Client` / `WriteApi` / `QueryApi` | ❌ **hargez bind nashe** — na singleton، na scoped، na bind |

- **Write**: faghat tu worker e CLI، faghat `WriteType::SYNCHRONOUS`، client **tu khod e method** sakhte va tu `finally` `close()` beshe.
- **Read: aslan SDK estefade nakon.**
  ```php
  Http::withToken($readToken, 'Token')
      ->withHeaders(['Content-Type' => 'application/vnd.flux', 'Accept' => 'application/csv'])
      ->timeout(5)->withBody($flux, 'application/vnd.flux')
      ->post("{$url}/api/v2/query?org=".urlencode($org));
  ```
  + `FluxCsv::toCandles()` e ~40 khati. Yek class e kamel e khatar e Octane az request path hazf mishe، yek HTTP stack baraye kol e app، va parse e mostaghim be shape e KLineCharts jaye alloc kardan e `FluxTable`/`FluxRecord` (series e `h1` e yek-sale = 8,760 × 5 ≈ 44k record).

## Injection

Hich chiz e karbar mostaghim tu Flux narire:
- `symbol` → `Instrument::tryFrom()`، `interval` → `Interval::tryFrom()`، `side` → `Side::tryFrom()`
- `provider` → check ba lista ye key e DB
- `from`/`to` → cast be `int`، ba'd RFC3339
- Ru hame: `Flux::tag()` ke har chi gheyr e `[a-z0-9_\-]` ro rad mikone

Support e query-parameter e SDK za'if ast → **allowlist e sakht defa' e asli ast، na laye ye ezafe.**

## Do token

`PRICING_INFLUX_WRITE_TOKEN` (write-only ru `ticks` — worker) · `PRICING_INFLUX_READ_TOKEN` (read-only — web).
Token e web lo bere، kasi natune tarikhche ja'l kone.

## Vaghti chart khali/eshtebah ast — tartib e check

```bash
# 1. aslan tick mireze tu Influx?
docker compose -f docker-compose.local.yml exec influxdb \
  influx query 'from(bucket:"ticks") |> range(start:-5m) |> limit(n:5)'

# 2. task ha salem-and?
docker compose -f docker-compose.local.yml exec octane php artisan pricing:influx:health

# 3. candle sakhte mishe?
docker compose -f docker-compose.local.yml exec influxdb \
  influx query 'from(bucket:"candles_1m") |> range(start:-10m) |> limit(n:5)'
```
Khali bud → chahar tale ye bala ro be tartib check kon. `pricing:influx:health` bayad assert kone: akharin bar e `candles_1m` < 3 daghighe، akharin `candles_1d` **emruz be vaght e Tehran**، va `GET /api/v2/tasks/{id}/runs` akharin run esh `success`.

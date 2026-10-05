// ticks -> candles_1m
//
// Tanha task-i ke az tick e KHAM mikhune. Baghi az candle be candle miran
// (cascade): sakhtan e candle e ruzane az bucket e 30-rooze ba'd az yek
// ghat'i ye chand-hafte-i ghabl e jobran nist.
//
// `every: 1m` + lookback e `-3m` ya'ni do bar e akhar HAR daghighe dobare
// hesab mishan. `to()` upsert mikone, pas yek tick e dir-res ya replay e
// spool khod-be-khod tarikhche ro eslah mikone.

import "timezone"

option location = timezone.fixed(offset: {{tz_offset}})

option task = {name: "candles_1m", every: 1m, offset: 15s}

from(bucket: "ticks")
    |> range(start: -3m)
    |> filter(fn: (r) => r._measurement == "price" and r._field == "value")
    |> window(every: 1m)
    // Yek pass: `open` avvalin meghdar, `close` akharin. `reduce` radif ha ro
    // be tartib e zaman mibine, pas `seeded` tanha chizi-e ke lazem darim.
    |> reduce(
        identity: {open: 0.0, high: 0.0, low: 0.0, close: 0.0, count: 0.0, seeded: false},
        fn: (r, accumulator) => ({
            open: if accumulator.seeded then accumulator.open else r._value,
            high: if accumulator.seeded and accumulator.high > r._value then accumulator.high else r._value,
            low: if accumulator.seeded and accumulator.low < r._value then accumulator.low else r._value,
            close: r._value,
            // ⚠️ FLOAT (`1.0` na `1`): `acc.count + 1` ba int/float mismatch
            // task ro SAKET mikoshe — shayeh tarin marg e task tu hamin olgu.
            count: accumulator.count + 1.0,
            seeded: true,
        }),
    )
    |> drop(columns: ["seeded"])
    // Timestamp e bar = lahze ye BAZ shodanesh, na baste shodanesh.
    |> duplicate(column: "_start", as: "_time")
    |> window(every: inf)
    |> set(key: "_measurement", value: "ohlc")
    |> to(
        bucket: "candles_1m",
        org: "{{org}}",
        tagColumns: ["provider", "symbol", "side", "quote"],
        fieldFn: (r) => ({open: r.open, high: r.high, low: r.low, close: r.close, count: r.count}),
    )

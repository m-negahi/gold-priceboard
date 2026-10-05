// candles_1h -> candles_4h
//
// OHLC-of-OHLC daghigh ast: open = avvalin open, close = akharin close,
// high = max e high ha, low = min e low ha.
//
// `every` AMDAN az andaze ye bar kuchik tar ast: yek task e ruzane ke
// rooz-i yek bar bezane, candle e EMRUZ ro ta farda nemisaze. Ba lookback e
// `-9h` bar e jari pey-dar-pey refresh mishe (upsert), pas hich
// «partial bar stitching» tu laye ye read lazem nist.

import "timezone"

option location = timezone.fixed(offset: {{tz_offset}})

option task = {name: "candles_4h", every: 15m, offset: 1m}

from(bucket: "candles_1h")
    |> range(start: -9h)
    |> filter(fn: (r) => r._measurement == "ohlc")
    |> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")
    |> window(every: 4h)
    |> reduce(
        identity: {open: 0.0, high: 0.0, low: 0.0, close: 0.0, count: 0.0, seeded: false},
        fn: (r, accumulator) => ({
            open: if accumulator.seeded then accumulator.open else r.open,
            high: if accumulator.seeded and accumulator.high > r.high then accumulator.high else r.high,
            low: if accumulator.seeded and accumulator.low < r.low then accumulator.low else r.low,
            close: r.close,
            count: accumulator.count + r.count,
            seeded: true,
        }),
    )
    |> drop(columns: ["seeded"])
    |> duplicate(column: "_start", as: "_time")
    |> window(every: inf)
    |> set(key: "_measurement", value: "ohlc")
    |> to(
        bucket: "candles_4h",
        org: "{{org}}",
        tagColumns: ["provider", "symbol", "side", "quote"],
        fieldFn: (r) => ({open: r.open, high: r.high, low: r.low, close: r.close, count: r.count}),
    )

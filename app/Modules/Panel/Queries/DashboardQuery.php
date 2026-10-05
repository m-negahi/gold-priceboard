<?php

declare(strict_types=1);

namespace App\Modules\Panel\Queries;

use App\Modules\Common\Helpers\Jalali;
use App\Modules\Pricing\Dto\QuoteView;
use App\Modules\Pricing\Models\Instrument;
use App\Modules\Pricing\Models\PriceSource;
use App\Modules\Pricing\Queries\InstrumentQuery;
use App\Modules\Pricing\Queries\PriceQuoteQuery;
use App\Modules\Pricing\Queries\PriceSourceQuery;
use App\Modules\Pricing\Queries\SourceQuoteComparisonQuery;
use App\Modules\Pricing\Support\MarketCalendar;
use App\Modules\Pricing\Support\TimeWindow;
use App\Modules\Setting\Queries\SettingQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Yek snapshot-e kamel az mizkar-e admin (v2): navar-e amar, jadval-e gheymat, bishtarin
 * harekat ha, moghayese-ye provider ha, jalase-haye bazar, feed-e khata ha va list-e
 * "niaz be tavajoh".
 *
 * Hamin yek shekl ham tu safhe inline mishe (avalin paint bedoone fetch) va ham az
 * `/panel/api/dashboard` har 5 sanie be Alpine mirese — pas kelid ha snake_case-e JSON-and va
 * har tarikh-e jalali hamin ja, dar labe-ye server, sakhte mishe; client faghat neshun mide.
 *
 * Hich chizi inja be Influx nemirese: tick-haye sa'at-e akhir arzesh-e yek query-e tarikhche ro
 * dar masir-e 5 sanie-i nadaran (ADR-0007). Har chi hast az Postgres va cache-e Redis miad;
 * sparkline-e 24 sa'ate ro khod-e safhe tanbal va joda az `/panel/api/series/{code}` migire.
 *
 * Bakhsh-e mali (kif pool, fakتور, eshterak) amdan inja nist: un ma'zool-haye M9–M12-and va yek
 * placeholder-e khali bad-tar az jaye khali-e. Naghshe-ye mantaghe ha tu rahnama-ye safhe-ast.
 */
final readonly class DashboardQuery
{
    /** Khata-ye provider ta in andaze kootah mishe: tashkhis kafi-e, javab-e kamel hich vaght (ARCHITECTURE §8). */
    private const ERROR_LENGTH = 140;

    /** Chand ta az har jahat tu "bishtarin harekat ha". */
    private const MOVERS_LIMIT = 5;

    /** Ta chand rooz jolo donbal-e jalase-ye ba'di-ye bazar migardim (yek hafte + yek rooz baraye wrap). */
    private const SESSION_LOOKAHEAD_DAYS = 8;

    private const MINUTES_PER_DAY = 1440;

    /** Esm-e ensani-ye bazar ha; kelid-e nashenakhte hamun kelid neshun dade mishe. */
    private const MARKET_LABELS = [
        'irr_gold' => 'بازار طلای ایران',
        'global' => 'بازار جهانی',
    ];

    public function __construct(
        private PriceQuoteQuery $quotes,
        private PriceSourceQuery $sources,
        private InstrumentQuery $instruments,
        private SettingQuery $settings,
        private SourceQuoteComparisonQuery $comparison,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $now = CarbonImmutable::now();
        $calendar = MarketCalendar::fromSettings($this->settings);
        $instruments = $this->instruments->active()->keyBy('code');
        $quotes = $this->quoteRows($instruments, $calendar, $now);
        $sources = $this->sourceRows($now);
        $markets = $this->markets($instruments, $calendar, $now);

        return [
            'generated_at' => $now->toIso8601String(),
            'generated_at_jalali' => Jalali::format($now, 'Y/m/d H:i:s'),
            'kpis' => $this->kpis($instruments, $quotes, $sources, $markets),
            'quotes' => $quotes->values()->all(),
            'movers' => $this->movers($quotes),
            'comparison' => $this->comparison->compare($now),
            'sessions' => $this->sessions($markets, $calendar, $now),
            'errors' => $this->errors($sources),
            'sources' => $sources->values()->all(),
            'attention' => $this->attention($instruments, $quotes, $sources),
        ];
    }

    /**
     * @param  Collection<string, Instrument>  $instruments
     * @return Collection<int, array<string, mixed>>
     */
    private function quoteRows(Collection $instruments, MarketCalendar $calendar, CarbonImmutable $now): Collection
    {
        return $this->quotes->currentForBoard()
            ->map(function (QuoteView $quote) use ($instruments, $calendar, $now): array {
                $instrument = $instruments->get($quote->code);

                /** @var array<string, mixed> $row */
                $row = [
                    'code' => $quote->code,
                    'name' => $quote->name,
                    'kind' => $instrument?->kind->value ?? 'metal',
                    'unit' => $quote->unit,
                    'market' => $quote->market,
                    'market_label' => self::MARKET_LABELS[$quote->market] ?? $quote->market,
                    'market_open' => $calendar->isOpen($quote->market, $now),
                    'mid' => $quote->mid,
                    'buy' => $quote->buy,
                    'sell' => $quote->sell,
                    'change_abs' => $quote->changeAbs,
                    'change_pct' => $quote->changePct,
                    'direction' => $quote->direction(),
                    'source_name' => $quote->sourceName,
                    'as_of' => $quote->asOf->toIso8601String(),
                    'as_of_jalali' => Jalali::dateTime($quote->asOf),
                    'is_stale' => $quote->isStale,
                    'is_manual' => $quote->isManual(),
                ];

                return $row;
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function sourceRows(CarbonImmutable $now): Collection
    {
        return $this->sources->query()
            ->withCount('mappings')
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(function (PriceSource $source) use ($now): array {
                $cooldown = $source->disabled_until !== null && $source->disabled_until->isAfter($now)
                    ? (int) $now->diffInSeconds($source->disabled_until, absolute: true)
                    : 0;

                /** @var array<string, mixed> $row */
                $row = [
                    'key' => $source->key,
                    'name' => $source->name,
                    'role' => $source->role->value,
                    'role_label' => $source->role->label(),
                    'priority' => $source->priority,
                    'is_active' => $source->is_active,
                    'is_manual' => $source->key === PriceSource::MANUAL_KEY,
                    'status' => $this->sourceStatus($source, $cooldown),
                    'consecutive_failures' => $source->consecutive_failures,
                    'disabled_until' => $source->disabled_until?->toIso8601String(),
                    'cooldown_seconds' => $cooldown,
                    'last_success_at' => $source->last_success_at?->toIso8601String(),
                    'last_success_jalali' => Jalali::dateTime($source->last_success_at),
                    'last_error' => $this->truncate($source->last_error),
                    // Interval-i ke hamin alan dar kar-e, na fallback-e sabet (ADR-0026).
                    'interval_seconds' => $source->pollSchedule()->intervalAt($now),
                    'mappings_count' => (int) $source->getAttribute('mappings_count'),
                ];

                return $row;
            });
    }

    /**
     * Yek kalame baraye noghte-ye range-i-ye har source. Tartib mohem-e: source-e gheyr-e fa'al
     * hata age dar cooldown bashe "gheyr-e fa'al"-e, chon operator khodesh khamooshesh karde.
     */
    private function sourceStatus(PriceSource $source, int $cooldown): string
    {
        return match (true) {
            ! $source->is_active => 'inactive',
            $cooldown > 0 => 'cooldown',
            $source->consecutive_failures > 0 => 'failing',
            $source->last_success_at === null => 'idle',
            default => 'ok',
        };
    }

    /**
     * Bazar-hayi ke instrument-haye active roosheshun moamele mishan, ba vaziat-e feli.
     *
     * @param  Collection<string, Instrument>  $instruments
     * @return Collection<int, array{key: string, label: string, open: bool}>
     */
    private function markets(Collection $instruments, MarketCalendar $calendar, CarbonImmutable $now): Collection
    {
        return $instruments
            ->map(static fn (Instrument $instrument): string => $instrument->market ?: 'irr_gold')
            ->unique()
            ->values()
            ->map(static fn (string $market): array => [
                'key' => $market,
                'label' => self::MARKET_LABELS[$market] ?? $market,
                'open' => $calendar->isOpen($market, $now),
            ]);
    }

    /**
     * @param  Collection<string, Instrument>  $instruments
     * @param  Collection<int, array<string, mixed>>  $quotes
     * @param  Collection<int, array<string, mixed>>  $sources
     * @param  Collection<int, array{key: string, label: string, open: bool}>  $markets
     * @return array<string, mixed>
     */
    private function kpis(Collection $instruments, Collection $quotes, Collection $sources, Collection $markets): array
    {
        $lastTick = $sources
            ->filter(static fn (array $source): bool => $source['last_success_at'] !== null)
            ->sortByDesc('last_success_at')
            ->first();

        return [
            'instruments_active' => $instruments->count(),
            'instruments_quoted' => $quotes->count(),
            'sources_total' => $sources->count(),
            'sources_healthy' => $sources->where('status', 'ok')->count(),
            'sources_cooldown' => $sources->where('status', 'cooldown')->count(),
            'sources_failing' => $sources->where('status', 'failing')->count(),
            'stale_count' => $quotes->where('is_stale', true)->count(),
            // Chand instrument hamin alan gheymat-e dasti migiran (ADR-0034) — chip-e hoshdar-e mizkar.
            'manual_count' => $quotes->where('is_manual', true)->count(),
            'movers' => [
                'up' => $quotes->where('direction', 'up')->count(),
                'down' => $quotes->where('direction', 'down')->count(),
                'flat' => $quotes->where('direction', 'flat')->count(),
            ],
            'markets' => [
                'open' => $markets->where('open', true)->count(),
                'total' => $markets->count(),
                'list' => $markets->all(),
            ],
            'last_tick' => $lastTick === null ? null : [
                'at' => $lastTick['last_success_at'],
                'at_jalali' => $lastTick['last_success_jalali'],
                'source_key' => $lastTick['key'],
                'source_name' => $lastTick['name'],
            ],
        ];
    }

    /**
     * Panj roshd-e bozorg va panj oft-e bozorg-e emrooz, be tartib-e darsad-e taghir.
     *
     * Tartib ba float-e change_pct-e — faghat baraye sort, hich pool-i az un rad nemishe.
     *
     * @param  Collection<int, array<string, mixed>>  $quotes
     * @return array{up: list<array<string, mixed>>, down: list<array<string, mixed>>}
     */
    private function movers(Collection $quotes): array
    {
        $pick = static fn (array $row): array => [
            'code' => $row['code'],
            'name' => $row['name'],
            'kind' => $row['kind'],
            'mid' => $row['mid'],
            'change_abs' => $row['change_abs'],
            'change_pct' => $row['change_pct'],
            'direction' => $row['direction'],
            'is_stale' => $row['is_stale'],
            'market_open' => $row['market_open'],
        ];

        return [
            'up' => $quotes->where('direction', 'up')
                ->sortByDesc(static fn (array $row): float => (float) $row['change_pct'])
                ->take(self::MOVERS_LIMIT)
                ->map($pick)
                ->values()
                ->all(),
            'down' => $quotes->where('direction', 'down')
                ->sortBy(static fn (array $row): float => (float) $row['change_pct'])
                ->take(self::MOVERS_LIMIT)
                ->map($pick)
                ->values()
                ->all(),
        ];
    }

    /**
     * Jalase-haye emrooz-e har bazar baraye timeline: panjare-haye emrooz be darsad-e rooz (vaght-e
     * Tehran), jaye "alan", va taghir-e ba'di (baste mishe / baz mishe) ba zaman-e daghigh.
     *
     * Bazar-e bi-taghvim hamishe baz-e (hamun ghanoon-e MarketCalendar) va `always_open` migire ta
     * UI be jaye yek navar-e por, "taghvim nadarad" benevise.
     *
     * @param  Collection<int, array{key: string, label: string, open: bool}>  $markets
     * @return list<array<string, mixed>>
     */
    private function sessions(Collection $markets, MarketCalendar $calendar, CarbonImmutable $now): array
    {
        $hours = $this->settings->array('market_hours');
        $local = $now->setTimezone(TimeWindow::TIMEZONE);
        $nowMinutes = $local->hour * 60 + $local->minute + $local->second / 60;

        $sessions = [];

        foreach ($markets as $market) {
            $raw = $hours[$market['key']] ?? null;
            $windows = $this->parseWindows(is_array($raw) ? $raw : []);
            $open = $calendar->isOpen($market['key'], $now);

            $sessions[] = [
                'key' => $market['key'],
                'label' => $market['label'],
                'open' => $open,
                'always_open' => $windows === [],
                'now_pct' => $this->pct($nowMinutes),
                'now_time' => Jalali::format($now, 'H:i'),
                'segments' => $this->segmentsToday($windows, $local),
                'next' => $windows === [] ? null : $this->nextChange($windows, $open, $now, $local),
            ];
        }

        return $sessions;
    }

    /**
     * Panjare-haye setting ro be daghighe tabdil mikone; har panjare-i ke TimeWindow rad mikone
     * inja ham rad mishe, ta timeline hamun chizi ro neshun bede ke resolver mifahme.
     *
     * @param  array<array-key, mixed>  $raw
     * @return list<array{from: int, to: int, days: list<int>|null}>
     */
    private function parseWindows(array $raw): array
    {
        $windows = [];

        foreach ($raw as $window) {
            if (! is_array($window) || TimeWindow::tryFrom($window) === null) {
                continue;
            }

            $days = null;

            if (isset($window['days']) && is_array($window['days']) && $window['days'] !== []) {
                $days = array_values(array_filter(
                    array_map(static fn (mixed $d): int => (int) $d, $window['days']),
                    static fn (int $d): bool => $d >= 0 && $d <= 6,
                ));
            }

            $windows[] = [
                'from' => $this->minutes((string) $window['from']),
                'to' => $this->minutes((string) $window['to']),
                'days' => $days,
            ];
        }

        return $windows;
    }

    /**
     * Segment-haye emrooz: panjare-ye adi yek segment; panjare-i ke az nime-shab rad mishe emrooz
     * ta 24:00 mire va tekke-ye sobh-esh az panjare-ye dirooz miad.
     *
     * @param  list<array{from: int, to: int, days: list<int>|null}>  $windows
     * @return list<array{from: string, to: string, from_pct: float, to_pct: float}>
     */
    private function segmentsToday(array $windows, CarbonImmutable $local): array
    {
        $today = $local->dayOfWeek;
        $yesterday = $local->subDay()->dayOfWeek;
        $segments = [];

        foreach ($windows as $window) {
            if ($this->appliesOn($window, $today)) {
                $segments[] = $window['from'] <= $window['to']
                    ? $this->segment($window['from'], $window['to'])
                    : $this->segment($window['from'], self::MINUTES_PER_DAY);
            }

            if ($window['from'] > $window['to'] && $this->appliesOn($window, $yesterday)) {
                $segments[] = $this->segment(0, $window['to']);
            }
        }

        usort($segments, static fn (array $a, array $b): int => $a['from_pct'] <=> $b['from_pct']);

        return $segments;
    }

    /**
     * Taghir-e ba'di: age baz-e, nazdik-tarin payan-e panjare-i ke alan toosh-im; age baste-ast,
     * nazdik-tarin shoroo-e ayande ta yek hafte jolo.
     *
     * @param  list<array{from: int, to: int, days: list<int>|null}>  $windows
     * @return array{kind: string, at: string, at_time: string, at_jalali: string|null, in_seconds: int}|null
     */
    private function nextChange(array $windows, bool $open, CarbonImmutable $now, CarbonImmutable $local): ?array
    {
        $best = null;

        for ($offset = -1; $offset <= self::SESSION_LOOKAHEAD_DAYS; $offset++) {
            $day = $local->startOfDay()->addDays($offset);

            foreach ($windows as $window) {
                if (! $this->appliesOn($window, $day->dayOfWeek)) {
                    continue;
                }

                $start = $day->addMinutes($window['from']);
                $end = $window['from'] <= $window['to']
                    ? $day->addMinutes($window['to'])
                    : $day->addDay()->addMinutes($window['to']);

                $candidate = $open
                    ? ($start->lessThanOrEqualTo($now) && $end->greaterThan($now) ? $end : null)
                    : ($start->greaterThan($now) ? $start : null);

                if ($candidate !== null && ($best === null || $candidate->lessThan($best))) {
                    $best = $candidate;
                }
            }
        }

        if ($best === null) {
            return null;
        }

        $at = $best->setTimezone('UTC');

        return [
            'kind' => $open ? 'closes' : 'opens',
            'at' => $at->toIso8601String(),
            'at_time' => Jalali::format($at, 'H:i') ?? '',
            'at_jalali' => Jalali::dateTime($at),
            'in_seconds' => (int) $now->diffInSeconds($at, absolute: true),
        ];
    }

    /** @param array{from: int, to: int, days: list<int>|null} $window */
    private function appliesOn(array $window, int $dayOfWeek): bool
    {
        return $window['days'] === null || in_array($dayOfWeek, $window['days'], true);
    }

    /** @return array{from: string, to: string, from_pct: float, to_pct: float} */
    private function segment(int $from, int $to): array
    {
        return [
            'from' => sprintf('%02d:%02d', intdiv($from, 60), $from % 60),
            'to' => $to === self::MINUTES_PER_DAY ? '24:00' : sprintf('%02d:%02d', intdiv($to, 60), $to % 60),
            'from_pct' => $this->pct($from),
            'to_pct' => $this->pct($to),
        ];
    }

    private function pct(float $minutes): float
    {
        return round($minutes / self::MINUTES_PER_DAY * 100, 2);
    }

    /** `HH:MM` → daghighe az nime-shab; vorudi ghablan ba TimeWindow::tryFrom check shode. */
    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    /**
     * Feed-e khata: faghat source-haye fa'al-i ke ya alan moshkel daran ya akharin khata-shun
     * hanuz pak nashode. Zaman-e khod-e khata ro nadarim (sotoon-i nist), pas "akharin movafagh"
     * va shomarande-ye khata-haye peyapey neshun dade mishe.
     *
     * @param  Collection<int, array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    private function errors(Collection $sources): array
    {
        return $sources
            ->filter(static fn (array $source): bool => $source['is_active'] === true
                && ($source['last_error'] !== null || in_array($source['status'], ['cooldown', 'failing'], true)))
            ->map(static fn (array $source): array => [
                'key' => $source['key'],
                'name' => $source['name'],
                'status' => $source['status'],
                'priority' => $source['priority'],
                'consecutive_failures' => $source['consecutive_failures'],
                'cooldown_seconds' => $source['cooldown_seconds'],
                'disabled_until' => $source['disabled_until'],
                'last_error' => $source['last_error'],
                'last_success_at' => $source['last_success_at'],
                'last_success_jalali' => $source['last_success_jalali'],
            ])
            ->values()
            ->all();
    }

    /**
     * Har moshkel daghighan yek bar, ba tone-i ke UI mostaghim estefade mikone. Tartib = shedat:
     * source-e dar cooldown gheymat ro az dast mide, stale ya'ni hamin alan az dast rafte,
     * instrument-e bi-gheymat ya'ni hich vaght nagereftim.
     *
     * @param  Collection<string, Instrument>  $instruments
     * @param  Collection<int, array<string, mixed>>  $quotes
     * @param  Collection<int, array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    private function attention(Collection $instruments, Collection $quotes, Collection $sources): array
    {
        $items = [];

        foreach ($sources as $source) {
            if ($source['status'] === 'cooldown') {
                $items[] = [
                    'kind' => 'cooldown',
                    'tone' => 'danger',
                    'code' => $source['key'],
                    'title' => $source['name'].' در وقفهٔ خطا است',
                    'detail' => 'پس از '.$source['consecutive_failures'].' خطای پیاپی تا پایان وقفه از چرخهٔ دریافت خارج شده است.',
                    'until' => $source['disabled_until'],
                ];
            } elseif ($source['status'] === 'failing') {
                $items[] = [
                    'kind' => 'failing',
                    'tone' => 'warn',
                    'code' => $source['key'],
                    'title' => $source['name'].' خطا می‌دهد',
                    'detail' => $source['consecutive_failures'].' خطای پیاپی؛ هنوز در چرخهٔ دریافت است.',
                    'until' => null,
                ];
            }
        }

        foreach ($quotes->where('is_stale', true) as $quote) {
            $items[] = [
                'kind' => 'stale',
                'tone' => 'stale',
                'code' => $quote['code'],
                'title' => $quote['name'].' کهنه است',
                'detail' => 'بازار باز است ولی از '.$quote['as_of_jalali'].' قیمت تازه‌ای نرسیده.',
                'until' => null,
            ];
        }

        $quoted = $quotes->pluck('code')->flip();

        foreach ($instruments as $instrument) {
            if (! $quoted->has($instrument->code)) {
                $items[] = [
                    'kind' => 'unquoted',
                    'tone' => 'info',
                    'code' => $instrument->code,
                    'title' => $instrument->name_fa.' هنوز قیمتی ندارد',
                    'detail' => 'هیچ منبعی تا حالا برای این ابزار قیمتی تحویل نداده است.',
                    'until' => null,
                ];
            }
        }

        return $items;
    }

    private function truncate(?string $error): ?string
    {
        if ($error === null || trim($error) === '') {
            return null;
        }

        $error = trim($error);

        return mb_strlen($error) > self::ERROR_LENGTH
            ? mb_substr($error, 0, self::ERROR_LENGTH).'…'
            : $error;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries;

use App\Modules\Common\Helpers\Jalali;
use App\Modules\Common\Helpers\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Gozaresh e mali ye MAHANE (mah e Jalali) az `wallet_transactions`.
 *
 * ── Chera mah e Jalali tu PHP va na `date_trunc('month')` e SQL ─────
 * Mah e shamsi ba mah e miladi ham-marz nist؛ `date_trunc` 12 satl e
 * miladi mide ke baraye karbar e Irani ma'ni nadare. Pas marz e har mah
 * (00:00 e Tehran e rooz e 1) inja hesab mishe va baraye HAR mah yek
 * query e aggregate zade mishe — hadaksar 24 query، hameshun index-dar ru
 * `created_at`.
 *
 * Mablagh ha RIAL-and (gharardad e kol e safhe ye mali).
 */
final readonly class FinanceMonthlyReportQuery
{
    /** Bazeh haye sari' — key => label e UI. */
    public const PERIODS = [
        'current' => 'ماه جاری',
        '3m' => '۳ ماه گذشته',
        '6m' => '۶ ماه گذشته',
        '12m' => '۱۲ ماه',
    ];

    public const DEFAULT_PERIOD = '6m';

    /** Saghf e tedad e mah tu bazeh ye dasti — har mah yek query ast. */
    private const MAX_MONTHS = 24;

    private const MONTHS_FA = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /**
     * Soton haye gozaresh: key => [label, reason, direction].
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const COLUMNS = [
        'topup' => ['شارژ درگاه', 'topup', 'credit'],
        'adminCredit' => ['شارژ مدیر', 'admin_adjust', 'credit'],
        'adminDebit' => ['کسر مدیر', 'admin_adjust', 'debit'],
        'planPurchase' => ['خرید اشتراک', 'plan_purchase', 'debit'],
        'apiCall' => ['مصرف API', 'api_call', 'debit'],
        'refund' => ['بازگشت', 'refund', 'credit'],
        'reversal' => ['ابطال', 'admin_reversal', '*'],
    ];

    /**
     * @param  array{period?: string|null, from?: string|null, to?: string|null}  $filters
     * @return array{
     *   period: string, custom: bool, rangeLabel: string, from: string, to: string,
     *   columns: array<string, string>,
     *   rows: list<array<string, mixed>>, totals: array<string, mixed>,
     * }
     */
    public function report(array $filters): array
    {
        [$period, $custom, $months] = $this->resolveMonths($filters);

        $rows = [];
        $totals = array_fill_keys([...array_keys(self::COLUMNS), 'credits', 'debits', 'net', 'count'], 0);

        foreach ($months as $month) {
            $row = $this->aggregate($month['start'], $month['end']);

            foreach ($totals as $key => $_) {
                $totals[$key] += $row[$key];
            }

            $rows[] = ['key' => $month['key'], 'label' => $month['label'], 'from' => $month['fromIso'], 'to' => $month['toIso']] + self::display($row);
        }

        $first = $months[0];
        $last = $months[count($months) - 1];

        return [
            'period' => $period,
            'custom' => $custom,
            'rangeLabel' => $first['label'] === $last['label'] ? $first['label'] : $first['label'].' – '.$last['label'],
            'from' => $first['fromIso'],
            'to' => $last['toIso'],
            'columns' => array_map(static fn (array $c): string => $c[0], self::COLUMNS),
            'rows' => $rows,
            'totals' => self::display($totals),
        ];
    }

    /** @return array<string, string> */
    public static function periods(): array
    {
        return self::PERIODS;
    }

    /**
     * @param  array{period?: string|null, from?: string|null, to?: string|null}  $filters
     * @return array{0: string, 1: bool, 2: list<array{key: string, label: string, start: CarbonImmutable, end: CarbonImmutable, fromIso: string, toIso: string}>}
     */
    private function resolveMonths(array $filters): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if (is_string($from) && $from !== '' && is_string($to) && $to !== '') {
            [$fy, $fm] = self::jalaliYearMonth($from);
            [$ty, $tm] = self::jalaliYearMonth($to);

            $months = [];
            [$y, $m] = [$fy, $fm];

            while (($y < $ty || ($y === $ty && $m <= $tm)) && count($months) < self::MAX_MONTHS) {
                $months[] = self::month($y, $m);
                [$y, $m] = $m === 12 ? [$y + 1, 1] : [$y, $m + 1];
            }

            return ['custom', true, $months === [] ? [self::month($fy, $fm)] : $months];
        }

        $period = is_string($filters['period'] ?? null) && isset(self::PERIODS[$filters['period']])
            ? $filters['period']
            : self::DEFAULT_PERIOD;

        $count = match ($period) {
            'current' => 1,
            '3m' => 3,
            '6m' => 6,
            default => 12,
        };

        [$y, $m] = self::jalaliYearMonth(CarbonImmutable::now()->setTimezone(Jalali::TIMEZONE)->format('Y-m-d'));
        $months = [];

        // Az ghadim be jadid، mah e jari akhar.
        for ($i = $count - 1; $i >= 0; $i--) {
            $back = $i;
            [$yy, $mm] = [$y, $m];

            while ($back-- > 0) {
                [$yy, $mm] = $mm === 1 ? [$yy - 1, 12] : [$yy, $mm - 1];
            }

            $months[] = self::month($yy, $mm);
        }

        return [$period, false, $months];
    }

    /** @return array{0: int, 1: int} */
    private static function jalaliYearMonth(string $iso): array
    {
        $jalali = Jalali::fromIso($iso) ?? Jalali::fromIso(CarbonImmutable::now()->format('Y-m-d'));
        [$jy, $jm] = explode('/', (string) $jalali);

        return [(int) $jy, (int) $jm];
    }

    /** @return array{key: string, label: string, start: CarbonImmutable, end: CarbonImmutable, fromIso: string, toIso: string} */
    private static function month(int $jy, int $jm): array
    {
        $days = Jalali::daysInMonth($jy, $jm);
        $fromIso = (string) Jalali::toIso(sprintf('%04d/%02d/01', $jy, $jm));
        $toIso = (string) Jalali::toIso(sprintf('%04d/%02d/%02d', $jy, $jm, $days));

        // Marz ha be vaght e Tehran؛ DB UTC ast.
        $start = CarbonImmutable::parse($fromIso, Jalali::TIMEZONE)->startOfDay()->utc();
        $end = CarbonImmutable::parse($toIso, Jalali::TIMEZONE)->addDay()->startOfDay()->utc();

        return [
            'key' => sprintf('%04d-%02d', $jy, $jm),
            'label' => self::MONTHS_FA[$jm - 1].' '.$jy,
            'start' => $start,
            'end' => $end,
            'fromIso' => $fromIso,
            'toIso' => $toIso,
        ];
    }

    /** @return array<string, int> */
    private function aggregate(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $selects = ['COUNT(*) as count'];
        $selects[] = "COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE 0 END), 0) as credits";
        $selects[] = "COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount_minor ELSE 0 END), 0) as debits";

        foreach (self::COLUMNS as $key => [, $reason, $direction]) {
            $condition = $direction === '*'
                ? "reason = '{$reason}'"
                : "reason = '{$reason}' AND direction = '{$direction}'";
            $selects[] = "COALESCE(SUM(CASE WHEN {$condition} THEN amount_minor ELSE 0 END), 0) as {$key}";
        }

        $row = DB::table('wallet_transactions')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw(implode(', ', $selects))
            ->first();

        $out = [];

        foreach ([...array_keys(self::COLUMNS), 'credits', 'debits', 'count'] as $key) {
            $out[$key] = (int) ($row->{$key} ?? 0);
        }

        $out['net'] = $out['credits'] - $out['debits'];

        return $out;
    }

    /**
     * Meghdar e kham + matn e namayesh (rial ba jodakonande).
     *
     * @param  array<string, int>  $row
     * @return array<string, mixed>
     */
    private static function display(array $row): array
    {
        $out = ['count' => $row['count'], 'netSign' => $row['net'] <=> 0];

        foreach ($row as $key => $value) {
            if ($key === 'count') {
                continue;
            }

            $out[$key] = $value;
            $out[$key.'Rial'] = Money::rialDisplay($value);
        }

        return $out;
    }
}

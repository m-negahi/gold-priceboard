<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;
use Throwable;

/**
 * Tarikh e Jalali baraye ensan ha — faghat dar labe ye NAMAYESH.
 *
 * ⚠️ Integration az noskhe ye ghabli (Desktop/priceboard، 2026-10-06) ba DO
 * taghir:
 *   1. namespace `Common\Support` -> `Common\Helpers` (sakhtar e in proje).
 *   2. `persianDigits` default esh `false` shod: policy e IN proje «raghame
 *      LATIN tu kol e site» ast (`LatinizeDigits` + `StringHelper`). Ba
 *      default e `true`، Jalali raghame Farsi tolid mikard va middleware
 *      dobare bar migardund — kar e bihude dar har do jahat.
 *
 * Database UTC zakhire mikone (architecture §8). Hich chizi inja meghdari bar nemigardoone ke be
 * storage bar garde, joz `parse()` ke hamun chizi-e ke date picker-e jalali post mikone va un
 * ham dobare yek lahze-ye UTC tahvil mide.
 */
final class Jalali
{
    public const TIMEZONE = 'Asia/Tehran';

    public static function date(?DateTimeInterface $at, bool $persianDigits = false): ?string
    {
        return self::format($at, 'Y/m/d', $persianDigits);
    }

    public static function dateTime(?DateTimeInterface $at, bool $persianDigits = false): ?string
    {
        return self::format($at, 'Y/m/d H:i', $persianDigits);
    }

    public static function format(?DateTimeInterface $at, string $format, bool $persianDigits = false): ?string
    {
        if ($at === null) {
            return null;
        }

        $local = CarbonImmutable::instance($at)->setTimezone(self::TIMEZONE);
        $formatted = Jalalian::fromDateTime($local)->format($format);

        return $persianDigits ? self::toPersianDigits($formatted) : $formatted;
    }

    /** `1405/07/03` ro (ba argham-e farsi ya latin) be yek lahze-ye UTC dar nime-shab-e Tehran parse mikone. */
    public static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = trim(str_replace(['-', '.'], '/', (string) StringHelper::numbersToEnglish($value)));

        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $normalized, $m) !== 1) {
            return null;
        }

        $canonical = sprintf('%04d/%02d/%02d', $m[1], $m[2], $m[3]);

        try {
            $jalali = Jalalian::fromFormat('Y/m/d', $canonical, new DateTimeZone(self::TIMEZONE));
        } catch (Throwable) {
            return null;
        }

        // Jalalian tarikh-e kharej az mahdoode ro bi-seda mighaltoone (mah-e 13 mishe Farvardin-e
        // ba'd, rooz-e 31-e yek mah-e 30 rooze mishe 1om), ke yek typo ro be tarikh-e vaghei vali
        // ghalat tabdil mikard. Raft-o-bargasht arzan-tarin rah-e rad kardan-e in-e.
        if ($jalali->format('Y/m/d') !== $canonical) {
            return null;
        }

        return CarbonImmutable::instance($jalali->toCarbon())
            ->setTimezone(self::TIMEZONE)
            ->startOfDay();
    }

    // ── Tarikh-e taghvimi-ye khalis ──────────────────────────────────────────────────────────
    // Gharardad-e `x-form.jalali-date`: field yek tarikh-e ISO-ye miladi (`2026-09-26`) tahvil
    // mide va migire — na sa'at, na timezone. In method ha hamun converter-i hastand ke
    // resources/js/alpine/jalali-date.js tu browser dare (algorithm-e Borkowski, hamun ke morilog
    // estefade mikone); tests/Fixtures/jalali-dates.json har do ro rooye yek fixture negah midare.

    /** `2026-09-26` → `1405/07/04`. Har chizi ke yek tarikh-e vaghei-ye ISO nabashe null mishe. */
    public static function fromIso(?string $iso, bool $persianDigits = false): ?string
    {
        if ($iso === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($iso), $m) !== 1) {
            return null;
        }

        [$gy, $gm, $gd] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        if (! checkdate($gm, $gd, $gy)) {
            return null;
        }

        [$jy, $jm, $jd] = CalendarUtils::toJalali($gy, $gm, $gd);
        $formatted = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

        return $persianDigits ? self::toPersianDigits($formatted) : $formatted;
    }

    /** `1405/07/04` (argham-e farsi ya latin, `/` ya `-`) → `2026-09-26`; rooz-e nadashte-ye mah null mishe. */
    public static function toIso(?string $jalali): ?string
    {
        if ($jalali === null) {
            return null;
        }

        $normalized = trim(str_replace('-', '/', (string) StringHelper::numbersToEnglish($jalali)));

        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $normalized, $m) !== 1) {
            return null;
        }

        [$jy, $jm, $jd] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > self::daysInMonth($jy, $jm)) {
            return null;
        }

        [$gy, $gm, $gd] = CalendarUtils::toGregorian($jy, $jm, $jd);

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    public static function isLeapYear(int $jy): bool
    {
        return CalendarUtils::isLeapJalaliYear($jy);
    }

    /** Shesh mah-e aval 31, panj mah-e ba'd 30, Esfand 29 — ya 30 dar sal-e kabise. */
    public static function daysInMonth(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }

        if ($jm <= 11) {
            return 30;
        }

        return self::isLeapYear($jy) ? 30 : 29;
    }

    public static function toPersianDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}

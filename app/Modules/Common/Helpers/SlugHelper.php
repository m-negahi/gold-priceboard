<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

use Normalizer;

/**
 * Slug-haye do-zabane. Horoof-e farsi negah dashte mishan — URL-e farsi baraye in mokhatab khoob
 * index mishe va browser ha shaffaf percent-encode-esh mikonand — dar hali ke latin horoof-kuchik
 * mishe, harf-haye latin-e alamat-dar be harf-e paye mishan (é → e) va har chiz-e dige mishe dash.
 *
 * Tartib sabet-e (docs/ARCHITECTURE.md §8 «Slugs»): harf-haye arabi → farsi va hazf-e harekat,
 * nim-fasele → fasele, argham → ASCII, horoof-kuchik, alamat-e negareshi-ye arabi → fasele,
 * transliteration-e latin (faghat rooye run-haye gheyr-e arabi), har chiz-e gheyr az
 * [\p{Arabic}a-z0-9] → dash, jam' kardan-e dash ha, borid-e 120 character rooye marz-e kalame.
 *
 * Editor be karbar ghol mide har chi type kone moghe-ye save *eslah* mishe na rad, pas in hich
 * vaght throw nemikone: title-e gheyr-e ghabel-e estefade string-e khali bar migardoone va
 * seda-zanande fallback ro tasmim migire. `SlugRule` faghat chizi ro rad mikone ke inja dorost nemishe.
 *
 * `resources/js/lib/slug.js` ayene-ye samt-e client-e in-e; `tests/Fixtures/slugs.json` har do ro
 * rooye yek fixture negah midare.
 */
final class SlugHelper
{
    public const MAX_LENGTH = 120;

    /**
     * Kalame-hayi ke yek route-e vaghei ya yek masir-e system-i ro mipooshoonand. Yek slug ke
     * daghighan yeki az in ha bashe hich vaght nabayad zakhire beshe: SlugRule rad mikone va
     * HasSlug un ro mesl-e barkhord mishmore (`about` → `about-2`). Har segment-e avval-e har route
     * bayad inja bashe — `ReservedRouteSegmentsTest` ba `Route::getRoutes()` check mikone.
     */
    public const RESERVED = [
        'admin', 'api', 'panel', 'login', 'logout', 'horizon', 'telescope', 'livewire', 'storage',
        'build', 'vendor', 'up', 'sitemap', 'sitemap.xml', 'robots.txt', 'new', 'create', 'edit', 'delete',
        'articles', 'feed', 'price', 'board', 'docs', 'services', 'pricing', 'about', 'contact', 'captcha',
        'sanctum', 'tools', 'market',
    ];

    /** Horoof-e farsi/arabi, horoof-e ASCII va argham mimunand; har chiz-e dige joda-konande mishe. */
    private const KEEP = '/[^\p{Arabic}a-z0-9]+/u';

    /**
     * ALAMAT-e negareshi-ye khat-e arabi tu PCRE2 zir-e \p{Arabic} (script extensions) miad, pas `،`
     * va `؟` az pass-e KEEP rad mishodand va sar az URL dar miavordand. Aval hazf mishan.
     */
    private const PUNCTUATION = '/[\x{060C}\x{060D}\x{061B}\x{061E}\x{061F}\x{066A}\x{066D}\x{06D4}\x{06DD}\x{06DE}\x{00AB}\x{00BB}]/u';

    /** Run-haye gheyr-e arabi — faghat in ha transliterate mishan, ta NFD «آ» ro be «ا» tajziye nakone. */
    private const NON_ARABIC_RUN = '/[^\p{Arabic}]+/u';

    /** Harf-hayi ke NFD tajziye nemikone vali yek shekl-e latin-e ja-oftade daran. */
    private const LIGATURES = [
        'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ø' => 'o', 'đ' => 'd', 'ł' => 'l', 'ð' => 'd', 'þ' => 'th',
    ];

    public static function make(string $title): string
    {
        // Integration-note (2026-10-06): noskhe ye ghabli `PersianText` va
        // `Digits` e jodagane dasht — inja hamun kar ba `StringHelper` e
        // mojud anjam mishe (YEK map e horoof/ragham tu kol e proje) va
        // faghat flatten e ZWNJ ke makhsus e slug ast dasti munde:
        // «می‌شود» bayad «mi-shavad» she، na yek kalame.
        $value = (string) StringHelper::normalize($title);
        $value = str_replace("\u{200C}", ' ', $value);
        $value = mb_strtolower($value);

        $value = (string) preg_replace(self::PUNCTUATION, ' ', $value);
        $value = (string) preg_replace_callback(self::NON_ARABIC_RUN, static fn (array $m): string => self::transliterate($m[0]), $value);
        $value = (string) preg_replace(self::KEEP, '-', $value);
        $value = trim((string) preg_replace('/-+/', '-', $value), '-');

        return self::truncate($value);
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(mb_strtolower(trim($slug)), self::RESERVED, true);
    }

    /** NFD + hazf-e mark ha: é → e, ü → u. Hamun kari ke `normalize('NFD')` tu JS mikone. */
    private static function transliterate(string $run): string
    {
        $run = strtr($run, self::LIGATURES);
        $decomposed = Normalizer::normalize($run, Normalizer::FORM_D);

        return (string) preg_replace('/\p{M}+/u', '', $decomposed === false ? $run : $decomposed);
    }

    private static function truncate(string $slug): string
    {
        if (mb_strlen($slug) <= self::MAX_LENGTH) {
            return $slug;
        }

        $cut = mb_substr($slug, 0, self::MAX_LENGTH);
        $lastDash = mb_strrpos($cut, '-');

        // Age marz-e kalame hast hamun ja bebor, ta slug hich vaght vasat-e kalame tamum nashe.
        return trim($lastDash === false ? $cut : mb_substr($cut, 0, $lastDash), '-');
    }
}

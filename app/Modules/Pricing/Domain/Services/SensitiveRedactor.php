<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Services;

/**
 * Redact e header / field e form / query ghabl az zakhire tu daftarche ye
 * upstream (P4-20).
 *
 * Daftarche tu panel neshun dade mishe. Provider haye fe'li (tgju/estjt)
 * secret nadaran، vali avvalin provider e platform-i ke API key mikhad
 * nabayad kelid esh ro tu Redis e dev va safhe ye panel bendaze — pas
 * redact az HALA، na vaghti ke nasht kard.
 *
 * Mahz (bedun e IO) — ba PHPUnit e khali test mishe.
 */
final class SensitiveRedactor
{
    public const MASK = '[redacted]';

    private const PATTERN = '/authorization|token|key|secret|cookie|password|signature/i';

    /**
     * @param  array<string, mixed>  $values  header (string ya list) ya field e form
     * @return array<string, string>
     */
    public static function map(array $values): array
    {
        $out = [];

        foreach ($values as $name => $value) {
            $name = (string) $name;

            if (preg_match(self::PATTERN, $name) === 1) {
                $out[$name] = self::MASK;

                continue;
            }

            $out[$name] = is_array($value)
                ? implode(', ', array_map(static fn ($v): string => (string) $v, $value))
                : (string) $value;
        }

        return $out;
    }

    /** Meghdar e param haye hassas e query string. */
    public static function url(string $url): string
    {
        $q = strpos($url, '?');

        if ($q === false) {
            return $url;
        }

        $fragment = '';
        $hash = strpos($url, '#', $q);

        if ($hash !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        $base = substr($url, 0, $q);
        $pairs = explode('&', substr($url, $q + 1));

        foreach ($pairs as $i => $pair) {
            [$name] = explode('=', $pair, 2);

            if (preg_match(self::PATTERN, urldecode($name)) === 1) {
                $pairs[$i] = $name.'='.rawurlencode(self::MASK);
            }
        }

        return $base.'?'.implode('&', $pairs).$fragment;
    }
}

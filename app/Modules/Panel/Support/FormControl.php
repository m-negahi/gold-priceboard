<?php

declare(strict_types=1);

namespace App\Modules\Panel\Support;

/**
 * Class ha va ghavanin-e moshtarak-e control-haye `x-form.*`, yek ja — ta input, textarea, select
 * va number yek zaban-e visual dashte bashan va id-e control ba `for`-e x-form.field hamishe
 * yeki bashe. Stateless-e; faghat string misaze.
 */
final class FormControl
{
    /** Alpine: aria-invalid faghat vaghti dakhel-e yek x-form.field-e ke khata dare; birun-e field null. */
    public const ARIA_INVALID = "typeof invalid === 'undefined' || !invalid ? null : 'true'";

    /** Alpine: id-e hint ya khata-ye field-e dor-e-sh, ya null. */
    public const ARIA_DESCRIBED_BY = "typeof describedBy === 'undefined' ? null : describedBy";

    private const SIZES = [
        'sm' => 'h-9 px-3 text-sm',
        'md' => 'h-11 px-3.5 text-sm',
        'lg' => 'h-13 px-4 text-base',
    ];

    /**
     * Halat-e khata az wrapper-e x-form.field miad (`group/field` + `data-invalid`); focus hamun
     * border-e brand + halo-ye safhe-ye login-e.
     */
    private const BASE = 'block w-full min-w-0 rounded-xl border border-line-3 bg-surface text-ink placeholder:text-muted-3 '
        .'transition-[border-color,box-shadow,background-color] duration-(--bp-dur-fast) '
        .'hover:border-muted-3 focus:border-brand focus:shadow-(--bp-elev-brand) focus:outline-none '
        .'read-only:bg-surface-2 read-only:hover:border-line-3 read-only:focus:border-line-3 read-only:focus:shadow-none '
        .'disabled:cursor-not-allowed disabled:bg-surface-2 disabled:text-muted-2 disabled:hover:border-line-3 '
        .'group-data-[invalid=true]/field:border-danger group-data-[invalid=true]/field:bg-danger-soft group-data-[invalid=true]/field:focus:shadow-none';

    /** `title` → `f-title`, `seo[title]` → `f-seo-title`; null vaghti esmi nist. */
    public static function id(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        return 'f-'.trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-');
    }

    /** Class-haye yek control-e yek-khati (input, number, select). */
    public static function classes(string $size = 'md', bool $ltr = false): string
    {
        return self::BASE.' '.(self::SIZES[$size] ?? self::SIZES['md']).($ltr ? ' ltr text-left' : '');
    }

    /** Class-haye control-e chand-khati (textarea): height-e sabet nadare. */
    public static function multilineClasses(bool $ltr = false): string
    {
        return self::BASE.' px-3.5 py-2.5 text-sm leading-(--bp-leading-snug)'.($ltr ? ' ltr text-left' : '');
    }
}

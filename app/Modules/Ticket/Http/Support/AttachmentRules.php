<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Http\Support;

use Illuminate\Http\UploadedFile;

/**
 * Ghanun e validation e peyvast (P7-02) — YEK ja baraye har form e ticket
 * (javab e karbar/admin، ticket e jadid، onboarding).
 *
 * `mimes` pasvand ro az MOHTAVA hads mizane va `mimetypes` mime e finfo ro
 * check mikone — har do lazem-and ta esm e file be tanhayi kafi nabashe.
 */
final class AttachmentRules
{
    public const MAX_FILES = 5;

    public const MAX_KB = 5120;

    public const MAX_TOTAL_KB = 15360;

    /**
     * Payam e Farsi — proje file e `lang/fa` nadare va payam e pish-farz e
     * Laravel English ast (karbar «files.0» mibine).
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'files.required' => 'حداقل یک مدرک بارگذاری کنید.',
            'files.array' => 'فایل‌ها معتبر نیستند.',
            'files.max' => 'حداکثر '.self::MAX_FILES.' فایل مجاز است.',
            'files.*.file' => 'بارگذاری فایل ناموفق بود.',
            'files.*.mimes' => 'نوع فایل مجاز نیست (فقط PDF، JPG، PNG، WEBP).',
            'files.*.mimetypes' => 'نوع فایل مجاز نیست (فقط PDF، JPG، PNG، WEBP).',
            'files.*.max' => 'حجم هر فایل حداکثر '.(self::MAX_KB / 1024).' مگابایت است.',
        ];
    }

    /** @return array<string, list<string|\Closure>> */
    public static function rules(bool $required = false): array
    {
        return [
            'files' => [$required ? 'required' : 'nullable', 'array', 'max:'.self::MAX_FILES, static function (string $attribute, mixed $files, \Closure $fail): void {
                if (is_array($files) && array_sum(array_map(static fn ($file): int => $file instanceof UploadedFile ? (int) $file->getSize() : 0, $files)) > self::MAX_TOTAL_KB * 1024) {
                    $fail('مجموع حجم فایل‌ها حداکثر 15 مگابایت است.');
                }
            }],
            'files.*' => [
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
                'max:'.self::MAX_KB,
            ],
        ];
    }
}

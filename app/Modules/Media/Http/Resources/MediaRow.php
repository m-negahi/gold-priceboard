<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Resources;

use App\Modules\Common\Helpers\Jalali;
use App\Modules\Media\Application\Actions\UploadMediaAction;
use App\Modules\Media\Infrastructure\Persistence\Models\Media;

/**
 * Yek radif-e رسانه be shekl-e JSON-e panel — yek shekl baraye jadval, picker, javab-e upload/virayesh
 * va `featured_media`-e editor-e safhe/maghale, ta modal-e virayesh ba hamun radif kar kone.
 */
final readonly class MediaRow
{
    /** @return array<string, mixed> */
    public static function from(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'alt' => $media->alt,
            'title' => $media->title,
            'mime' => $media->mime,
            'kind' => $media->isSvg() ? 'برداری' : strtoupper((string) (UploadMediaAction::ALLOWED[$media->mime] ?? '')),
            'size' => $media->size,
            'size_human' => self::humanSize($media->size),
            'width' => $media->width,
            'height' => $media->height,
            'dimensions' => $media->width !== null && $media->height !== null
                ? $media->width.'×'.$media->height
                : ($media->isSvg() ? 'برداری' : '—'),
            'url' => $media->url(),
            'thumb_url' => $media->thumbUrl(),
            'webp_url' => $media->webpUrl(),
            'srcset' => $media->srcset(),
            // `usages_count` faghat vaghti hast ke Query ba withCount khunde bashe; vagarna sefr.
            'usages_count' => (int) ($media->usages_count ?? 0),
            'created_at' => Jalali::dateTime($media->created_at),
        ];
    }

    private static function humanSize(int $bytes): string
    {
        $value = $bytes >= 1024 * 1024
            ? number_format($bytes / (1024 * 1024), 1).' مگابایت'
            : number_format(max(1, (int) round($bytes / 1024))).' کیلوبایت';

        return $value;
    }
}

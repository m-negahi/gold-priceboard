<?php

declare(strict_types=1);

namespace App\Modules\Media\Actions;

use App\Modules\Common\Actions\BaseAction;
use App\Modules\Media\Dto\MediaDto;
use App\Modules\Media\Models\Media;

/**
 * Faghat matn-ha (alt/title) avaz mishan; file va masir hich vaght. Form hamishe har do ro
 * miferste, pas title-e null inja yani «pak kon» — na «dast nazan».
 *
 * @extends BaseAction<Media>
 */
final readonly class UpdateMediaAction extends BaseAction
{
    protected function model(): string
    {
        return Media::class;
    }

    public function handle(Media $media, MediaDto $dto): Media
    {
        $media->forceFill([
            'alt' => trim((string) ($dto->alt ?? $media->alt)),
            'title' => $dto->title === null || trim($dto->title) === '' ? null : trim($dto->title),
        ])->save();

        return $media;
    }
}

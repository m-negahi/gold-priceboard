<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Actions;

use App\Modules\Common\Actions\BaseAction;
use App\Modules\Common\Helpers\SvgSanitizer;
use App\Modules\Media\Application\Dto\UploadMediaDto;
use App\Modules\Media\Domain\Exceptions\UnsupportedMediaException;
use App\Modules\Media\Infrastructure\Persistence\Models\Media;
use App\Modules\Media\Infrastructure\Support\ImageProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Tanha rah-e vorood-e yek file be ketabkhane. Mime az fileinfo miad na az pasvand; file zir-e
 * `media/YYYY/MM/<ulid>.<ext>` rooye disk-e `public` mishine (ADR-0037) va baraye raster ha
 * WebP + thumbnail + srcset ham hamun ja sakhte mishe. Age moshtagh-sazi shekast bekhore, asl
 * mimune va `derivatives` null-e — yek tasvir-e bi-thumbnail behtar az yek upload-e shekast-khorde-ast.
 *
 * @extends BaseAction<Media>
 */
final readonly class UploadMediaAction extends BaseAction
{
    public const DISK = 'public';

    public const MAX_BYTES = 5 * 1024 * 1024;

    /** @var array<string, string> mime → pasvand-e zakhire */
    public const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    public function __construct(private FilesystemFactory $filesystem) {}

    protected function model(): string
    {
        return Media::class;
    }

    public function handle(UploadMediaDto $dto): Media
    {
        $mime = (string) $dto->file->getMimeType();

        if (! isset(self::ALLOWED[$mime])) {
            throw UnsupportedMediaException::mime($mime);
        }

        $disk = $this->filesystem->disk(self::DISK);
        $directory = 'media/'.CarbonImmutable::now()->format('Y/m');
        $base = strtolower((string) Str::ulid());
        $relative = "{$directory}/{$base}.".self::ALLOWED[$mime];

        if ($mime === 'image/svg+xml') {
            // SVG rooye hamin origin serve mishe: script/handler/href-e kharji ghabl az zakhire pak mishe.
            try {
                $written = $disk->put($relative, SvgSanitizer::clean((string) file_get_contents((string) $dto->file->getRealPath())));
            } catch (InvalidArgumentException) {
                throw UnsupportedMediaException::mime($mime);
            }
        } else {
            $written = $disk->putFileAs($directory, $dto->file, "{$base}.".self::ALLOWED[$mime]);
        }
        if ($written === false) {
            throw new RuntimeException('File ru disk zakhire nashod.');
        }

        $derivedPaths = array_merge(["{$directory}/{$base}.webp"], array_map(
            static fn (int $width): string => "{$directory}/{$base}-{$width}.webp",
            ImageProcessor::SRCSET_WIDTHS,
        ));
        $absolute = $disk->path($relative);
        $dimensions = ImageProcessor::dimensions($absolute, $mime);

        $derivatives = null;

        if (in_array($mime, ImageProcessor::RASTER_MIMES, true)) {
            try {
                $derivatives = ImageProcessor::derivatives($absolute, $mime, $disk->path("{$directory}/{$base}"), "{$directory}/{$base}");
            } catch (Throwable $e) {
                $disk->delete(array_values(array_diff($derivedPaths, [$relative])));
                report($e);
            }
        }

        $media = new Media([
            'disk' => self::DISK,
            'path' => $relative,
            'name' => Str::limit((string) $dto->file->getClientOriginalName(), 200, ''),
            'mime' => $mime,
            'size' => $disk->size($relative),
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'alt' => trim($dto->alt),
            'title' => $dto->title === null || trim($dto->title) === '' ? null : trim($dto->title),
            'derivatives' => $derivatives,
            'uploaded_by' => $dto->uploadedBy,
        ]);

        try {
            $media->save();
        } catch (Throwable $exception) {
            $disk->delete(array_values(array_unique([$relative, ...$derivedPaths])));
            throw $exception;
        }

        return $media;
    }
}

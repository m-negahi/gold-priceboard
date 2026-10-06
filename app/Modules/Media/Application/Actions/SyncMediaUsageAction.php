<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Actions;

use App\Modules\Common\Actions\BaseAction;
use App\Modules\Media\Infrastructure\Persistence\Models\Media;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaUsage;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * «Koja estefade shode» ro baraye yek model bazneveshtan: har field-i ke media id (ya list-e id)
 * dare, radif-haye ghabli-e hamun (model, field) pak va jadid ha sabt mishan. Body-e HTML ham az
 * rooye `src`-haye `<img>` be id tabdil mishe (`idsInHtml`) ta tasvir-e dakhel-e matn ham
 * shomorde beshe. Saheb-e model in ro tu hamun transaction-e save-esh seda mizane.
 *
 * @extends BaseAction<MediaUsage>
 */
final readonly class SyncMediaUsageAction extends BaseAction
{
    public function __construct(private FilesystemFactory $filesystem) {}

    protected function model(): string
    {
        return MediaUsage::class;
    }

    /**
     * @param  array<string, int|list<int>|null>  $fields  field → media id ya list-e id
     */
    public function handle(Model $owner, array $fields): void
    {
        foreach ($fields as $field => $ids) {
            $ids = array_values(array_unique(array_filter(array_map(intval(...), (array) $ids))));

            MediaUsage::query()
                ->where('usable_type', $owner->getMorphClass())
                ->where('usable_id', $owner->getKey())
                ->where('field', $field)
                ->whereNotIn('media_id', $ids)
                ->delete();

            foreach ($ids as $id) {
                MediaUsage::query()->firstOrCreate([
                    'media_id' => $id,
                    'usable_type' => $owner->getMorphClass(),
                    'usable_id' => $owner->getKey(),
                    'field' => $field,
                ]);
            }
        }
    }

    /** Hame-ye radif-haye yek model (moghe-ye hazf-e saheb). */
    public function forget(Model $owner): void
    {
        MediaUsage::query()
            ->where('usable_type', $owner->getMorphClass())
            ->where('usable_id', $owner->getKey())
            ->delete();
    }

    /**
     * Id-e rasane-hayi ke tu HTML ba `<img src="…">` amadand — src-e mutlagh ya nesbi be URL-e
     * disk-e public; tasvir-e kharej az ketabkhane nadide gerefte mishe.
     *
     * @return list<int>
     */
    public function idsInHtml(?string $html): array
    {
        if ($html === null || ! str_contains($html, '<img')) {
            return [];
        }

        preg_match_all('/<img\b[^>]*\bsrc="([^"]+)"/i', $html, $matches);

        $base = rtrim($this->filesystem->disk(UploadMediaAction::DISK)->url(''), '/');
        $basePath = (string) parse_url($base, PHP_URL_PATH);
        $paths = [];

        foreach ($matches[1] as $src) {
            $src = html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $path = str_starts_with($src, $base) ? substr($src, strlen($base)) : ((string) parse_url($src, PHP_URL_PATH));

            if ($basePath !== '' && str_starts_with($path, $basePath.'/')) {
                $path = substr($path, strlen($basePath));
            }

            $path = ltrim($path, '/');

            if ($path !== '') {
                // Moshtagh (`-400.webp`, `.webp`) ham be asl-esh bar migarde.
                $paths[] = (string) preg_replace('/(-\d{3,4})?\.webp$/', '', $path);
                $paths[] = $path;
            }
        }

        if ($paths === []) {
            return [];
        }

        // Faghat radif-hayi ke yeki az in masir ha (ya stem-eshun ba har pasvandi) ro daran — na kol-e jadval.
        $rows = Media::query()->where(function ($query) use ($paths): void {
            foreach (array_unique($paths) as $path) {
                $query->orWhere('path', $path)->orWhere('path', 'like', str_replace(['%', '_'], ['\\%', '\\_'], $path).'.%');
            }
        })->get(['id', 'path', 'derivatives']);

        $ids = [];

        foreach ($rows as $media) {
            $own = $media->allPaths();
            $stem = (string) preg_replace('/\.[a-z]+$/', '', $media->path);

            foreach ($paths as $path) {
                if (in_array($path, $own, true) || $path === $stem) {
                    $ids[] = $media->id;
                    break;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}

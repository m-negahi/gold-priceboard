<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Actions;

use App\Modules\Common\Actions\BaseAction;
use App\Modules\Media\Domain\Exceptions\MediaInUseException;
use App\Modules\Media\Infrastructure\Persistence\Models\Media;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

/**
 * Hazf-e rasane: radif-e media dakhel-e hamun transaction-e hazf `lockForUpdate` mishe va
 * `media_usages` az no shomorde mishe — age jayi estefade shode rad mishe, chon yek `<img>`-e
 * shekaste rooye safhe-ye zende badtar az yek radif-e ezafi tu ketabkhane-ast. In tarz jolo-ye
 * race-i ro migire ke tush yek estefade bein-e check-e biroon-e transaction va commit sabt she
 * va az ghalam biofte. Radif tu transaction pak mishe va file ha faghat ba'd az commit; file-e
 * ja-monde faghat ja migire, radif-e bi-file shekaste-ast.
 *
 * @extends BaseAction<Media>
 */
final readonly class DeleteMediaAction extends BaseAction
{
    public function __construct(private FilesystemFactory $filesystem) {}

    protected function model(): string
    {
        return Media::class;
    }

    public function handle(Media $media): void
    {
        /** @var array{disk: string, paths: list<string>} $target */
        $target = $this->transaction(static function () use ($media): array {
            /** @var Media $locked */
            $locked = Media::query()->whereKey($media->getKey())->lockForUpdate()->firstOrFail();

            // Shomaresh bayad tu hamun transaction, ba'd az lock anjam beshe: vagarna yek
            // estefade-ye hamzaman ke bein-e check-e biroon va commit sabt mishe az ghalam miofte.
            $used = $locked->usages()->count();

            if ($used > 0) {
                throw MediaInUseException::usedIn($used);
            }

            $paths = $locked->allPaths();
            $disk = $locked->disk;

            $locked->delete();

            return ['disk' => $disk, 'paths' => $paths];
        });

        $this->filesystem->disk($target['disk'])->delete($target['paths']);
    }
}

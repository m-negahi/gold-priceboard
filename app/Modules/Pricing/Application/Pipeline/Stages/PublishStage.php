<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Pipeline\Stages;

use App\Modules\Pricing\Application\Pipeline\Stage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Domain\Enums\StagePolicy;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use Throwable;

/**
 * Akharin marhale: gharar dadan e tablo tu dastres e reader ha.
 *
 * Fe'lan faghat version ro mikhune (ke `putBoard` ghablan bump karde) ta
 * `TickReportDto` betune un ro gozaresh kone. Vaghti `/api/board.json` va
 * payload e SSR sakhte shan (Phase 5)، garm kardan e un cache ham inja miad.
 *
 * Amdan Stage e jodagane-ast: «neveshtan» va «montasher kardan» do kar e
 * motefavet-and va shekast e dovomi nabayad avvali ro batel kone.
 */
final readonly class PublishStage implements Stage
{
    public function __construct(private HotCachePort $cache) {}

    public function name(): string
    {
        return 'publish';
    }

    public function policy(): StagePolicy
    {
        return StagePolicy::Isolated;
    }

    public function handle(TickContext $context): void
    {
        try {
            $this->cache->boardVersion();
        } catch (Throwable) {
            // Redis ghat' ast — ghablan tu `PersistStage` sabt shode.
        }
    }
}

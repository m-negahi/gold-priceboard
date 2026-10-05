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
 * Do kar:
 *   1. Version ro mikhune (ke `putBoard` ghablan bump karde) ta
 *      `TickReportDto` betune un ro gozaresh kone.
 *   2. 🔴 **Zarabān** mineviseh — «nevisande zende ast va write esh gereft».
 *
 * ── Chera zarabān INJA va na tu `PersistStage` ──────────────────────
 * `PersistStage::handle()` vaghti hich snapshot va hich hold nabashe FORI
 * bar migarde. Un daghighan halat e SHABANE-ast: bazaar baste، hame ye
 * quote ha `stale` rad mishan. Agar zarabān unja bud، har shab motevaghef
 * mishod va `BoardQuery` eshtebahan «tablo yakh zade» e'lam mikard.
 *
 * In Stage hamishe ejra mishe، pas zarabān hamishe be-rooz mishe — magar
 * inke write e Redis VAGHE'AN shekast bokhore (ke hamun chizi-e ke
 * mikhahim kashf konim).
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

            // Zarabān KHODESH yek write ast، pas tu halat e OOM
            // (`noeviction` + memory e por) be-rooz nemishe — va hamin
            // chizi-e ke `BoardQuery` baraye kashf e yakh-zadegi mikhune.
            $this->cache->putHeartbeat($context->at->timestamp());
        } catch (Throwable) {
            // Redis ghat' ast — ghablan tu `PersistStage` sabt shode.
        }
    }
}

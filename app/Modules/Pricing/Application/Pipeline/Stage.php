<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Pipeline;

use App\Modules\Pricing\Domain\Enums\StagePolicy;

/**
 * Yek marhale ye pipeline.
 *
 * Har Stage policy esh ro E'LAM mikone va `TickPipeline` e'mal esh mikone —
 * jaye inke har marhale khodesh try/catch bezare va tasmim begire. Natije:
 * baraye fahmidan e «agar X bimofte chi mishe» faghat yek jadval ro mikhuni،
 * na 150 khat code.
 */
interface Stage
{
    public function name(): string;

    public function policy(): StagePolicy;

    public function handle(TickContext $context): void;
}

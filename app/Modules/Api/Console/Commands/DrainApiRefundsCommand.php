<?php

declare(strict_types=1);

namespace App\Modules\Api\Console\Commands;

use App\Modules\Api\Application\Actions\ChargeApiCallAction;
use Illuminate\Console\Command;

/**
 * Replay e refund haye API ke moghe ye ghat'i ye PG tu saf e Redis mundan
 * (compensation e paydar — checkpoint e 2026-10-06). Command NAZOK:
 * faghat Action ro seda mizane.
 */
final class DrainApiRefundsCommand extends Command
{
    protected $signature = 'api:refunds:drain {--max=500 : Saghf e entry dar yek ejra}';

    protected $description = 'Replay e refund haye API e mande dar saf (ba\'d az ghat\'i ye DB)';

    public function handle(ChargeApiCallAction $charger): int
    {
        $result = $charger->drainPendingRefunds(max(1, (int) $this->option('max')));

        if ($result['drained'] === 0 && $result['requeued'] === 0) {
            $this->info('Saf e refund khali bud.');

            return self::SUCCESS;
        }

        $this->info("Refund haye replay-shode: {$result['drained']}؛ bargashti be saf: {$result['requeued']}.");

        // Requeue ya'ni DB hanuz ghat' e — exit e gheyr-sefr ta monitoring bebine.
        return $result['requeued'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

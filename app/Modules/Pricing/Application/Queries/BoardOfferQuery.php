<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\SymbolOfferDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\ValueObjects\Price;

/**
 * Kamtarin/bishtarin e har symbol baraye JSON-LD e safhe ye asli (P5-05).
 *
 * 🔴 Daghighan HAMUN radif haye `PublicBoardQuery` (provider haye fa'al،
 * `sell` e mosbat، spread e'mal-shode ru read) — Google mige data ye
 * structured bayad ba chizi ke karbar ru safhe mibine yeki bashe. Farghesh
 * faghat inke `Price` mimune (na float) ta rial e DAGHIGH tu JSON-LD bere.
 */
final readonly class BoardOfferQuery
{
    public function __construct(
        private BoardQuery $board,
        private ProviderQuery $providers,
    ) {}

    /** @return list<SymbolOfferDto> */
    public function offers(): array
    {
        $board = $this->board->board($this->providers->allActive());
        $offers = [];

        foreach ($board->bySymbol() as $symbol => $rows) {
            $instrument = Instrument::tryFrom((string) $symbol);

            if ($instrument === null) {
                continue;
            }

            $low = null;
            $high = null;
            $count = 0;

            foreach ($rows as $row) {
                if (! $row->sell->isPositive()) {
                    continue;
                }

                $low = $low === null ? $row->sell : Price::min($low, $row->sell);
                $high = $high === null ? $row->sell : Price::max($high, $row->sell);
                $count++;
            }

            if ($low !== null && $high !== null) {
                $offers[] = new SymbolOfferDto($instrument, $low, $high, $count);
            }
        }

        return $offers;
    }
}

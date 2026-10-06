<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Http\Controllers;

use App\Modules\Pricing\Application\Queries\InstrumentPageQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Seo\Application\Queries\InstrumentSeoQuery;
use Illuminate\View\View;

/**
 * Safhe ye omumi ye yek instrument (`/price/{symbol}`، P10-13) — SSR
 * baraye SEO؛ chart va poll e zende ba Alpine ru hamun endpoint haye omumi
 * (`/candles.json`، `/board.json`).
 */
final readonly class InstrumentPageController
{
    public function __construct(
        private InstrumentPageQuery $page,
        private InstrumentSeoQuery $seo,
    ) {}

    public function show(string $symbol): View
    {
        $instrument = Instrument::tryFrom($symbol);

        abort_if($instrument === null, 404);

        return view('public.instrument', [
            'page' => $this->page->present($instrument),
            'seo' => $this->seo->present($instrument),
        ]);
    }
}

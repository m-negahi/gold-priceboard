<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Pricing\Application\Actions\SymbolPriorityAction;
use App\Modules\Pricing\Application\Queries\SymbolPriorityQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * «اولویت منابع» per-symbol (P5-07) — nazok، faghat Query/Action.
 *
 * Save JSON bar migardune (hamun olgu ye safhe ye gheymat e dasti: AJAX،
 * hich reload/scroll — khaste ye sahebe proje).
 */
final readonly class PriorityController
{
    public function __construct(
        private SymbolPriorityQuery $priorities,
        private SymbolPriorityAction $action,
    ) {}

    public function index(): View
    {
        return view('panel::priorities.index', [
            'rows' => $this->priorities->panelRows(),
            'names' => $this->priorities->providerNames(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'providers' => ['present', 'array', 'max:20'],
            'providers.*' => ['string', 'max:32'],
        ]);

        $instrument = Instrument::tryFrom((string) $data['symbol']);

        if ($instrument === null) {
            return new JsonResponse(['message' => 'نماد ناشناخته است.'], 422);
        }

        try {
            $this->action->set($instrument, array_values(array_map('strval', $data['providers'])));
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'rows' => $this->priorities->panelRows(),
            'toast' => [
                'kind' => 'success',
                'title' => 'انجام شد',
                'message' => $data['providers'] === []
                    ? $instrument->label().' به ترتیب پیش‌فرض برگشت.'
                    : 'اولویت '.$instrument->label().' ذخیره شد.',
            ],
        ]);
    }
}

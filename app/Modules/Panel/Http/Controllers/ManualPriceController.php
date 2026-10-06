<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Pricing\Application\Actions\ManualPriceAction;
use App\Modules\Pricing\Application\Queries\EffectivePriceQuery;
use App\Modules\Pricing\Application\Queries\ManualPriceQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Gheymat haye DASTI (fallback) — panel e admin (Phase 4/5).
 *
 * ⚠️ update/seed/remove JSON bar migardunan (safhe ba Alpine be-rooz
 * mishe — hich reload/scroll، khaste ye sahebe proje). Progressive: age
 * expectsJson nabud، redirect e classic.
 */
final readonly class ManualPriceController
{
    public function __construct(
        private ManualPriceQuery $manual,
        private ManualPriceAction $action,
        private ProviderQuery $providers,
        private EffectivePriceQuery $effective,
    ) {}

    public function index(): View
    {
        return view('panel::manual.index', $this->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'value' => ['required', 'numeric', 'regex:/^\d+(?:\.\d+)?$/D'],
        ]);

        $instrument = Instrument::tryFrom((string) $data['symbol']);

        if ($instrument === null) {
            return new JsonResponse(['message' => 'نماد ناشناخته است.'], 422);
        }

        try {
            $this->action->setFromDisplay($instrument, (string) $data['value'], $this->editor($request));
        } catch (InvalidArgumentException) {
            return new JsonResponse(['message' => 'قیمت باید مثبت و در محدوده مجاز باشد.'], 422);
        }

        return $this->json($instrument->label().' به‌روز شد.');
    }

    public function seed(Request $request): JsonResponse
    {
        $data = $request->validate(['provider' => ['required', 'string', 'max:32']]);

        $count = $this->action->seedFromProvider((string) $data['provider'], $this->editor($request));

        return $this->json(
            $count > 0 ? "{$count} نماد از منبع انتخابی کپی شد." : 'آن منبع هنوز قیمتی ندارد.',
            $count > 0 ? 'success' : 'warning',
        );
    }

    /**
     * Seed bar asas e OLAVIAT e per-symbol (P5-07): har symbol az avvalin
     * manba' e TAZE ye zanjire ye khodesh («aval tgju، age khata dad estjt»).
     */
    public function seedByPriority(Request $request): JsonResponse
    {
        $rows = $this->effective->freshestByPriority();
        $count = $this->action->seedFromRows($rows, $this->editor($request));
        $missing = count(Instrument::cases()) - $count;

        return $this->json(
            match (true) {
                $count === 0 => 'هیچ منبعی قیمت تازه ندارد — قیمت‌های دستی دست نخورد.',
                $missing > 0 => "{$count} نماد بر اساس اولویت پر شد؛ {$missing} نماد منبع تازه نداشت و دست نخورد.",
                default => "{$count} نماد بر اساس اولویت پر شد.",
            },
            $count > 0 ? 'success' : 'warning',
        );
    }

    public function remove(Request $request): JsonResponse
    {
        $instrument = Instrument::tryFrom((string) $request->string('symbol'));

        if ($instrument !== null) {
            $this->action->remove($instrument);
        }

        return $this->json('قیمت دستی برداشته شد.');
    }

    /** Payload e moshtarak: rows + source ha + vaziat e feed e mo'asser. */
    private function payload(): array
    {
        $seedSources = [];

        foreach ($this->providers->panelRows() as $row) {
            if ($row['key'] !== 'manual' && ! $row['deleted']) {
                $seedSources[$row['key']] = $row['name'];
            }
        }

        $effective = $this->effective->feed();

        return [
            'rows' => $this->manual->rows(),
            'seedSources' => $seedSources,
            'effective' => [
                'alarm' => $effective['alarm'],
                'fallbackCount' => $effective['fallbackCount'],
            ],
        ];
    }

    private function json(string $message, string $kind = 'success'): JsonResponse
    {
        $payload = $this->payload();

        return new JsonResponse([
            'rows' => $payload['rows'],
            'effective' => $payload['effective'],
            'toast' => ['kind' => $kind, 'title' => $kind === 'success' ? 'انجام شد' : 'توجه', 'message' => $message],
        ]);
    }

    private function editor(Request $request): ?string
    {
        $user = $request->user();

        return $user?->name ?: $user?->phone;
    }
}

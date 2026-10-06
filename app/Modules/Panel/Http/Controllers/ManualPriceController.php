<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Pricing\Application\Actions\ExportManualPricesAction;
use App\Modules\Pricing\Application\Actions\ImportManualPricesAction;
use App\Modules\Pricing\Application\Actions\ManualPriceAction;
use App\Modules\Pricing\Application\Queries\EffectivePriceQuery;
use App\Modules\Pricing\Application\Queries\ManualPriceImportQuery;
use App\Modules\Pricing\Application\Queries\ManualPriceQuery;
use App\Modules\Pricing\Application\Queries\PricingSettingsQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Virayesh/import e gheymat e dasti dar safhe ye olaviat.
 * Mutation ha JSON midan ta Alpine safhe ro bedun reload be-rooz kone.
 */
final readonly class ManualPriceController
{
    public function __construct(
        private ManualPriceQuery $manual,
        private ManualPriceAction $action,
        private EffectivePriceQuery $effective,
        private ManualPriceImportQuery $import,
        private ImportManualPricesAction $importAction,
        private ExportManualPricesAction $export,
        private PricingSettingsQuery $settings,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('panel.priorities.index');
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
            try {
                $this->action->remove($instrument);
            } catch (InvalidArgumentException $exception) {
                return new JsonResponse(['message' => $exception->getMessage()], 422);
            }
        }

        return $this->json('قیمت دستی برداشته شد.');
    }

    private function json(string $message, string $kind = 'success'): JsonResponse
    {
        $payload = $this->settings->present();

        return new JsonResponse([
            'rows' => $this->manual->rows(),
            'unifiedRows' => $payload['rows'],
            'effective' => $payload['effective'],
            'toast' => ['kind' => $kind, 'title' => $kind === 'success' ? 'انجام شد' : 'توجه', 'message' => $message],
        ]);
    }

    private function editor(Request $request): ?string
    {
        $user = $request->user();

        return $user?->name ?: $user?->phone;
    }

    public function template(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'manual-prices-');

        if ($path === false) {
            abort(503);
        }

        try {
            $this->export->execute($path);
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception;
        }

        return response()->download($path, 'manual-prices.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    public function preview(Request $request): JsonResponse
    {
        $request->session()->forget('pricing.manual_import');
        $request->validate(['workbook' => ['required', 'file', 'max:2048', 'extensions:xlsx']]);

        try {
            $preview = $this->import->preview($request->file('workbook')->getRealPath());
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        $token = Str::random(40);
        $request->session()->put('pricing.manual_import', ['token' => $token, 'preview' => $preview, 'expires' => time() + 900]);

        return new JsonResponse(['preview' => $preview, 'token' => $token]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:40']]);
        $pending = $request->session()->get('pricing.manual_import');

        if (! is_array($pending) || ! hash_equals((string) ($pending['token'] ?? ''), $data['token']) || ($pending['expires'] ?? 0) < time()) {
            return new JsonResponse(['message' => 'پیش‌نمایش منقضی شده است؛ فایل را دوباره انتخاب کنید.'], 422);
        }

        if ($pending['preview']['hasErrors'] || $pending['preview']['count'] === 0) {
            return new JsonResponse(['message' => 'فایل دارای خطا یا بدون قیمت است؛ ابتدا آن را اصلاح کنید.'], 422);
        }

        try {
            $count = $this->importAction->confirm($pending['preview']['rows'], $this->editor($request));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        $request->session()->forget('pricing.manual_import');

        return $this->json("{$count} قیمت دستی از اکسل ثبت شد.");
    }
}

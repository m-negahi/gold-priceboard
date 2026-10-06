<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Safhe-haye "kit": har primitive-e panel ba yek demo-ye zende (docs/frontend/admin-panel.md §6–§8, §26).
 *
 * Faghat local/testing — gate-esh middleware-e `local-only` rooye group-e route-e, na in class;
 * tu production 404-e, na 403. `request` gharardad-e panelRequest ro tamrin mikone (2xx / 422 /
 * 403 / 500 / toast-e sefareshi), `flash` masir-e redirect + `session('toast')` ro, `date` va
 * `slug` namayeshgah-e do field-e tarkibi-and (Playwright roosheshun test mikone).
 */
final readonly class KitController
{
    public function index(): View
    {
        // View ha tu `resources/views/kit/`-and (global)، na namespace e panel.
        return view('kit.index');
    }

    /** Namayeshgah-e `x-form.jalali-date`: min/max, disabled, meghdar-e bind shode, khorooji-ye ISO. */
    public function date(): View
    {
        return view('kit.date');
    }

    /** Namayeshgah-e `x-form.slug` bedoone form-e vaghei. */
    public function slug(): View
    {
        return view('kit.slug');
    }

    /** Har `outcome` yek shakhe-ye gharardad-e panelRequest-e; front hamun ro toast mikone. */
    public function request(Request $request): JsonResponse
    {
        return match ((string) $request->string('outcome')) {
            'ok' => new JsonResponse(['message' => 'تغییرات ذخیره شد.']),
            'toast' => new JsonResponse([
                'saved' => true,
                'toast' => ['kind' => 'info', 'title' => 'توست سفارشی سرور', 'message' => 'همراه داده‌ی پاسخ آمد.'],
            ]),
            'invalid' => throw ValidationException::withMessages([
                'title' => 'عنوان الزامی است.',
                'slug' => 'شناسه تکراری است.',
            ]),
            'forbidden' => new JsonResponse(['message' => 'شما اجازه‌ی ویرایش این مورد را ندارید.'], 403),
            'error' => new JsonResponse(['message' => 'پردازش با خطا متوقف شد.'], 500),
            default => new JsonResponse(['message' => 'حالت ناشناخته.'], 422),
        };
    }

    /** Redirect-e classic ba flash: host-e toast ha `session('toast')` ro sar-e load neshun mide. */
    public function flash(): RedirectResponse
    {
        return redirect()->route('panel.kit.index')->with('toast', [
            'kind' => 'success',
            'title' => 'فرم ارسال شد',
            'message' => 'این توست از session پس از redirect آمده است.',
        ]);
    }
}

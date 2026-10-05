<?php

declare(strict_types=1);

namespace Tests\Feature\Common;

use App\Modules\Common\Http\Middleware\LatinizeDigits;
use App\Modules\Common\Http\Middleware\NormalizePhoneMiddleware;
use App\Modules\Common\Http\Middleware\NormalizeRouteParamsMiddleware;
use App\Modules\Common\Http\Middleware\NormalizeStringMiddleware;
use App\Modules\Common\Http\Middleware\SecurityHeaders;
use App\Modules\Common\Http\Middleware\TrustProxies;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies as FrameworkTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * 🔴 Tartib e middleware ha — har kodum az in assert ha yek bug e VAGHEI ro
 * mibande، na yek salighe.
 */
final class MiddlewareOrderTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function globalStack(): array
    {
        $kernel = $this->app->make(Kernel::class);

        /** @var array<int, string> $stack */
        $stack = (new ReflectionClass($kernel))->getProperty('middleware')->getValue($kernel);

        return $stack;
    }

    /** @return array<int, string> */
    private function webGroup(): array
    {
        $kernel = $this->app->make(Kernel::class);

        /** @var array<string, array<int, string>> $groups */
        $groups = (new ReflectionClass($kernel))->getProperty('middlewareGroups')->getValue($kernel);

        return $groups['web'];
    }

    // ── 🔴 TrustProxies ─────────────────────────────────────────────

    /**
     * 🔴 JAYGOZIN، na EZAFE.
     *
     * Framework khodesh `TrustProxies` ro tu stack e global dare. Agar male
     * khodemun `prepend` beshe، HAR DO ejra mishan: `REMOTE_ADDR` do bar
     * bazneveshte mishe va natije be tartib e un do vabaste mishe.
     */
    public function test_the_framework_trust_proxies_is_replaced_not_duplicated(): void
    {
        $stack = $this->globalStack();

        self::assertContains(TrustProxies::class, $stack);
        self::assertNotContains(FrameworkTrustProxies::class, $stack);
        self::assertCount(
            1,
            array_filter($stack, static fn (string $m): bool => str_contains($m, 'TrustProxies')),
        );
    }

    /**
     * 🔴 Bayad GHABL az har chizi ke IP ya scheme mikhune bashe —
     * makhsusan `HandleCors`.
     */
    public function test_trust_proxies_runs_before_cors(): void
    {
        $stack = $this->globalStack();

        self::assertLessThan(
            array_search(HandleCors::class, $stack, true),
            array_search(TrustProxies::class, $stack, true),
        );
    }

    public function test_security_headers_is_global_so_api_responses_get_them_too(): void
    {
        self::assertContains(SecurityHeaders::class, $this->globalStack());
    }

    // ── 🔴 NormalizeRouteParams ghabl az SubstituteBindings ─────────

    /**
     * 🔴 Bug-i ke vaghe'an oftad: aval `NormalizeRouteParams` ro `append`
     * kardam va jaygah e 10 shod — BA'D az `SubstituteBindings` (7).
     *
     * Un amalan middleware ro BI-ASAR mikone: route-model binding ba
     * parameter e KHAM ejra mishe، pas 404 mide va normalize e ba'd az un
     * hich fayede-i nadare.
     */
    public function test_route_params_are_normalised_before_bindings_are_substituted(): void
    {
        $web = $this->webGroup();

        self::assertLessThan(
            array_search(SubstituteBindings::class, $web, true),
            array_search(NormalizeRouteParamsMiddleware::class, $web, true),
            'NormalizeRouteParams bayad GHABL az SubstituteBindings bashe.',
        );
    }

    /**
     * 🔴 Hamun bug، vali az samt e RAFTAR — na tartib.
     *
     * In test e asli-e: yek URL e copy-paste shode az matn e Farsi bayad
     * model ro peyda kone. Agar tartib kharab beshe، in 404 mide.
     */
    public function test_route_model_binding_works_with_persian_digits(): void
    {
        $provider = Provider::create([
            'key' => 'tgju', 'driver' => 'tgju', 'kind' => 'reference',
            'name' => 'TGJU', 'name_fa' => 'آزمایشی', 'is_active' => true,
        ]);

        Route::middleware('web')->get('/t/{provider}', static fn (Provider $provider): string => $provider->key);

        // Shenase ba raghame FARSI.
        $persianId = strtr((string) $provider->id, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        $this->get('/t/'.rawurlencode($persianId))->assertOk()->assertSee('tgju');
    }

    // ── Tartib e normalize e input ──────────────────────────────────

    /** `NormalizeString` bayad GHABL az `NormalizePhone` bashe. */
    public function test_strings_are_normalised_before_phones(): void
    {
        $web = $this->webGroup();

        self::assertLessThan(
            array_search(NormalizePhoneMiddleware::class, $web, true),
            array_search(NormalizeStringMiddleware::class, $web, true),
        );
    }

    public function test_a_persian_digit_phone_arrives_as_e164_at_the_controller(): void
    {
        Route::middleware('web')->post('/t/phone', static fn (Request $request): string => (string) $request->input('phone'));

        // CSRF inja mored e barresi nist — faghat normalize.
        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post('/t/phone', ['phone' => '۰۹۱۲۱۲۳۴۵۶۷'])
            ->assertOk()
            ->assertSee('+989121234567');
    }

    // ── 🔴 LatinizeDigits bayad BIRUNI TARIN bashe ──────────────────

    /**
     * 🔴 Zed e shohud: `prepend`، na `append`.
     *
     * Pipeline tu-dar-tu-st، pas code e BA'D az `$next()` baraye **avvalin**
     * middleware **AKHAR** ejra mishe. `LatinizeDigits` yek filter e
     * KHORUJI-e: bayad HTML e NAHAYI ro bebine.
     */
    public function test_latinize_digits_is_the_outermost_web_middleware(): void
    {
        self::assertSame(LatinizeDigits::class, $this->webGroup()[0]);
    }

    /**
     * Middleware e sath e ROUTE ke ba'd az controller ragham e Farsi be
     * khoruji ezafe mikone — un ham bayad Latin beshe.
     *
     * ⚠️ In test jaygah e `LatinizeDigits` TU GROUP ro NEMISANJE — ba
     * mutation check ma'lum shod ke ba `append` ham sabz mimune، chun
     * middleware e route hamishe DAKHEL e group ejra mishe، pas
     * `LatinizeDigits` har ja ye group bashe baz ham biruni tar az un ast.
     *
     * Chizi ke vaghe'an jaygah ro mikhkub mikone
     * `test_latinize_digits_is_the_outermost_web_middleware` ast (un ba
     * mutation ghermez shod). In yeki yek khasiat e DIGE ro sabt mikone:
     * khoruji e middleware haye route ham az filter rad mishe.
     */
    public function test_digits_added_by_an_inner_middleware_are_still_latinised(): void
    {
        Route::middleware(['web', AppendPersianDigits::class])
            ->get('/t/inner', static fn (): string => 'gheymat: ');

        $this->get('/t/inner')->assertOk()->assertSee('gheymat: 1234');
    }

    /** JSON dast nemikhore — snapshot e Livewire checksum dare. */
    public function test_a_json_response_on_a_web_route_is_left_alone(): void
    {
        Route::middleware('web')->get('/t/json', static fn (): array => ['v' => '۱۲۳۴']);

        // ⚠️ `assertSee` kar nemikone chun `json_encode` raghamha ro
        // unicode-escape mikone (`\u06f1…`) — un ESCAPE ast، na taghir.
        // `assertExactJson` meghdar e DECODE-shode ro moghayese mikone.
        $this->get('/t/json')->assertOk()->assertExactJson(['v' => '۱۲۳۴']);
    }

    /**
     * Paye ye tasmim e «JSON dast nemikhore»: endpoint e Livewire vojud dare
     * va POST ast.
     *
     * ⚠️ URI esh HASH dare (`livewire-383c42fc/update`) — hash az nasakhe
     * miad، pas nabayad sakht-code beshe.
     */
    public function test_the_livewire_update_endpoint_exists(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(static fn ($r): bool => str_contains((string) $r->uri(), 'update')
                && str_starts_with((string) $r->uri(), 'livewire'));

        self::assertNotNull($route, 'endpoint e update e Livewire peyda nashod.');
        self::assertContains('POST', $route->methods());
    }
}

/** Middleware e komaki: ba'd az route، ragham e Farsi be khoruji ezafe mikone. */
final class AppendPersianDigits
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->setContent((string) $response->getContent().'۱۲۳۴');

        return $response;
    }
}

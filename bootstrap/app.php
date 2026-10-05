<?php

declare(strict_types=1);

use App\Modules\Common\Http\Middleware\LatinizeDigits;
use App\Modules\Common\Http\Middleware\NormalizePhoneMiddleware;
use App\Modules\Common\Http\Middleware\NormalizeRouteParamsMiddleware;
use App\Modules\Common\Http\Middleware\NormalizeStringMiddleware;
use App\Modules\Common\Http\Middleware\SecurityHeaders;
use App\Modules\Common\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies as FrameworkTrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * ── 🔴 TrustProxies: JAYGOZIN، na EZAFE ─────────────────────
         * Framework khodesh `Illuminate\Http\Middleware\TrustProxies` ro tu
         * stack e global dare (jaygah e 3، ghabl az `HandleCors`). Agar male
         * khodemun ro `prepend` konim، HAR DO ejra mishan: `REMOTE_ADDR` do
         * bar bazneveshte mishe va natije ash be tartib e un do vabaste
         * mishe.
         *
         * `replace()` jaygah ro hefz mikone — ke MOHEM ast: bayad ghabl az
         * `HandleCors` (ke be scheme niaz dare) va ghabl az har chizi ke IP
         * mikhune bashe.
         *
         * Chera asl esh hayati-e: bedun e un `$request->ip()` hamishe
         * neshani e proxy ast، pas rate limit e IP e OTP (`P2-06`) ya hame
         * ro ba ham ban mikone ya hichkas ro.
         */
        $middleware->replace(FrameworkTrustProxies::class, TrustProxies::class);

        /*
         * Header haye amniati ru HAME ye pasokh ha — web ham، API ham.
         * (Global، chun yek pasokh e API ham be `nosniff` niaz dare.)
         */
        $middleware->append(SecurityHeaders::class);

        /*
         * ── Normalize e INPUT — FAGHAT `web` ────────────────────────
         * Tartib LOAD-BEARING ast: `NormalizeString` bayad GHABL az
         * `NormalizePhone` bashe، vagarna un yeki yek shomare ba raghame
         * Farsi mibine va nemitune parse kone.
         *
         * ⚠️ Ru `api` AMDAN sabt nashode: yek API e foroshi bayad ghararad e
         * SARIH dashte bashe، na input-i ke saket bazneveshte mishe. Tasmim
         * esh tu Phase 4 gerefte mishe، jayi ke endpoint ha ma'lum-and.
         */
        $middleware->web(append: [
            NormalizeStringMiddleware::class,
            NormalizePhoneMiddleware::class,
        ]);

        /*
         * ── 🔴 `LatinizeDigits` PREPEND mishe، na APPEND ────────────
         * Zed e shohud ast، pas benevis chera:
         *
         * Pipeline e `[A, B, C]` tu-dar-tu-st: A -> B -> C -> route، va
         * pasokh bar migarde route -> C -> B -> A. Ya'ni code e BA'D az
         * `$next()` baraye **avvalin** middleware **AKHAR** ejra mishe.
         *
         * `LatinizeDigits` yek filter e KHORUJI-e: bayad HTML e NAHAYI ro
         * bebine، ba'd az har chizi ke middleware haye daxeli be pasokh
         * ezafe karde-and. Pas bayad BIRUNI TARIN bashe = avvalin tu lista =
         * `prepend`.
         *
         * Agar `append` mishod، transform esh AVVAL ejra mishod va har
         * middleware e biruni tar mitunest ba'd az un ragham e Farsi be
         * khoruji ezafe kone — va un ragham ha dast-nakhorde mireftan birun.
         * (`MiddlewareOrderTest` daghighan hamin ro check mikone.)
         */
        /*
         * ── 🔴 `NormalizeRouteParams` bayad GHABL az `SubstituteBindings`
         * bashe ─────────────────────────────────────────────────────
         * Aval un ro `append` kardam va jaygah e 10 shod — BA'D az
         * `SubstituteBindings` (7). Un amalan middleware ro BI-ASAR mikone:
         * route-model binding ba parameter e KHAM ejra mishe، pas
         * `/providers/۱۰۲۲` ghabl az normalize shodan 404 mide، va normalize
         * e ba'd az un hich fayede-i nadare.
         *
         * `prepend` un ro ghabl az `SubstituteBindings` mizare.
         * `$request->route()` unja hast chun group e `web` BA'D az match
         * shodan e route ejra mishe.
         */
        $middleware->web(prepend: [
            LatinizeDigits::class,
            NormalizeRouteParamsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

<?php

declare(strict_types=1);

use App\Modules\Access\Http\Controllers\AccessController;
use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Middleware\EnsureAdmin;
use App\Modules\Kyc\Http\Controllers\OnboardingController;
use App\Modules\Media\Http\Controllers\Panel\MediaController;
use App\Modules\Panel\Http\Controllers\ChartController;
use App\Modules\Panel\Http\Controllers\DashboardController;
use App\Modules\Panel\Http\Controllers\KitController;
use App\Modules\Panel\Http\Controllers\ManualPriceController;
use App\Modules\Panel\Http\Controllers\MatrixController;
use App\Modules\Panel\Http\Controllers\MonitorController;
use App\Modules\Panel\Http\Controllers\PriorityController;
use App\Modules\Panel\Http\Controllers\ProviderController;
use App\Modules\Panel\Http\Controllers\ProviderSpreadController;
use App\Modules\Panel\Http\Controllers\TicketController;
use App\Modules\Panel\Http\Controllers\UserController;
use App\Modules\Pricing\Http\Controllers\PublicBoardController;
use App\Modules\Seo\Http\Controllers\SeoController;
use App\Modules\Seo\Http\Controllers\SeoEditorController;
use App\Modules\Ticket\Http\Controllers\AccountTicketController;
use App\Modules\Ticket\Http\Controllers\TicketAttachmentController;
use Illuminate\Support\Facades\Route;

/*
 * ── Site e omumi (Phase 5) ───────────────────────────────────────────
 * Tablo ye moghayese ye gheymat — SSR (SEO) + `/board.json` baraye poll e
 * 15 saniye-i ye Alpine (OMUMI، bedun e auth — bar khalaf e `/api/v1/*`).
 */
Route::get('/', [PublicBoardController::class, 'home'])->name('home');
Route::get('/board.json', [PublicBoardController::class, 'boardJson'])->name('board.json');
Route::get('/candles.json', [PublicBoardController::class, 'candlesJson'])->name('candles.json');
// Board e TV (P5-03) — full-screen baraye namayeshgar e maghaze؛ OMUMI.
Route::get('/tv', [PublicBoardController::class, 'tv'])->name('tv');
Route::get('/tv.json', [PublicBoardController::class, 'tvJson'])->name('tv.json');

// SEO (P5-05) — `robots.txt` DYNAMIC (file e static e `public/` hazf shod:
// ghabl az route serve mishod) + sitemap index.
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('sitemap.index');
Route::get('/sitemaps/pages.xml', [SeoController::class, 'sitemapPages'])->name('sitemap.pages');

/*
 * ── Login ba OTP ─────────────────────────────────────────────────────
 * Controller e nazok + Alpine. Hame GUEST-and (karbar e login-shode be
 * maghsad esh mire). POST ha JSON javab midan — `alpine/login.js`.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login/code', [LoginController::class, 'requestCode'])->name('login.code');
    Route::post('/login/cooldown', [LoginController::class, 'cooldown'])->name('login.cooldown');
    Route::post('/login/reset', [LoginController::class, 'reset'])->name('login.reset');
    Route::post('/login/verify', [LoginController::class, 'verify'])->name('login.verify');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
 * ── Panel (faghat admin) ─────────────────────────────────────────────
 */
Route::middleware(['auth', EnsureAdmin::class])
    ->prefix('dashboard')
    ->name('panel.')
    ->group(function (): void {
        Route::get('/', [DashboardController::class, 'show'])->name('dashboard');
        Route::view('/no-access', 'access::no-access')->name('no-access');

        Route::get('/seo', [SeoEditorController::class, 'index'])->name('seo.index');
        Route::put('/seo/{page}', [SeoEditorController::class, 'update'])->name('seo.update');

        Route::get('/access', [AccessController::class, 'index'])->name('access.index');
        Route::post('/access/roles', [AccessController::class, 'save'])->name('access.roles.store');
        Route::put('/access/roles/{role}', [AccessController::class, 'save'])->whereNumber('role')->name('access.roles.update');
        Route::delete('/access/roles/{role}', [AccessController::class, 'destroy'])->whereNumber('role')->name('access.roles.destroy');
        Route::put('/access/admins/{user}', [AccessController::class, 'assign'])->whereNumber('user')->name('access.assign');

        Route::get('/media', [MediaController::class, 'index'])->name('media.index');
        Route::get('/media/table', [MediaController::class, 'table'])->name('media.table');
        Route::post('/media', [MediaController::class, 'store'])->name('media.store');
        Route::put('/media/{media}', [MediaController::class, 'update'])->whereNumber('media')->name('media.update');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->whereNumber('media')->name('media.destroy');

        // ── Manba' ha (P3-06) ───────────────────────────────────────
        Route::get('/providers', [ProviderController::class, 'index'])->name('providers.index');
        Route::get('/providers/create', [ProviderController::class, 'create'])->name('providers.create');
        Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
        Route::get('/providers/{key}/edit', [ProviderController::class, 'edit'])->name('providers.edit');
        Route::put('/providers/{key}', [ProviderController::class, 'update'])->name('providers.update');
        Route::post('/providers/{key}/flags', [ProviderController::class, 'flags'])->name('providers.flags');

        // ── Spread (P3-07) ──────────────────────────────────────────
        Route::get('/providers/{key}/spreads', [ProviderSpreadController::class, 'edit'])->name('providers.spreads');
        Route::put('/providers/{key}/spreads', [ProviderSpreadController::class, 'update'])->name('providers.spreads.update');
        // PUT chun dokme ye hazf DAKHEL e form e asli-e (formaction) va un
        // form `@method('PUT')` dare.
        Route::put('/providers/{key}/spreads/{symbol}/remove', [ProviderSpreadController::class, 'remove'])->name('providers.spreads.remove');
        Route::post('/providers/{key}/spreads/preview', [ProviderSpreadController::class, 'preview'])->name('providers.spreads.preview');

        // ── Gheymat e dasti (fallback — Phase 4/5) ──────────────────
        Route::get('/manual', [ManualPriceController::class, 'index'])->name('manual.index');
        Route::post('/manual', [ManualPriceController::class, 'update'])->name('manual.update');
        Route::post('/manual/remove', [ManualPriceController::class, 'remove'])->name('manual.remove');

        // ── Olaviat e per-symbol (P5-07) ────────────────────────────
        Route::get('/priorities', [PriorityController::class, 'index'])->name('priorities.index');
        Route::post('/priorities', [PriorityController::class, 'update'])->name('priorities.update');
        Route::post('/priorities/settings', [PriorityController::class, 'settings'])->name('priorities.settings');
        Route::get('/manual/template', [ManualPriceController::class, 'template'])->name('manual.template');
        Route::post('/manual/import/preview', [ManualPriceController::class, 'preview'])->block()->name('manual.import.preview');
        Route::post('/manual/import/confirm', [ManualPriceController::class, 'confirm'])->block()->name('manual.import.confirm');

        // ── Monitoring (P3-08/09/10) ────────────────────────────────
        Route::redirect('/health', '/dashboard/providers')->name('health');
        Route::get('/monitor', [MonitorController::class, 'ticks'])->name('monitor.ticks');
        Route::get('/monitor/api', [MonitorController::class, 'api'])->name('monitor.api');
        Route::get('/monitor/upstream', [MonitorController::class, 'upstream'])->name('monitor.upstream');
        Route::get('/monitor/upstream/{id}', [MonitorController::class, 'upstreamShow'])
            ->where('id', '[A-Za-z0-9]{1,32}')
            ->name('monitor.upstream.show');
        Route::get('/matrix', [MatrixController::class, 'show'])->name('matrix');

        // ── Chart (P5-08) ───────────────────────────────────────────
        Route::get('/charts', [ChartController::class, 'show'])->name('charts');
        Route::get('/charts/data', [ChartController::class, 'data'])->name('charts.data');

        // ── Karbaran (Phase 6) ──────────────────────────────────────
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/role', [UserController::class, 'role'])->whereNumber('user')->name('users.role');
        Route::post('/users/{user}/disable', [UserController::class, 'disable'])->whereNumber('user')->name('users.disable');
        Route::post('/users/{user}/enable', [UserController::class, 'enable'])->whereNumber('user')->name('users.enable');
        Route::post('/users/{user}/wallet', [UserController::class, 'adjustWallet'])->whereNumber('user')->name('users.wallet.adjust');
        Route::get('/users/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show');

        // ── Ticket ha (Phase 7) ─────────────────────────────────────
        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->whereNumber('ticket')->name('tickets.reply');
        Route::post('/tickets/{ticket}/status', [TicketController::class, 'status'])->whereNumber('ticket')->name('tickets.status');
    });

/*
 * ── Onboarding (P7-03) ───────────────────────────────────────────────
 * Ba'd az avvalin login e moshtari (redirect e `LoginController`)؛ karbar e
 * `basic` ba'dan ham az inja darkhast e ertegha mide.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
});

/*
 * ── Hesab e karbar (Phase 7) ─────────────────────────────────────────
 * Ticket haye KHOD e karbar (har naghsh). Malekiat tu `TicketQuery::
 * findForOwnerOrFail` — ticket e digari 404.
 */
Route::middleware('auth')
    ->prefix('account')
    ->name('account.')
    ->group(function (): void {
        Route::get('/tickets', [AccountTicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/create', [AccountTicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [AccountTicketController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/{ticket}', [AccountTicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [AccountTicketController::class, 'reply'])->whereNumber('ticket')->name('tickets.reply');
    });

// Download e peyvast (P7-02): emza ye zamandar (relative — host-independent) +
// saheb/admin tu controller. Hamun URL baraye admin va karbar.
Route::get('/tickets/attachments/{attachment}', [TicketAttachmentController::class, 'download'])
    ->middleware(['auth', 'signed:relative'])
    ->whereNumber('attachment')
    ->name('tickets.attachments.download');

/*
 * ── Kit e component ha (FAGHAT dev) ──────────────────────────────────
 * Namayeshgah e zende ye primitive haye panel — P3-04 rush kar mikone.
 * `local-only` kharej az local/testing 404 mide؛ auth lazem nist chun tu
 * production aslan vojud nadare va e2e bedun e login roosh test mizane.
 */
Route::middleware('local-only')
    ->prefix('dashboard/kit')
    ->name('panel.kit.')
    ->group(function (): void {
        Route::get('/', [KitController::class, 'index'])->name('index');
        Route::get('/date', [KitController::class, 'date'])->name('date');
        Route::get('/slug', [KitController::class, 'slug'])->name('slug');
        Route::post('/request', [KitController::class, 'request'])->name('request');
        Route::post('/flash', [KitController::class, 'flash'])->name('flash');
    });

require __DIR__.'/identity.php';

require __DIR__.'/billing.php';

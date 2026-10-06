<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Middleware\EnsureAdmin;
use App\Modules\Panel\Http\Controllers\DashboardController;
use App\Modules\Panel\Http\Controllers\KitController;
use App\Modules\Panel\Http\Controllers\ProviderController;
use App\Modules\Panel\Http\Controllers\ProviderSpreadController;
use Illuminate\Support\Facades\Route;

/*
 * ⚠️ Route e `home` fe'lan placeholder e matn ast — site e omumi Phase 5 e.
 * Esm esh az alan hast chun login/logout behesh redirect mikonan.
 */
Route::get('/', static fn (): string => 'PriceBoard — be zudi.')->name('home');

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
    });

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

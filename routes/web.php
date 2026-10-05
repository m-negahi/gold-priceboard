<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Middleware\EnsureAdmin;
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
 * ⚠️ Dashboard fe'lan placeholder ast (P3-00/P3-05): DashboardQuery e
 * noskhe ye ghabli be Pricing e UN vabaste-ast va bayad be BoardQuery e in
 * proje vasl she.
 */
Route::middleware(['auth', EnsureAdmin::class])
    ->prefix('dashboard')
    ->name('panel.')
    ->group(function (): void {
        Route::get('/', static fn () => view('panel-placeholder'))->name('dashboard');
    });

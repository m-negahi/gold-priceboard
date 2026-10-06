<?php

declare(strict_types=1);

use App\Modules\Api\Http\Controllers\CandlesController;
use App\Modules\Api\Http\Controllers\EffectiveController;
use App\Modules\Api\Http\Controllers\MetaController;
use App\Modules\Api\Http\Controllers\PricesController;
use App\Modules\Api\Http\Controllers\WalletController;
use App\Modules\Api\Http\Middleware\ApiHmacAuth;
use App\Modules\Api\Http\Middleware\ChargeApiCall;
use App\Modules\Api\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

/*
 * ── API e foroshi (Phase 4) ──────────────────────────────────────────
 * Hame posht e HMAC (`ApiHmacAuth`). Endpoint haye DATA charge daran
 * (`ChargeApiCall` — 304/error refund mishe)؛ metadata va wallet majani.
 *
 * ⚠️ Hich normalize-middleware-i inja NIST (amdan — bootstrap/app.php):
 * API ghararad e sarih dare؛ vorudi ye kaj 422 migire، bazneveshte
 * nemishe.
 */
// `LogApiRequest` BIRUNI tarin ast ta 401 haye auth ham sabt shan (P4-11).
Route::prefix('v1')->middleware([LogApiRequest::class, ApiHmacAuth::class])->group(function (): void {
    // Majani — ghablan pul e call e data dade.
    Route::get('/instruments', [MetaController::class, 'instruments'])->name('api.instruments');
    Route::get('/providers', [MetaController::class, 'providers'])->name('api.providers');
    Route::get('/wallet', [WalletController::class, 'show'])->name('api.wallet');

    Route::middleware(ChargeApiCall::class)->group(function (): void {
        Route::get('/prices', [PricesController::class, 'index'])->name('api.prices');
        Route::get('/prices/{symbol}', [PricesController::class, 'show'])->name('api.prices.show');
        // Feed e mo'asser: yek gheymat/symbol ba olaviat + fallback be manual.
        Route::get('/effective', [EffectiveController::class, 'index'])->name('api.effective');
        Route::get('/candles', [CandlesController::class, 'index'])->name('api.candles');
    });
});

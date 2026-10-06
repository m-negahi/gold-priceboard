<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Middleware\EnsureAdmin;
use App\Modules\Billing\Http\Controllers\AccountBillingController;
use App\Modules\Panel\Http\Controllers\ApiPlanController;
use App\Modules\Panel\Http\Controllers\FinanceController;
use App\Modules\Panel\Http\Controllers\SiteApprovalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureAdmin::class])->prefix('dashboard')->name('panel.')->group(function (): void {
    Route::middleware('can:finance.view')->group(function (): void {
        Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('/finance/export', [FinanceController::class, 'export'])->name('finance.export');
        Route::get('/finance/{transaction}', [FinanceController::class, 'show'])->whereNumber('transaction')->name('finance.show');
    });
    Route::middleware('can:finance.manage')->group(function (): void {
        Route::post('/finance/{transaction}/reverse', [FinanceController::class, 'reverse'])->whereNumber('transaction')->name('finance.reverse');
        Route::post('/finance/{transaction}/correct', [FinanceController::class, 'correct'])->whereNumber('transaction')->name('finance.correct');
        Route::post('/finance/payments/{payment}/settle', [FinanceController::class, 'settle'])->whereNumber('payment')->name('finance.settle');
    });
    Route::middleware('can:plans.view')->group(function (): void {
        Route::get('/plans', [ApiPlanController::class, 'index'])->name('plans.index');
    });
    Route::middleware('can:plans.manage')->group(function (): void {
        Route::get('/plans/create', [ApiPlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [ApiPlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [ApiPlanController::class, 'edit'])->whereNumber('plan')->name('plans.edit');
        Route::put('/plans/{plan}', [ApiPlanController::class, 'update'])->whereNumber('plan')->name('plans.update');
        Route::post('/plans/{plan}/archive', [ApiPlanController::class, 'archive'])->whereNumber('plan')->name('plans.archive');
    });
    Route::middleware('can:sites.view')->group(function (): void {
        Route::get('/sites', [SiteApprovalController::class, 'index'])->name('sites.index');
    });
    Route::middleware('can:sites.manage')->group(function (): void {
        Route::post('/sites/{ticket}/enamad', [SiteApprovalController::class, 'check'])->whereNumber('ticket')->name('sites.enamad');
        Route::post('/sites/{ticket}/decision', [SiteApprovalController::class, 'decide'])->whereNumber('ticket')->name('sites.decision');
    });
});

Route::middleware('auth')->prefix('account')->name('account.billing.')->group(function (): void {
    Route::get('/', [AccountBillingController::class, 'dashboard'])->name('dashboard');
    Route::get('/sites', [AccountBillingController::class, 'sites'])->name('sites');
    Route::post('/sites', [AccountBillingController::class, 'storeSite'])->name('sites.store');
    Route::get('/subscriptions', [AccountBillingController::class, 'subscriptions'])->name('subscriptions');
    Route::post('/subscriptions', [AccountBillingController::class, 'purchase'])->name('subscriptions.purchase');
    Route::get('/wallet', [AccountBillingController::class, 'wallet'])->name('wallet');
    Route::post('/wallet/topup', [AccountBillingController::class, 'startTopup'])->name('topup.start');
    Route::get('/wallet/invoices/{invoice}', [AccountBillingController::class, 'invoice'])->whereNumber('invoice')->name('invoice');
    Route::get('/usage', [AccountBillingController::class, 'usage'])->name('usage');
});

Route::get('/payments/zibal/callback', [AccountBillingController::class, 'callback'])->name('account.billing.topup.callback');

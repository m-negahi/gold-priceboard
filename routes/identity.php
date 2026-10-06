<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Middleware\EnsureAdmin;
use App\Modules\Panel\Http\Controllers\TicketController;
use App\Modules\Panel\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureAdmin::class])->prefix('dashboard')->name('panel.')->group(function (): void {
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->whereNumber('user')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::post('/tickets/{ticket}/rerun', [TicketController::class, 'rerun'])->whereNumber('ticket')->name('tickets.rerun');
    Route::post('/tickets/{ticket}/decide', [TicketController::class, 'decide'])->whereNumber('ticket')->name('tickets.decide');
});

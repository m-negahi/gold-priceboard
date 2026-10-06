<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Providers;

use App\Modules\Common\Providers\ModuleServiceProvider;

/**
 * Module e Ticket (Phase 7) — view haye `ticket::` (safhe haye karbar).
 * Route ha tu `routes/web.php` (ADR-0027).
 */
final class TicketServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Ticket';
    }
}

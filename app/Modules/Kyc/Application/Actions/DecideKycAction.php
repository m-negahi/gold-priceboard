<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Application\Actions;

use App\Modules\Auth\Domain\Enums\CustomerType;
use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Ticket\Application\Actions\TransitionTicketAction;
use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DecideKycAction
{
    public function __construct(private TransitionTicketAction $transition) {}

    public function execute(Ticket $ticket, User $actor, bool $approve, string $reason): Ticket
    {
        abort_unless($actor->is_admin, 403);
        if (! $approve && trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'دلیل رد درخواست الزامی است.']);
        }

        return DB::transaction(function () use ($ticket, $actor, $approve, $reason): Ticket {
            $user = User::query()->whereKey($ticket->user_id)->lockForUpdate()->firstOrFail();
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->type === TicketType::KycUpgrade, 422);
            $updated = $this->transition->execute($locked, $approve ? TicketStatus::Approved : TicketStatus::Rejected, $actor->id, $reason, 'decision');
            if ($approve) {
                $auditFields = ['name', 'customer_type', 'national_code', 'level', 'identity_profile', 'invoice_profile'];
                $before = $user->only($auditFields);
                $profile = $locked->meta ?? [];
                $user->forceFill(['level' => UserLevel::InvoiceVerified, 'customer_type' => empty($profile['company_national_id']) ? CustomerType::Individual : CustomerType::Legal,
                    'name' => $profile['name'] ?? $user->name, 'invoice_profile' => $profile, 'identity_profile' => [...$profile, 'landline' => $profile['phone'] ?? $profile['landline'] ?? null], 'national_code' => $profile['national_code'] ?? $profile['representative_national_code'] ?? null])->save();
                DB::table('user_profile_audits')->insert(['user_id' => $user->id, 'actor_id' => $actor->id, 'before' => json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($user->only($auditFields), JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }

            return $updated;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Application\Actions;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Kyc\Application\Dto\KycRequestDto;
use App\Modules\Kyc\Application\Queries\KycQuery;
use App\Modules\Ticket\Application\Actions\OpenTicketAction;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Darkhast e ertegha be `invoice_verified` (P7-03): ticket e `kyc_upgrade`
 * ba form (meta) + madarek، va onboarding tamum.
 *
 * 🔴 Level inja AVAZ NEMISHE — faghat ta'eed e admin (P7-06).
 * 🔴 Yek darkhast e baz dar har lahze: radif e karbar `lockForUpdate` mishe
 * ta do submit e hamzaman (double-click) do ticket nasazan.
 */
final readonly class SubmitKycRequestAction
{
    public function __construct(
        private OpenTicketAction $open,
        private KycQuery $kyc,
        private QueueIdentityChecksAction $checks,
    ) {}

    /** @param array<int, UploadedFile> $files */
    public function execute(User $user, KycRequestDto $request, array $files): Ticket
    {
        return DB::transaction(function () use ($user, $request, $files): Ticket {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($this->kyc->pendingTicketFor($user->id) !== null) {
                throw TicketException::pendingExists();
            }

            $ticket = $this->open->execute(
                $user->id,
                TicketType::KycUpgrade,
                'درخواست ارتقای سطح — '.$request->registeredName,
                'درخواست دریافت فاکتور رسمی برای «'.$request->registeredName.'» (شناسه ملی '.$request->companyNationalId.'). مدارک پیوست شده است.',
                meta: $request->toMeta(),
                files: $files,
            );

            $user->forceFill([
                'onboarded_at' => $user->onboarded_at ?? CarbonImmutable::now(),
                'invoice_requested' => true,
            ])->save();

            $this->checks->execute($user->id, [...$request->toMeta(), 'mobile' => $user->phone], 'ticket:'.$ticket->id, $ticket->id);

            return $ticket;
        });
    }
}

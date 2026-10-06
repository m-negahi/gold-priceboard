<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Application\Actions;

use App\Modules\Auth\Http\Support\UserProfileRules;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Kyc\Application\Actions\QueueIdentityChecksAction;
use App\Modules\Kyc\Application\Queries\KycQuery;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateTicketRequestAction
{
    public function __construct(private OpenTicketAction $open, private QueueIdentityChecksAction $checks, private KycQuery $kyc) {}

    /** @param array<string, mixed> $profile
     * @param  array<int, UploadedFile>  $files
     */
    public function execute(User $owner, TicketType $type, string $subject, string $body, array $profile, array $files, ?User $actor = null): Ticket
    {
        return DB::transaction(function () use ($owner, $type, $subject, $body, $profile, $files, $actor): Ticket {
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            if ($type === TicketType::KycUpgrade && $this->kyc->pendingTicketFor($owner->id) !== null) {
                throw TicketException::pendingExists();
            }
            if ($type === TicketType::KycUpgrade) {
                $required = ($profile['customer_type'] ?? 'individual') === 'legal'
                    ? ['registered_name', 'company_national_id', 'economic_code', 'representative_national_code', 'representative_birth_date', 'postal_code', 'address']
                    : ['name', 'national_code', 'birth_date', 'postal_code', 'address'];
                foreach ($required as $field) {
                    if (empty($profile[$field])) {
                        throw ValidationException::withMessages([$field => 'این فیلد برای احراز هویت الزامی است.']);
                    }
                }
                $profile = array_intersect_key($profile, array_flip([...UserProfileRules::PROFILE_FIELDS, 'name', 'national_code', 'customer_type']));
                if (($profile['customer_type'] ?? 'individual') === 'individual') {
                    $profile = array_diff_key($profile, array_flip(['registered_name', 'company_national_id', 'economic_code', 'representative_national_code', 'representative_birth_date']));
                } else {
                    unset($profile['birth_date'], $profile['national_code']);
                }
                $profile['phone'] = $profile['landline'] ?? '';
            }
            $ticket = $this->open->execute($owner->id, $type, $subject, $body, $type === TicketType::KycUpgrade ? $profile : null, $files, $actor?->id);
            if ($type === TicketType::KycUpgrade) {
                $this->checks->execute($owner->id, [...$profile, 'mobile' => $owner->phone], 'ticket:'.$ticket->id, $ticket->id, $actor?->id);
            }

            return $ticket;
        });
    }
}

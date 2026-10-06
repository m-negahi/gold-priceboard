<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Application\Actions;

use App\Modules\Kyc\Jobs\RunIdentityChecks;
use Illuminate\Support\Facades\DB;

final readonly class QueueIdentityChecksAction
{
    /** @param array<string, mixed> $input */
    public function execute(int $userId, array $input, string $submissionKey, ?int $ticketId = null, ?int $actorId = null): int
    {
        return DB::transaction(function () use ($userId, $input, $submissionKey, $ticketId, $actorId): int {
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $existing = DB::table('identity_verification_runs')->where('submission_key', $submissionKey)->value('id');
            if ($existing !== null) {
                return (int) $existing;
            }
            $id = DB::table('identity_verification_runs')->insertGetId([
                'user_id' => $userId, 'ticket_id' => $ticketId, 'actor_id' => $actorId,
                'submission_key' => $submissionKey, 'status' => 'queued',
                'input' => json_encode($input, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
            ]);
            RunIdentityChecks::dispatch($id)->afterCommit();

            return $id;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Actions;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use Illuminate\Support\Facades\Crypt;

/**
 * CRUD e client e API (P4-03).
 *
 * 🔴 Secret faghat YEK BAR bar migarde (khoruji ye `create`/`rotate`) —
 * server encrypt-shodash ro negah midare va hich endpoint-i aslan
 * nemitune dobare neshunesh bede. Gom shod؟ rotate.
 */
final readonly class ApiClientAction
{
    /**
     * @return array{client: ApiClient, secret: string} secret FAGHAT hamin yek bar
     */
    public function create(int $userId, string $name, ?int $priceGroupId = null): array
    {
        $secret = bin2hex(random_bytes(32));

        $client = ApiClient::query()->create([
            'user_id' => $userId,
            'price_group_id' => $priceGroupId,
            'name' => $name,
            // Gheyr e hads-pazir؛ prefix baraye tashkhis tu log ha.
            'client_id' => 'pb_'.bin2hex(random_bytes(12)),
            'secret_encrypted' => Crypt::encryptString($secret),
        ]);

        return ['client' => $client, 'secret' => $secret];
    }

    /** @return array{client: ApiClient, secret: string} */
    public function rotateSecret(ApiClient $client): array
    {
        $secret = bin2hex(random_bytes(32));

        $client->forceFill(['secret_encrypted' => Crypt::encryptString($secret)])->save();

        return ['client' => $client, 'secret' => $secret];
    }

    public function deactivate(ApiClient $client): ApiClient
    {
        $client->forceFill(['is_active' => false])->save();

        return $client;
    }

    public function activate(ApiClient $client): ApiClient
    {
        $client->forceFill(['is_active' => true])->save();

        return $client;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Application\Actions\CreateSiteAction;
use App\Modules\Api\Application\Actions\DecideSiteAction;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Kyc\Application\Dto\VerificationResultDto;
use App\Modules\Kyc\Domain\Ports\IdentityVerificationPort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Tests\Support\Fakes\FakeIdentityVerification;
use Tests\TestCase;

final class SiteApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_site_has_pending_key_and_approval_enables_signed_api(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $created = app(CreateSiteAction::class)->execute($user->id, 'Example', 'https://example.com');
        $fake = new FakeIdentityVerification;
        $fake->results['enamad'] = new VerificationResultDto('passed', 'تأیید شد.', ['domain' => 'example.com', 'expired' => false]);
        $this->app->instance(IdentityVerificationPort::class, $fake);

        $this->signed($created['client']->client_id, $created['secret'])->assertForbidden();
        $check = app(DecideSiteAction::class)->checkEnamad($created['ticket_id']);
        app(DecideSiteAction::class)->decide($created['ticket_id'], $admin->id, true, 'دامنه تأیید شد');

        self::assertSame('passed', $check['status']);
        self::assertSame('enamad', $fake->calls[0]['check']);
        self::assertSame(['website' => 'example.com'], $fake->calls[0]['input']);
        $this->assertDatabaseHas('api_clients', ['id' => $created['client']->id, 'status' => 'approved', 'approved_by' => $admin->id]);
        $this->assertDatabaseHas('tickets', ['id' => $created['ticket_id'], 'status' => 'approved']);
        $this->signed($created['client']->client_id, $created['secret'])->assertOk();
    }

    public function test_enamad_domain_mismatch_blocks_approval(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $created = app(CreateSiteAction::class)->execute($user->id, 'Example', 'https://example.com');
        $fake = new FakeIdentityVerification;
        $fake->results['enamad'] = new VerificationResultDto('passed', 'تأیید شد.', ['domain' => 'other.com', 'expired' => false]);
        $this->app->instance(IdentityVerificationPort::class, $fake);
        app(DecideSiteAction::class)->checkEnamad($created['ticket_id']);

        $this->expectException(InvalidArgumentException::class);
        app(DecideSiteAction::class)->decide($created['ticket_id'], $admin->id, true, 'approve');
    }

    public function test_unconfigured_enamad_check_keeps_provider_warning_and_blocks_approval(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $created = app(CreateSiteAction::class)->execute($user->id, 'Example', 'https://example.com');
        $fake = new FakeIdentityVerification;
        $fake->results['enamad'] = new VerificationResultDto('warning', 'سرویس استعلام پیکربندی نشده است.', []);
        $this->app->instance(IdentityVerificationPort::class, $fake);

        $check = app(DecideSiteAction::class)->checkEnamad($created['ticket_id']);
        self::assertSame('warning', $check['status']);
        self::assertSame('سرویس استعلام پیکربندی نشده است.', $check['message']);

        $this->expectException(InvalidArgumentException::class);
        app(DecideSiteAction::class)->decide($created['ticket_id'], $admin->id, true, 'approve');
    }

    private function signed(string $clientId, string $secret): TestResponse
    {
        $uri = '/api/v1/wallet';
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));

        return $this->get($uri, [
            'Accept' => 'application/json',
            'X-Pb-Client' => $clientId,
            'X-Pb-Timestamp' => $timestamp,
            'X-Pb-Nonce' => $nonce,
            'X-Pb-Signature' => HmacSignature::sign($secret, HmacSignature::canonical('GET', $uri, $timestamp, $nonce, '')),
        ]);
    }
}

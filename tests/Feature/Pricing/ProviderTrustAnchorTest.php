<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Application\Pipeline\Stages\ValidateStage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Exceptions\InvalidProviderException;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProviderTrustAnchorTest extends TestCase
{
    use RefreshDatabase;

    private function context(): TickContext
    {
        return new TickContext(TickTime::fromCarbon(CarbonImmutable::parse('2026-10-07T12:00:00Z')), [], [
            'wrong' => new ProviderConfigDto('wrong'),
            'tgju' => new ProviderConfigDto('tgju', isTrustAnchor: true),
        ]);
    }

    private function quote(string $key, int $minor): RawQuoteDto
    {
        return new RawQuoteDto($key, Instrument::Gold18Gram, Side::Last, Price::irr($minor), null);
    }

    public function test_anchor_is_validated_first_even_when_the_wrong_source_arrives_first(): void
    {
        $context = $this->context();
        $context->raw = [$this->quote('wrong', 26_640_000), $this->quote('tgju', 266_400_000)];

        (new ValidateStage)->handle($context);

        self::assertCount(1, $context->raw);
        self::assertSame('tgju', $context->raw[0]->providerKey);
        self::assertStringContainsString('cross_provider', $context->rejections[0]->reason);
    }

    public function test_nonanchor_bootstrap_waits_for_an_available_anchor(): void
    {
        $context = $this->context();
        $context->raw = [$this->quote('wrong', 266_400_000)];

        (new ValidateStage)->handle($context);

        self::assertSame([], $context->raw);
        self::assertSame('trust_anchor:unavailable', $context->rejections[0]->reason);
    }

    public function test_wrong_nonanchor_history_cannot_poison_the_anchor(): void
    {
        $context = $this->context();
        $old = new SnapshotDto('wrong', Instrument::Gold18Gram, Side::Last, Price::irr(26_640_000), $context->at->at);
        $context->previous[$old->key()] = $old;
        $context->raw = [$this->quote('tgju', 266_400_000)];

        (new ValidateStage)->handle($context);

        self::assertSame([], $context->rejections);
        self::assertSame(266_400_000, $context->raw[0]->price->minor);
    }

    public function test_trust_flag_round_trips_through_cached_provider_configuration(): void
    {
        $provider = Provider::query()->create(['key' => 'tgju', 'driver' => 'tgju', 'name' => 'TGJU', 'name_fa' => 'ت', 'is_active' => true]);
        app(ProviderAction::class)->setFlags($provider, ['is_trust_anchor' => true]);

        $config = app(ProviderQuery::class)->allActive()['tgju'];

        self::assertTrue(ProviderConfigDto::fromRow($config->toArray())->isTrustAnchor);
        self::assertSame(['tgju'], app(ProviderQuery::class)->trustAnchorKeys());
    }

    public function test_manual_source_cannot_be_marked_as_an_anchor(): void
    {
        $provider = Provider::query()->create(['key' => 'manual', 'driver' => 'manual', 'name' => 'Manual', 'name_fa' => 'دستی']);
        $this->expectException(InvalidProviderException::class);

        app(ProviderAction::class)->setFlags($provider, ['is_trust_anchor' => true]);
    }
}

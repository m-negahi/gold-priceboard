<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Dto\ProviderInputDto;
use App\Modules\Pricing\Domain\Enums\HealthState;
use App\Modules\Pricing\Domain\Enums\ProviderKind;
use App\Modules\Pricing\Domain\Enums\UpstreamUnit;
use App\Modules\Pricing\Domain\Exceptions\InvalidProviderException;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProviderActionTest extends TestCase
{
    use RefreshDatabase;

    private ProviderAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new ProviderAction;
    }

    /** @param array<string, mixed> $overrides */
    private function input(array $overrides = []): ProviderInputDto
    {
        return ProviderInputDto::fromArray(array_merge([
            'key' => 'tgju',
            'driver' => 'tgju',
            'name' => 'TGJU',
            'name_fa' => 'طلا و جواهر',
            'kind' => 'reference',
        ], $overrides));
    }

    // ── Sakht ───────────────────────────────────────────────────────

    public function test_create_persists_settings_and_leaves_next_poll_null(): void
    {
        $provider = $this->action->create($this->input([
            'kind' => 'platform',
            'upstream_unit' => 'toman',
            'options' => ['ua' => 'Mozilla/5.0'],
            'sort' => 3,
        ]));

        $this->assertSame('tgju', $provider->key);
        $this->assertSame(ProviderKind::Platform, $provider->kind);
        $this->assertSame(UpstreamUnit::Toman, $provider->upstream_unit);
        $this->assertSame(['ua' => 'Mozilla/5.0'], $provider->options);
        $this->assertSame(3, $provider->sort);

        // `null` ya'ni «tu avvalin tick poll she» — provider e taze nabayad
        // yek dore sabr kone.
        $this->assertNull($provider->next_poll_at);
    }

    public function test_create_rejects_a_key_already_used_by_a_soft_deleted_provider(): void
    {
        $provider = $this->action->create($this->input());
        $this->action->delete($provider);

        // 🔴 Estefade ye dobare az kelid, data ye do provider e motefavet ro
        // tu Influx va `price_changes` ru ham mirize.
        $this->expectException(InvalidProviderException::class);
        $this->action->create($this->input());
    }

    public function test_create_rejects_unknown_driver(): void
    {
        $this->expectExceptionMessageMatches('/درایور/');
        $this->action->create($this->input(['driver' => 'bourse24']));
    }

    public function test_create_rejects_malformed_key(): void
    {
        foreach (['', 'TGJU', 'tg ju', 'tgju.ir', 't'] as $bad) {
            try {
                $this->action->create($this->input(['key' => $bad]));
                $this->fail("kelid e «{$bad}» bayad rad mishod.");
            } catch (InvalidProviderException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * 🔴 In test aval «timeout bayad az fasele kamtar bashe» ro assert mikard
     * va BE-DOROSTI shekast: config e VAGHEI ye estjt tu seeder
     * `timeout = 20` ba fasele ye 15s ast، chun un endpoint andaze-giri
     * shode 6.2–9.1 saniye javab mide.
     *
     * Un shart eshtebah bud: halghe ye worker ba
     * `sleep $((15 - $(date +%s) % 15))` ru grid ham-taraz mishe، pas yek
     * tick e 20 saniye-i YEK tick ja mindaze، na inke drift e daemi besaze.
     */
    public function test_a_timeout_longer_than_the_poll_interval_is_allowed(): void
    {
        $provider = $this->action->create($this->input([
            'key' => 'estjt',
            'driver' => 'estjt',
            'timeout' => 20,
            'poll_interval_seconds' => 15,
        ]));

        $this->assertSame(20, $provider->timeout);
    }

    public function test_create_rejects_a_runaway_timeout(): void
    {
        // `Http::pool` montazer e HAME ye provider ha mimune: yek timeout e
        // 300 saniye-i ya'ni 5 daghighe ke HICH gheymati — az hich
        // provider-i — neveshte nemishe.
        $this->expectExceptionMessageMatches('/زمان انتظار/');
        $this->action->create($this->input(['timeout' => 300, 'poll_interval_seconds' => 60]));
    }

    public function test_create_rejects_a_zero_timeout(): void
    {
        $this->expectException(InvalidProviderException::class);
        $this->action->create($this->input(['timeout' => 0]));
    }

    public function test_create_rejects_poll_interval_out_of_range(): void
    {
        foreach ([1, 7_200] as $seconds) {
            try {
                $this->action->create($this->input([
                    'poll_interval_seconds' => $seconds,
                    'timeout' => 5,
                ]));
                $this->fail("fasele ye {$seconds}s bayad rad mishod.");
            } catch (InvalidProviderException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ── 🔴 Ghofl budan e `key` ──────────────────────────────────────

    public function test_update_refuses_to_rename_the_key(): void
    {
        $provider = $this->action->create($this->input());

        // `key` hoviat e in provider tu Influx (tag), Redis va
        // `price_changes` ast. Influx 2 rename e tag nadare, pas avaz
        // kardanesh kol e tarikhche ye chart ro YATIM mikone — bedun e hich
        // khata.
        $this->expectException(InvalidProviderException::class);
        $this->expectExceptionMessageMatches('/شناسهٔ تأمین‌کننده پس از ساخت/');

        $this->action->update($provider, $this->input(['key' => 'tgju2']));
    }

    public function test_update_keeps_the_key_and_applies_the_rest(): void
    {
        $provider = $this->action->create($this->input());

        $updated = $this->action->update($provider, $this->input([
            'name_fa' => 'تی‌جی‌جی‌یو',
            'kind' => 'platform',
            'in_chart' => false,
        ]));

        $this->assertSame('تی‌جی‌جی‌یو', $updated->name_fa);
        $this->assertSame(ProviderKind::Platform, $updated->kind);
        $this->assertFalse($updated->in_chart);
    }

    // ── `next_poll_at` ──────────────────────────────────────────────

    public function test_changing_the_poll_interval_clears_next_poll_at(): void
    {
        $provider = $this->action->create($this->input([
            'poll_interval_seconds' => 3_600,
            'timeout' => 10,
        ]));

        // Provider yek sa'at jolo zamanbandi shode.
        $provider->forceFill(['next_poll_at' => CarbonImmutable::now()->addHour()])->save();

        $updated = $this->action->update($provider, $this->input([
            'poll_interval_seconds' => 15,
            'timeout' => 10,
        ]));

        // Bedun e in, admin fasele ro 15s mikone va ta YEK SA'AT e ba'd hich
        // chizi ettefagh nemioftad — va fekr mikone tanzim kar nakarde.
        $this->assertNull($updated->next_poll_at);
    }

    public function test_an_unchanged_poll_interval_keeps_the_schedule(): void
    {
        $provider = $this->action->create($this->input());
        $at = CarbonImmutable::parse('2026-10-05T10:00:15Z');
        $provider->forceFill(['next_poll_at' => $at])->save();

        $updated = $this->action->update($provider, $this->input(['name' => 'TGJU v2']));

        // Edit kardan e esm nabayad zamanbandi ro reset kone — vagarna har
        // save ye admin yek tick e ezafi be upstream mizane.
        $this->assertNotNull($updated->next_poll_at);
        $this->assertSame($at->timestamp, $updated->next_poll_at->timestamp);
    }

    // ── Fa'al/gheyr e fa'al ────────────────────────────────────────

    public function test_reactivating_resets_the_health_state(): void
    {
        $provider = $this->action->create($this->input());

        // Provider dar hal e shekast khamoosh shode.
        $provider->forceFill([
            'is_active' => false,
            'consecutive_failures' => 20,
            'health_state' => HealthState::Down->value,
            'last_error' => 'cURL 35',
            'next_poll_at' => CarbonImmutable::now()->addHour(),
        ])->save();

        $updated = $this->action->activate($provider);

        // Bedun e reset, panel un ro GHERMEZ neshun mide ghabl az inke hatta
        // yek bar emtehan she — va `next_poll_at` e ghadim yek sa'at ta'ekhir
        // mindaze.
        $this->assertTrue($updated->is_active);
        $this->assertSame(0, $updated->consecutive_failures);
        $this->assertSame(HealthState::Unknown, $updated->health_state);
        $this->assertNull($updated->last_error);
        $this->assertNull($updated->next_poll_at);
    }

    public function test_deactivating_does_not_touch_health_counters(): void
    {
        $provider = $this->action->create($this->input());
        $provider->forceFill([
            'consecutive_failures' => 4,
            'health_state' => HealthState::Degraded->value,
        ])->save();

        $updated = $this->action->deactivate($provider);

        // Khamoosh kardan yek «reset» nist: amar e shekast bayad bemune ta
        // admin bedune chera khamoosh esh karde.
        $this->assertFalse($updated->is_active);
        $this->assertSame(4, $updated->consecutive_failures);
        $this->assertSame(HealthState::Degraded, $updated->health_state);
    }

    public function test_set_flags_only_touches_the_four_allowed_flags(): void
    {
        $provider = $this->action->create($this->input(['sort' => 7]));

        $updated = $this->action->setFlags($provider, [
            'on_board' => false,
            'in_comparison' => false,
            // In do ta bayad NADIDE gerefte shan — vagarna yek toggle e lista
            // mitune hoviat ya zamanbandi ro avaz kone.
            'key' => 'hacked',
            'poll_interval_seconds' => 999,
        ]);

        $this->assertFalse($updated->on_board);
        $this->assertFalse($updated->in_comparison);
        $this->assertSame('tgju', $updated->key);
        $this->assertSame(15, $updated->poll_interval_seconds);
        $this->assertSame(7, $updated->sort);
    }

    public function test_set_flags_with_nothing_allowed_is_a_no_op(): void
    {
        $provider = $this->action->create($this->input());

        $updated = $this->action->setFlags($provider, ['sort' => 99]);

        $this->assertSame(0, $updated->sort);
    }

    // ── Hazf ────────────────────────────────────────────────────────

    public function test_delete_is_soft_and_also_deactivates(): void
    {
        $provider = $this->action->create($this->input());

        $this->action->delete($provider);

        // Soft delete amdi-e: `price_changes` va tag haye Influx be in `key`
        // eshare mikonan.
        $this->assertSame(0, Provider::query()->count());
        $this->assertSame(1, Provider::withTrashed()->count());

        // `is_active=false` ham lazem ast: `scopeDueForPoll` ru model ejra
        // mishe (pas soft delete ro mibine), vali har query e kham e digari
        // momken ast nabine.
        $this->assertFalse((bool) Provider::withTrashed()->first()->is_active);
    }

    public function test_restore_brings_the_provider_back_active(): void
    {
        $provider = $this->action->create($this->input());
        $this->action->delete($provider);

        $restored = $this->action->restore(Provider::withTrashed()->first());

        $this->assertTrue($restored->is_active);
        $this->assertSame(1, Provider::query()->count());
    }

    public function test_a_deleted_provider_is_not_due_for_poll(): void
    {
        $provider = $this->action->create($this->input());
        $this->action->delete($provider);

        $due = Provider::query()->dueForPoll(CarbonImmutable::now())->get();

        $this->assertCount(0, $due);
    }
}

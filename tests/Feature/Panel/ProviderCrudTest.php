<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD e provider az rooye HTTP (P3-06) — form -> `ProviderAction` ba hamun
 * invariant ha (key e ghofl، driver e sabt-shode).
 */
final class ProviderCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'key' => 'wallgold',
            'driver' => 'mock',
            'name' => 'WallGold',
            'name_fa' => 'وال‌گلد',
            'kind' => 'reference',
            'upstream_unit' => 'toman',
            'timeout' => 10,
            'poll_interval_seconds' => 15,
            'freshness_seconds' => 900,
            'hide_after_seconds' => 600,
            'sort' => 30,
        ], $overrides);
    }

    public function test_an_admin_creates_a_provider_through_the_form(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('panel.providers.store'), $this->payload());

        $response->assertRedirect(route('panel.providers.index'));

        $this->assertDatabaseHas('providers', [
            'key' => 'wallgold',
            'driver' => 'mock',
            // Checkbox e submit-nashode false ast — na default e DTO (true).
            'is_active' => false,
        ]);
    }

    public function test_an_unknown_driver_is_rejected_by_the_action(): void
    {
        $this->actingAs($this->admin())
            ->from(route('panel.providers.create'))
            ->post(route('panel.providers.store'), $this->payload(['driver' => 'wordpress']))
            ->assertRedirect(route('panel.providers.create'))
            ->assertSessionHasErrors('key');

        $this->assertDatabaseMissing('providers', ['key' => 'wallgold']);
    }

    public function test_the_key_cannot_be_changed_on_update(): void
    {
        $this->actingAs($this->admin())->post(route('panel.providers.store'), $this->payload());

        // Route key ro ta'een mikone — hatta agar form `key` e dige-i
        // befreste، hamun key e route update mishe (va Action ghofl esh ro
        // negah midare).
        $this->actingAs($this->admin())
            ->put(route('panel.providers.update', 'wallgold'), $this->payload([
                'key' => 'hacked-key',
                'name' => 'WallGold 2',
            ]))
            ->assertRedirect(route('panel.providers.index'));

        $this->assertDatabaseHas('providers', ['key' => 'wallgold', 'name' => 'WallGold 2']);
        $this->assertDatabaseMissing('providers', ['key' => 'hacked-key']);
    }

    public function test_the_flags_endpoint_toggles_only_allowed_flags(): void
    {
        $this->actingAs($this->admin())->post(route('panel.providers.store'), $this->payload());

        $this->actingAs($this->admin())
            ->post(route('panel.providers.flags', 'wallgold'), [
                'is_active' => 1,
                // Yek soton e gheyr e flag — nabayad asar kone.
                'timeout' => 55,
            ])
            ->assertRedirect();

        $provider = Provider::query()->where('key', 'wallgold')->firstOrFail();

        self::assertTrue($provider->is_active);
        self::assertSame(10, (int) $provider->timeout, 'flags nabayad soton e tanzimi ro avaz kone.');
    }

    public function test_the_list_page_shows_providers(): void
    {
        $this->actingAs($this->admin())->post(route('panel.providers.store'), $this->payload());

        $this->actingAs($this->admin())
            ->get(route('panel.providers.index'))
            ->assertOk()
            ->assertSee('وال‌گلد')
            ->assertSee('wallgold');
    }

    public function test_a_non_admin_gets_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('panel.providers.index'))
            ->assertNotFound();
    }
}

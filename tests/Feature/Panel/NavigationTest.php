<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Panel\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sidebar e panel (data-driven) — har route vaghe'an vojud dare va
 * zir-menu ye «پایش» (tick / منابع / API) sar e jash ast.
 *
 * 🔴 Noskhe ye ghabli ~13 esm e route e proje ye digar ro dasht va
 * `route()` e sidebar ru HAR safhe RouteNotFound midad. Pas har route —
 * bache ha ham — inja resolve mishe.
 */
final class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{key: string, label: string, route: ?string}> hame ye item ha + bache ha، saf */
    private function flatItems(): array
    {
        $flat = [];

        foreach (Navigation::groups() as $group) {
            foreach ($group['items'] as $item) {
                $flat[] = $item;

                foreach ($item['children'] ?? [] as $child) {
                    $flat[] = $child;
                }
            }
        }

        return $flat;
    }

    public function test_every_route_in_the_sidebar_exists(): void
    {
        foreach ($this->flatItems() as $item) {
            if ($item['route'] === null) {
                continue;
            }

            self::assertTrue(Route::has($item['route']), "route e «{$item['route']}» ({$item['label']}) vojud nadare.");
        }
    }

    public function test_monitoring_is_a_parent_with_ticks_upstream_and_api(): void
    {
        $monitor = null;

        foreach (Navigation::groups() as $group) {
            foreach ($group['items'] as $item) {
                if ($item['key'] === 'monitor') {
                    $monitor = $item;
                }
            }
        }

        self::assertNotNull($monitor, 'item e «monitor» tu sidebar nist.');
        self::assertSame(
            ['panel.monitor.ticks', 'panel.monitor.upstream', 'panel.monitor.api'],
            array_column($monitor['children'] ?? [], 'route'),
        );
    }

    public function test_every_key_is_unique(): void
    {
        // Key ha `current` ro match mikonan — key e tekrari ya'ni do item
        // ba ham active mishan.
        $keys = array_column($this->flatItems(), 'key');

        self::assertSame($keys, array_values(array_unique($keys)));
    }

    public function test_a_monitor_child_page_renders_the_submenu_with_its_child_active(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('panel.monitor.upstream'))
            ->assertOk()
            // Har se bache link-and…
            ->assertSee(route('panel.monitor.ticks'), false)
            ->assertSee(route('panel.monitor.api'), false)
            // …va bache ye fe'li `aria-current` dare.
            ->assertSee('aria-current="page"', false);
    }
}

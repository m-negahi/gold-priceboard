<?php

declare(strict_types=1);

namespace App\Modules\Panel\Support;

use App\Modules\Access\Application\Queries\AccessQuery;
use App\Modules\Access\Domain\PermissionCatalog;

/**
 * Sidebar be shekl-e data na markup, ta rail-e baste, list-e baz va breadcrumb
 * hame az yek manba' bekhunand va natoonand az ham fasele begirand.
 *
 * `route` baraye bakhshi ke hanuz vojood nadare `null` ast: item DISABLED
 * render mishe (badge e «به‌زودی») be jaye inke gheyb beshe — shekl-e panel
 * dar hin-e sakht sadegh mimune.
 *
 * ⚠️ Bazneveshte baraye IN proje (2026-10-06): noskhe ye ghabli ~13 esm e
 * route e proje ye Desktop ro dasht (`panel.pages.index`، …) va `route()` e
 * sidebar ru har safhe RouteNotFound midad. Inja faghat naghshe ye PLAN e
 * hamin proje ast: Phase 3 (panel e pricing)، Phase 4 (wallet/API)، Phase 5
 * (site/media). Har task-i ke route esh sakhte shod، hamin ja por mishe.
 */
final class Navigation
{
    /**
     * `children` (ekhtiari): zir-menu. `route` e parent hamun avvalin bache-st
     * (rail e baste bache ha ro neshun nemide va click mostaghim unja mire).
     * Parent vaghti active e ke `current` ba key e khodesh ya yeki az bache ha
     * match kone.
     *
     * @return list<array{label: string, items: list<array{key: string, label: string, icon: string, route: ?string, children?: list<array{key: string, label: string, route: string}>}>}>
     */
    public static function groups(): array
    {
        $groups = [
            [
                'label' => 'قیمت‌گذاری',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'میزکار', 'icon' => 'gauge', 'route' => 'panel.dashboard'],
                    ['key' => 'providers', 'label' => 'منابع قیمت', 'icon' => 'feed', 'route' => 'panel.providers.index'],
                    ['key' => 'priorities', 'label' => 'اولویت و قیمت دستی', 'icon' => 'layers', 'route' => 'panel.priorities.index'],
                    [
                        'key' => 'monitor', 'label' => 'پایش', 'icon' => 'eye', 'route' => 'panel.monitor.ticks',
                        'children' => [
                            ['key' => 'monitor.ticks', 'label' => 'پایش tick', 'route' => 'panel.monitor.ticks'],
                            ['key' => 'monitor.upstream', 'label' => 'پایش منابع', 'route' => 'panel.monitor.upstream'],
                            ['key' => 'monitor.api', 'label' => 'پایش API', 'route' => 'panel.monitor.api'],
                        ],
                    ],
                    ['key' => 'matrix', 'label' => 'ماتریس نمادها', 'icon' => 'coins', 'route' => 'panel.matrix'],
                    ['key' => 'charts', 'label' => 'نمودار قیمت', 'icon' => 'chart', 'route' => 'panel.charts'],
                ],
            ],
            [
                'label' => 'مشتریان و مالی', // Phase 4
                'items' => [
                    ['key' => 'customers', 'label' => 'مشتریان', 'icon' => 'users', 'route' => 'panel.users.index'],
                    ['key' => 'finance', 'label' => 'دفتر حساب و مالی', 'icon' => 'wallet', 'route' => 'panel.finance.index'],
                    ['key' => 'plans', 'label' => 'پلن‌ها', 'icon' => 'layers', 'route' => 'panel.plans.index'],
                    ['key' => 'sites', 'label' => 'سایت‌ها', 'icon' => 'world', 'route' => 'panel.sites.index'],
                ],
            ],
            [
                // Goruh e JODA (khaste ye sahebe proje): har darkhast e karbar
                // ticket-e — ertegha ye sath، ta'eed e site، poshtibani.
                'label' => 'پشتیبانی',
                'items' => [
                    ['key' => 'tickets', 'label' => 'تیکت‌ها', 'icon' => 'ticket', 'route' => 'panel.tickets.index'],
                ],
            ],
            [
                'label' => 'سایت', // Phase 5 + P3-00 (Media)
                'items' => [
                    ['key' => 'media', 'label' => 'رسانه', 'icon' => 'image', 'route' => 'panel.media.index'],
                    ['key' => 'seo', 'label' => 'سئو', 'icon' => 'search', 'route' => 'panel.seo.index'],
                ],
            ],
            [
                'label' => 'تنظیمات',
                'items' => [
                    ['key' => 'access', 'label' => 'نقش و دسترسی', 'icon' => 'cog', 'route' => 'panel.access.index'],
                ],
            ],
        ];

        $user = auth()->user();
        if ($user === null) {
            return $groups;
        }
        $permissions = app(AccessQuery::class)->permissions($user);
        $visible = static fn (?string $route): bool => in_array('*', $permissions, true)
            || ($route !== null && in_array(PermissionCatalog::forRoute($route), $permissions, true));
        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                if (isset($item['children'])) {
                    $item['children'] = array_values(array_filter($item['children'], fn (array $child): bool => $visible($child['route'])));
                    $item['route'] = $item['children'][0]['route'] ?? null;
                }
            }
            unset($item);
            $group['items'] = array_values(array_filter($group['items'], fn (array $item): bool => $visible($item['route'])));
        }
        unset($group);

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }
}

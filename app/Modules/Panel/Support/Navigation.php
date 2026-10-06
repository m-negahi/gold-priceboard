<?php

declare(strict_types=1);

namespace App\Modules\Panel\Support;

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
        return [
            [
                'label' => 'قیمت‌گذاری',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'میزکار', 'icon' => 'gauge', 'route' => 'panel.dashboard'],
                    ['key' => 'providers', 'label' => 'منابع قیمت', 'icon' => 'feed', 'route' => 'panel.providers.index'],
                    ['key' => 'health', 'label' => 'سلامت منابع', 'icon' => 'pulse', 'route' => 'panel.health'],
                    [
                        'key' => 'monitor', 'label' => 'پایش', 'icon' => 'eye', 'route' => 'panel.monitor.ticks',
                        'children' => [
                            ['key' => 'monitor.ticks', 'label' => 'پایش tick', 'route' => 'panel.monitor.ticks'],
                            ['key' => 'monitor.upstream', 'label' => 'پایش منابع', 'route' => 'panel.monitor.upstream'],
                            ['key' => 'monitor.api', 'label' => 'پایش API', 'route' => 'panel.monitor.api'],
                        ],
                    ],
                    ['key' => 'matrix', 'label' => 'ماتریس نمادها', 'icon' => 'coins', 'route' => 'panel.matrix'],
                    ['key' => 'manual', 'label' => 'قیمت دستی', 'icon' => 'pencil', 'route' => 'panel.manual.index'],
                ],
            ],
            [
                'label' => 'مشتریان و مالی', // Phase 4
                'items' => [
                    ['key' => 'customers', 'label' => 'مشتریان', 'icon' => 'users', 'route' => null],
                    ['key' => 'wallets', 'label' => 'کیف پول', 'icon' => 'wallet', 'route' => null],
                ],
            ],
            [
                'label' => 'سایت', // Phase 5 + P3-00 (Media)
                'items' => [
                    ['key' => 'media', 'label' => 'رسانه', 'icon' => 'image', 'route' => null],
                    ['key' => 'seo', 'label' => 'سئو', 'icon' => 'search', 'route' => null],
                ],
            ],
            [
                'label' => 'تنظیمات',
                'items' => [
                    ['key' => 'settings', 'label' => 'تنظیمات', 'icon' => 'cog', 'route' => null],
                ],
            ],
        ];
    }
}

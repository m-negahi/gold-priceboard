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
     * @return list<array{label: string, items: list<array{key: string, label: string, icon: string, route: ?string}>}>
     */
    public static function groups(): array
    {
        return [
            [
                'label' => 'قیمت‌گذاری',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'میزکار', 'icon' => 'gauge', 'route' => 'panel.dashboard'],
                    // P3-06/P3-07 — provider ha + spread.
                    ['key' => 'providers', 'label' => 'منابع قیمت', 'icon' => 'feed', 'route' => null],
                    // P3-08/P3-09 — salamat + payesh e tick.
                    ['key' => 'health', 'label' => 'سلامت منابع', 'icon' => 'pulse', 'route' => null],
                    ['key' => 'monitor', 'label' => 'پایش زنده', 'icon' => 'eye', 'route' => null],
                    // P3-10 — matrix e provider × symbol.
                    ['key' => 'matrix', 'label' => 'ماتریس نمادها', 'icon' => 'coins', 'route' => null],
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

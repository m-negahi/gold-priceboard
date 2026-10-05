<?php

declare(strict_types=1);

namespace App\Modules\Panel\Support;

/**
 * Sidebar be shekl-e data na markup, ta rail-e baste, list-e baz va breadcrumb hame az yek manba'
 * bekhunand va natoonand az ham fasele begirand.
 *
 * `route` baraye bakhshi ke hanuz vojood nadare momkene null bashe: item disabled render mishe
 * be jaye inke gheyb beshe, ke shekl-e panel ro dar hin-e sakht sadegh negah midare.
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
                    ['key' => 'instruments', 'label' => 'ابزارها', 'icon' => 'coins', 'route' => 'panel.instruments.index'],
                    ['key' => 'sources', 'label' => 'منابع قیمت', 'icon' => 'feed', 'route' => 'panel.sources.index'],
                    // ADR-0050: «نگاشت نمادها» ba «پایش زنده» yeki shod؛ item-e joda-gane hazf shod.
                    ['key' => 'monitor', 'label' => 'پایش زنده', 'icon' => 'pulse', 'route' => 'panel.monitor'],
                ],
            ],
            [
                'label' => 'محتوا',
                'items' => [
                    ['key' => 'pages', 'label' => 'صفحات', 'icon' => 'page', 'route' => 'panel.pages.index'],
                    ['key' => 'articles', 'label' => 'مقاله‌ها', 'icon' => 'article', 'route' => 'panel.articles.index'],
                    ['key' => 'article-categories', 'label' => 'دسته‌بندی مقالات', 'icon' => 'tag', 'route' => 'panel.article-categories.index'],
                    ['key' => 'menus', 'label' => 'منوها', 'icon' => 'menu', 'route' => 'panel.menus.index'],
                    ['key' => 'media', 'label' => 'رسانه', 'icon' => 'image', 'route' => 'panel.media.index'],
                ],
            ],
            [
                'label' => 'سئو',
                'items' => [
                    ['key' => 'seo', 'label' => 'مرور سئو', 'icon' => 'search', 'route' => 'panel.seo.overview'],
                    ['key' => 'redirects', 'label' => 'ریدایرکت‌ها', 'icon' => 'arrow', 'route' => 'panel.redirects.index'],
                ],
            ],
            [
                'label' => 'مشتریان',
                'items' => [
                    ['key' => 'merchants', 'label' => 'مشتریان', 'icon' => 'users', 'route' => null],
                    ['key' => 'sites', 'label' => 'سایت‌ها', 'icon' => 'globe', 'route' => null],
                    ['key' => 'tickets', 'label' => 'تیکت‌ها', 'icon' => 'ticket', 'route' => null],
                    ['key' => 'messages', 'label' => 'پیام‌ها', 'icon' => 'mail', 'route' => 'panel.messages.index'],
                ],
            ],
            [
                'label' => 'مالی',
                'items' => [
                    ['key' => 'plans', 'label' => 'پلن‌ها', 'icon' => 'tag', 'route' => null],
                    ['key' => 'wallets', 'label' => 'کیف پول', 'icon' => 'wallet', 'route' => null],
                    ['key' => 'invoices', 'label' => 'فاکتورها', 'icon' => 'receipt', 'route' => null],
                ],
            ],
            [
                'label' => 'گزارش‌ها',
                'items' => [
                    ['key' => 'provider-report', 'label' => 'گزارش منابع', 'icon' => 'pulse', 'route' => 'panel.reports.providers'],
                ],
            ],
            [
                'label' => 'تنظیمات',
                'items' => [
                    ['key' => 'settings', 'label' => 'تنظیمات', 'icon' => 'cog', 'route' => 'panel.settings.index'],
                ],
            ],
        ];
    }
}

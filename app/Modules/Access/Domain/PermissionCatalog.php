<?php

declare(strict_types=1);

namespace App\Modules\Access\Domain;

final class PermissionCatalog
{
    public const LABELS = [
        'dashboard.view' => 'مشاهده میزکار',
        'providers.view' => 'مشاهده منابع', 'providers.manage' => 'ویرایش منابع',
        'pricing.view' => 'مشاهده قیمت و اولویت', 'pricing.manage' => 'ویرایش قیمت و اولویت',
        'monitoring.view' => 'مشاهده پایش و ابزارهای فنی', 'monitoring.manage' => 'مدیریت صف و ابزارهای فنی',
        'users.view' => 'مشاهده مشتریان', 'users.manage' => 'ویرایش مشتریان',
        'finance.view' => 'مشاهده امور مالی', 'finance.manage' => 'ثبت و اصلاح اسناد مالی',
        'plans.view' => 'مشاهده پلن‌ها', 'plans.manage' => 'ویرایش پلن‌ها',
        'sites.view' => 'مشاهده سایت‌ها', 'sites.manage' => 'تأیید و مدیریت سایت‌ها',
        'tickets.view' => 'مشاهده تیکت‌ها', 'tickets.manage' => 'پاسخ و تصمیم تیکت',
        'media.view' => 'مشاهده رسانه', 'media.manage' => 'ویرایش رسانه',
        'seo.view' => 'مشاهده سئو', 'seo.manage' => 'ویرایش سئو',
        'access.manage' => 'مدیریت نقش و دسترسی',
    ];

    public static function forRoute(string $name, bool $write = false): ?string
    {
        $write = $write || str_ends_with($name, '.create') || str_ends_with($name, '.edit') || $name === 'panel.providers.spreads';
        $area = match (true) {
            str_starts_with($name, 'panel.access.'), $name === 'panel.users.role' => 'access',
            str_starts_with($name, 'panel.users.wallet'), str_starts_with($name, 'panel.finance.'), str_starts_with($name, 'panel.invoices.') => 'finance',
            str_starts_with($name, 'panel.users.') => 'users',
            str_starts_with($name, 'panel.providers.'), $name === 'panel.health' => 'providers',
            str_starts_with($name, 'panel.manual.'), str_starts_with($name, 'panel.priorities.'), str_starts_with($name, 'panel.charts'), $name === 'panel.matrix' => 'pricing',
            str_starts_with($name, 'panel.tickets.') => 'tickets',
            str_starts_with($name, 'panel.media.') => 'media',
            str_starts_with($name, 'panel.seo.') => 'seo',
            str_starts_with($name, 'panel.plans.') => 'plans',
            str_starts_with($name, 'panel.sites.') => 'sites',
            str_starts_with($name, 'panel.monitor.'), str_starts_with($name, 'horizon.'), str_starts_with($name, 'telescope.') => 'monitoring',
            $name === 'panel.dashboard' => 'dashboard',
            default => null,
        };

        return match ($area) {
            null => null,
            'access' => 'access.manage',
            'dashboard' => 'dashboard.view',
            default => $area.($write ? '.manage' : '.view'),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Common\Enums;

use App\Modules\Common\Traits\EnumTrait;

enum QueueName: string
{
    use EnumTrait;

    case Default = 'default';
    case Notifications = 'notifications';
    case Verification = 'verification';

    public static function labels(): array
    {
        return [
            self::Default->value => 'عمومی',
            self::Notifications->value => 'اطلاع‌رسانی',
            self::Verification->value => 'استعلام',
        ];
    }
}

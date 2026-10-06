<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $symbol
 * @property bool $manual_only
 * @property bool $manual_fallback_enabled
 * @property string $manual_auto_source
 */
class SymbolPriceSetting extends Model
{
    protected $fillable = ['symbol', 'manual_only', 'manual_fallback_enabled', 'manual_auto_source'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['manual_only' => 'boolean', 'manual_fallback_enabled' => 'boolean'];
    }
}

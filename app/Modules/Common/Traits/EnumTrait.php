<?php

declare(strict_types=1);

namespace App\Modules\Common\Traits;

use BackedEnum;

/**
 * Adds names()/values()/label() helpers to backed enums.
 * The enum must implement the static labels(): array method ([value => label]).
 *
 * @mixin BackedEnum
 */
trait EnumTrait
{
    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * @return array<int, int|string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human label for this enum value.
     */
    public function label(): string
    {
        return static::labels()[$this->value] ?? (string) $this->value;
    }

    /**
     * All labels as [value => label]. Must be implemented by each enum.
     *
     * @return array<int|string, string>
     */
    abstract public static function labels(): array;
}

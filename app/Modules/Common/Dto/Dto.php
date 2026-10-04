<?php

declare(strict_types=1);

namespace App\Modules\Common\Dto;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Base Data Transfer Object.
 *
 * One DTO per entity (not separate Create/Update DTOs). All properties are
 * nullable with a `null` default; `from()` maps snake_case request data to
 * camelCase properties. Use toArrayWithoutNulls() for create and
 * exceptWithoutNulls(['id']) for partial updates.
 *
 * @implements Arrayable<string, mixed>
 */
abstract class Dto implements Arrayable
{
    protected int|string|null $id = null;

    public function getId(): int|string|null
    {
        return $this->id;
    }

    /**
     * @return $this
     */
    public function setId(int|string|null $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    abstract public static function from(array $data): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArrayWithoutNulls(): array
    {
        return array_filter($this->toArray(), static fn ($value) => $value !== null);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->toArray(), array_flip($keys));
    }

    /**
     * Get all properties except specified keys, excluding null values.
     * Useful for partial updates where null means "not provided".
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function exceptWithoutNulls(array $keys): array
    {
        return array_filter(
            $this->except($keys),
            static fn ($value) => $value !== null
        );
    }

    public function has(string $property): bool
    {
        return property_exists($this, $property) && $this->{$property} !== null;
    }

    /**
     * @param  array<string, mixed>  $existingData
     * @param  array<string, mixed>  $newData
     */
    public static function merge(array $existingData, array $newData): static
    {
        return static::from(array_merge($existingData, $newData));
    }
}

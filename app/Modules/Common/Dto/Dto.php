<?php

declare(strict_types=1);

namespace App\Modules\Common\Dto;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Base Data Transfer Object.
 *
 * Yek DTO be ezaye har entity (na DTO ye jodagane baraye Create/Update).
 * Hame ye property ha nullable-and ba default e `null`؛ `from()` data ye
 * snake_case e request ro be property haye camelCase map mikone.
 *
 * Baraye sakht: `toArrayWithoutNulls()`. Baraye update e joz'i:
 * `exceptWithoutNulls(['id'])`.
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
     * Hame ye property ha be joz kelid haye dade-shode، bedun e `null` ha.
     *
     * Baraye update e JOZ'I: unja `null` ya'ni «frestade nashode»، na
     * «khali kon». Agar `null` ha ham befrestim، yek form e nime-kare
     * soton haye dast-nakhorde ro PAK mikone.
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

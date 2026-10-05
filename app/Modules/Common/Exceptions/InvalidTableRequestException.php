<?php

declare(strict_types=1);

namespace App\Modules\Common\Exceptions;

use InvalidArgumentException;

/**
 * Darkhast-e datatable-i ke `TableQuery` nemitoone ejra kone: sort-e kharej az whitelist ya
 * meghdar-e filter-e kharej az list. Exception-e domain-e, na ValidationException — yek Query
 * nabayad bedoone ke HTTP chi-e. Tabdil be 422 ba shekl-e Laravel (`message` + `errors[field]`)
 * yek ja tu bootstrap/app.php anjam mishe, pas har controller-i hamun javab ro mide.
 */
abstract class InvalidTableRequestException extends InvalidArgumentException
{
    /** Field-i ke tu `errors` gozaresh mishe (`sort`, `filters.kind`). */
    abstract public function field(): string;
}

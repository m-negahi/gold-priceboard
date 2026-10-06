<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/** @property list<string> $permissions */
class Role extends Model
{
    protected $table = 'access_roles';

    protected $fillable = ['name', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}

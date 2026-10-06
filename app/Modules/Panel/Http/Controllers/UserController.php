<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Auth\Application\Queries\UserQuery;
use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Domain\Enums\UserStatus;
use App\Modules\Panel\Queries\UserDetailQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Karbaran e panel (Phase 6) — controller e NAZOK: filter ha validate
 * mishan (meghdar e nashenas 422/redirect، na nadide gereftan e bi-seda)
 * va kol e khundan az `UserQuery`.
 */
final readonly class UserController
{
    public function __construct(
        private UserQuery $users,
        private UserDetailQuery $detail,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
            'role' => ['nullable', Rule::in(['admin', 'customer'])],
            'status' => ['nullable', Rule::in(UserStatus::values())],
            'level' => ['nullable', Rule::in(UserLevel::values())],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return view('panel::users.index', [
            'users' => $this->users->panelList($filters, (int) ($filters['page'] ?? 1)),
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'role' => (string) ($filters['role'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'level' => (string) ($filters['level'] ?? ''),
            ],
            'statuses' => UserStatus::labels(),
            'levels' => UserLevel::labels(),
        ]);
    }

    public function show(int $user): View
    {
        return view('panel::users.show', $this->detail->show($user));
    }
}

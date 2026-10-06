<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Application\Actions\ManageAccessAction;
use App\Modules\Access\Application\Queries\AccessQuery;
use App\Modules\Access\Domain\PermissionCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class AccessController
{
    public function __construct(private AccessQuery $query, private ManageAccessAction $action) {}

    public function index(): View
    {
        return view('access::index', [...$this->query->page(), 'permissions' => PermissionCatalog::LABELS]);
    }

    public function save(Request $request, ?int $role = null): RedirectResponse
    {
        $input = $request->only('name', 'permissions');
        $input['permissions'] ??= [];
        $this->action->saveRole($request->user(), $role, $input);

        return back()->with('success', 'نقش ذخیره شد.');
    }

    public function assign(Request $request, int $user): RedirectResponse
    {
        $data = $request->validate(['roles' => ['nullable', 'array'], 'full_access' => ['nullable', 'boolean']]);
        $this->action->assign($request->user(), $user, $data['roles'] ?? [], $request->boolean('full_access'));

        return back()->with('success', 'دسترسی مدیر ذخیره شد.');
    }

    public function destroy(Request $request, int $role): RedirectResponse
    {
        $this->action->deleteRole($request->user(), $role);

        return back()->with('success', 'نقش حذف شد.');
    }
}

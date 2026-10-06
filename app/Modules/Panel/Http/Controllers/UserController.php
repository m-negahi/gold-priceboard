<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Auth\Application\Actions\UserAdminAction;
use App\Modules\Auth\Application\Queries\UserQuery;
use App\Modules\Auth\Domain\Enums\CustomerType;
use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Domain\Enums\UserStatus;
use App\Modules\Auth\Domain\Exceptions\UserAdminException;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Common\Helpers\Money;
use App\Modules\Panel\Queries\UserDetailQuery;
use App\Modules\Wallet\Application\Actions\AdjustWalletAction;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use Illuminate\Http\RedirectResponse;
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
        private UserAdminAction $admin,
        private AdjustWalletAction $adjust,
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

    public function show(Request $request, int $user): View
    {
        return view('panel::users.show', [
            ...$this->detail->show($user),
            // Safhe ye KHOD e admin: form e disable neshun dade nemishe (Action
            // ham rad mikone — `selfDisable`).
            'isSelf' => $request->user()?->getAuthIdentifier() === $user,
        ]);
    }

    public function create(): View
    {
        return view('panel::users.create', [
            'customerTypes' => CustomerType::labels(),
        ]);
    }

    /**
     * 🔴 Faghat field haye `validated()` be Action miran — `is_admin`/
     * `status`/`level` e dast-kari-shode tu request hich ja masraf nemishan.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in(['admin', 'customer'])],
            'customer_type' => ['required', Rule::in(CustomerType::values())],
        ]);

        try {
            $user = $this->admin->create(
                $data['phone'],
                $data['name'] ?? null,
                $data['role'] === 'admin',
                CustomerType::from($data['customer_type']),
            );
        } catch (UserAdminException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return redirect()->route('panel.users.show', $user->id)->with('toast', [
            'kind' => 'success',
            'title' => 'کاربر ساخته شد',
            'message' => 'با اولین ورود OTP شماره‌اش تأیید می‌شود.',
        ]);
    }

    public function role(Request $request, int $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'customer'])],
        ]);

        /** @var User $target */
        $target = $this->users->findOrFail($user);
        /** @var User $actor */
        $actor = $request->user();

        try {
            $this->admin->setRole($target, $data['role'] === 'admin', $actor);
        } catch (UserAdminException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('panel.users.show', $target->id)->with('toast', [
            'kind' => 'success',
            'title' => 'نقش تغییر کرد',
            'message' => $data['role'] === 'admin' ? 'کاربر اکنون ادمین است.' : 'کاربر اکنون مشتری است.',
        ]);
    }

    /** P6-05 — login، session haye baz va HAME ye site ha ru API baste mishan. */
    public function disable(Request $request, int $user): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $target */
        $target = $this->users->findOrFail($user);
        /** @var User $actor */
        $actor = $request->user();

        try {
            $this->admin->disable($target, $data['reason'], $actor);
        } catch (UserAdminException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('panel.users.show', $target->id)->with('toast', [
            'kind' => 'success',
            'title' => 'کاربر غیرفعال شد',
            'message' => 'ورود، نشست‌های باز و همهٔ سایت‌هایش روی API بسته شدند.',
        ]);
    }

    public function enable(int $user): RedirectResponse
    {
        /** @var User $target */
        $target = $this->users->findOrFail($user);

        $this->admin->enable($target);

        return redirect()->route('panel.users.show', $target->id)->with('toast', [
            'kind' => 'success',
            'title' => 'کاربر فعال شد',
            'message' => 'دسترسی ورود و API برگشت.',
        ]);
    }

    /**
     * P6-06 — sharj/kasr e dasti. Mablagh TOMAN e matni ast va ba
     * `Money::tomanToRial` (string/bcmath) be rial mire — hich float.
     */
    public function adjustWallet(Request $request, int $user): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'string', 'max:32'],
            'note' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $amountMinor = Money::tomanToRial($data['amount']);

        if ($amountMinor === null || $amountMinor <= 0) {
            return back()->withErrors(['amount' => 'مبلغ معتبر نیست (تومان، حداکثر یک رقم اعشار).'])->withInput();
        }

        /** @var User $target */
        $target = $this->users->findOrFail($user);
        /** @var User $actor */
        $actor = $request->user();

        try {
            $this->adjust->execute($target->id, $data['direction'], $amountMinor, $data['note'], $actor->id, $data['idempotency_key']);
        } catch (WalletException $e) {
            $message = match ($e->getCode()) {
                WalletException::INSUFFICIENT => 'موجودی کیف پول کافی نیست.',
                WalletException::BLOCKED => 'کیف پول مسدود است.',
                default => 'ثبت تراکنش ممکن نشد.',
            };

            return back()->withErrors(['amount' => $message])->withInput();
        }

        return redirect()->route('panel.users.show', $target->id)->with('toast', [
            'kind' => 'success',
            'title' => $data['direction'] === 'credit' ? 'کیف پول شارژ شد' : 'از کیف پول کسر شد',
            'message' => Money::rialToToman($amountMinor).' تومان — ثبت در دفتر کل.',
        ]);
    }
}

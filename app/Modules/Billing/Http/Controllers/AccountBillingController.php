<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Api\Application\Actions\CreateSiteAction;
use App\Modules\Api\Application\Actions\PurchasePlanAction;
use App\Modules\Api\Application\Queries\ApiClientQuery;
use App\Modules\Api\Application\Queries\ApiPlanQuery;
use App\Modules\Billing\Application\Actions\TopupPaymentAction;
use App\Modules\Billing\Application\Queries\CustomerBillingQuery;
use App\Modules\Common\Helpers\Money;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

final readonly class AccountBillingController
{
    public function __construct(
        private CustomerBillingQuery $query,
        private CreateSiteAction $sites,
        private PurchasePlanAction $purchase,
        private TopupPaymentAction $topups,
        private ApiClientQuery $clients,
        private ApiPlanQuery $plans,
    ) {}

    public function dashboard(Request $request): View
    {
        return $this->page($request, 'dashboard');
    }

    public function sites(Request $request): View
    {
        return $this->page($request, 'sites');
    }

    public function subscriptions(Request $request): View
    {
        return $this->page($request, 'subscriptions');
    }

    public function wallet(Request $request): View
    {
        return $this->page($request, 'wallet');
    }

    public function usage(Request $request): View
    {
        return $this->page($request, 'usage');
    }

    public function storeSite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'website_url' => ['required', 'url:http,https', 'max:190'],
        ]);
        try {
            $created = $this->sites->execute((int) $request->user()->id, $data['name'], $data['website_url']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['website_url' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('account.billing.sites')
            ->with('site_credentials', [
                'client_id' => $created['client']->client_id,
                'secret' => $created['secret'],
            ]);
    }

    public function purchase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'integer', 'min:1'],
            'plan_id' => ['required', 'integer', 'min:1'],
            'purchase_key' => ['required', 'uuid'],
        ]);
        $site = $this->clients->findForUserOrFail((int) $request->user()->id, (int) $data['site_id']);
        $plan = $this->plans->modelOrFail((int) $data['plan_id']);
        try {
            $this->purchase->purchase($site, $plan, $data['purchase_key']);
        } catch (WalletException|InvalidArgumentException $exception) {
            return back()->withErrors(['plan_id' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('account.billing.subscriptions')->with('toast', [
            'kind' => 'success', 'title' => 'اشتراک ثبت شد', 'message' => 'مبلغ ریالی از کیف پول کسر شد.',
        ]);
    }

    public function startTopup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount_rial' => ['required', 'string', 'max:32'],
            'request_key' => ['required', 'uuid'],
        ]);
        $amount = Money::rialFromInput($data['amount_rial']);
        if ($amount === null || $amount <= 1000) {
            return back()->withErrors(['amount_rial' => 'مبلغ باید بیش از 1000 ریال باشد.'])->withInput();
        }
        try {
            $started = $this->topups->start(
                (int) $request->user()->id, $amount, $data['request_key'],
                route('account.billing.topup.callback'),
            );
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()->withErrors(['amount_rial' => $exception->getMessage()]);
        }

        return redirect()->away($started['url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        // Vandar `token` mifreste — alfanumeric e kutah.
        $data = $request->validate([
            'token' => ['required', 'regex:/^[A-Za-z0-9_-]{1,128}$/'],
        ]);
        try {
            $payment = $this->topups->verify((string) $data['token']);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return redirect()->route('account.billing.wallet')->withErrors(['payment' => $exception->getMessage()]);
        }
        if ($payment->status !== 'verified') {
            return redirect()->route('account.billing.wallet')->withErrors(['payment' => 'مبلغ تأییدشده با درخواست برابر نیست؛ با پشتیبانی تماس بگیرید.']);
        }

        return redirect()->route('account.billing.wallet')->with('toast', [
            'kind' => 'success', 'title' => 'کیف پول شارژ شد', 'message' => 'پرداخت پس از استعلام سمت سرور ثبت شد.',
        ]);
    }

    public function invoice(Request $request, int $invoice): View
    {
        return view('account.billing.invoice', [
            'invoice' => $this->query->invoice((int) $request->user()->id, $invoice),
        ]);
    }

    private function page(Request $request, string $section): View
    {
        $userId = (int) $request->user()->id;

        return view('account.billing.index', [
            'section' => $section,
            'dashboard' => $this->query->dashboard($userId),
            'sites' => $this->query->sites($userId),
            'plans' => $this->query->plans(),
            'subscriptions' => $this->query->subscriptions($userId),
            'wallet' => $this->query->wallet($userId),
            'usage' => $this->query->usage($userId),
            'purchaseKey' => (string) Str::uuid(),
            'topupKey' => (string) Str::uuid(),
        ]);
    }
}

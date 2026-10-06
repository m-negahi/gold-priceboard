<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Auth\Application\Queries\UserQuery;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Billing\Application\Actions\IssueInvoiceAction;
use App\Modules\Common\Helpers\Money;
use App\Modules\Finance\Application\Actions\SettleGatewayPaymentAction;
use App\Modules\Finance\Application\Queries\FinanceInvoiceQuery;
use App\Modules\Finance\Application\Queries\FinanceLedgerQuery;
use App\Modules\Finance\Application\Queries\FinanceMonthlyReportQuery;
use App\Modules\Wallet\Application\Actions\AdjustWalletAction;
use App\Modules\Wallet\Application\Actions\ReverseWalletAdjustmentAction;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Safhe ye «امور مالی» — chand tab (khaste ye sahebe proje، 2026-10-06):
 *   report      gozaresh e mahane (mah e Jalali) + bazeh ye sari'
 *   ledger      tarakonesh haye kif pool (filter + CSV)
 *   journal     taraz e azmayeshi + daftar e kol
 *   settlement  tasviye ye dargah
 *   invoices    factor haye rasmi + sodur e dasti
 *   wallet      sharj/kasr e kif pool e yek karbar
 *
 * Nazok: hame ye data az Query، hame ye write az Action.
 */
final readonly class FinanceController
{
    public const TABS = ['report', 'ledger', 'journal', 'settlement', 'invoices', 'wallet'];

    public function __construct(
        private FinanceLedgerQuery $ledger,
        private FinanceMonthlyReportQuery $monthly,
        private FinanceInvoiceQuery $invoices,
        private UserQuery $users,
        private ReverseWalletAdjustmentAction $reversals,
        private SettleGatewayPaymentAction $settlements,
        private IssueInvoiceAction $issuer,
        private AdjustWalletAction $adjust,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('panel::finance.index', [
            'tab' => $filters['tab'] ?? 'report',
            'report' => $this->monthly->report($filters),
            'periods' => FinanceMonthlyReportQuery::periods(),
            'rows' => $this->ledger->ledger($filters, (int) ($filters['page'] ?? 1)),
            'journalRows' => $this->ledger->generalLedger($filters, (int) ($filters['journal_page'] ?? 1)),
            'totals' => $this->ledger->totals($filters),
            'trialBalance' => $this->ledger->trialBalance(),
            'unsettledPayments' => $this->ledger->unsettledPayments(),
            'invoices' => $this->invoices->list((int) ($filters['invoice_page'] ?? 1)),
            'issuable' => $this->invoices->issuable(),
            'filters' => $filters,
            // Kelid e idempotency e form e sharj — har bar ke safhe render mishe yeki.
            'walletAdjustKey' => (string) Str::uuid(),
        ]);
    }

    public function show(int $transaction): View
    {
        $row = $this->ledger->transaction($transaction);
        abort_if($row === null, 404);

        return view('panel::finance.show', [
            'row' => $row,
            'journalLines' => $this->ledger->journalLines($transaction),
        ]);
    }

    /** Namayesh/chap e factor e rasmi (admin). */
    public function invoice(int $invoice): View
    {
        $data = $this->invoices->invoice($invoice);
        abort_if($data === null, 404);

        return view('panel::finance.invoice', ['invoice' => $data]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        if (empty($filters['from']) || empty($filters['to'])) {
            abort(422, 'برای خروجی، بازه تاریخ را مشخص کنید.');
        }
        if ($this->ledger->totals($filters)['count'] > 10_000) {
            abort(422, 'بازه خروجی بیش از 10000 تراکنش دارد؛ فیلتر را محدود کنید.');
        }

        return response()->streamDownload(function () use ($filters): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['شناسه', 'زمان', 'کاربر', 'نام', 'جهت', 'دلیل', 'مبلغ ریال', 'مانده ریال', 'مرجع', 'ابطال‌شده']);
            foreach ($this->ledger->exportRows($filters) as $row) {
                fputcsv($output, [
                    $row->id, $row->created_at, $row->owner_id,
                    $this->safeCsv((string) ($row->owner_name ?? $row->owner_phone)),
                    $row->direction, $row->reason, $row->amount_minor,
                    $row->balance_after_minor, $this->safeCsv($row->reference),
                    $row->reversal_id === null ? 'خیر' : 'بله',
                ]);
            }
            fclose($output);
        }, 'wallet-ledger-'.date('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** CSV e gozaresh e mahane — hamun satr ha ye jadval + jam'. */
    public function exportMonthly(Request $request): StreamedResponse
    {
        $report = $this->monthly->report($this->filters($request));

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['ماه', 'از', 'تا', ...array_values($report['columns']), 'بستانکار', 'بدهکار', 'خالص', 'تعداد']);
            foreach ($report['rows'] as $row) {
                fputcsv($output, [
                    $row['label'], $row['from'], $row['to'],
                    ...array_map(static fn (string $key): int => $row[$key], array_keys($report['columns'])),
                    $row['credits'], $row['debits'], $row['net'], $row['count'],
                ]);
            }
            $totals = $report['totals'];
            fputcsv($output, ['جمع', $report['from'], $report['to'],
                ...array_map(static fn (string $key): int => $totals[$key], array_keys($report['columns'])),
                $totals['credits'], $totals['debits'], $totals['net'], $totals['count']]);
            fclose($output);
        }, 'finance-monthly-'.$report['from'].'-'.$report['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reverse(Request $request, int $transaction): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);
        try {
            $this->reversals->reverse($transaction, (int) $request->user()->id, $data['note']);
        } catch (WalletException|InvalidArgumentException $exception) {
            return back()->withErrors(['note' => $exception->getMessage()]);
        }

        return redirect()->route('panel.finance.show', $transaction);
    }

    public function correct(Request $request, int $transaction): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount_rial' => ['required', 'string', 'max:32'],
            'note' => ['required', 'string', 'max:255'],
        ]);
        $amount = Money::rialFromInput($data['amount_rial']);
        if ($amount === null || $amount <= 0) {
            return back()->withErrors(['amount_rial' => 'مبلغ ریالی معتبر نیست.']);
        }
        try {
            $this->reversals->correct($transaction, (int) $request->user()->id, $data['direction'], $amount, $data['note']);
        } catch (WalletException|InvalidArgumentException $exception) {
            return back()->withErrors(['amount_rial' => $exception->getMessage()]);
        }

        return redirect()->route('panel.finance.show', $transaction);
    }

    public function settle(Request $request, int $payment): RedirectResponse
    {
        $data = $request->validate(['bank_reference' => ['required', 'string', 'max:100']]);
        try {
            $this->settlements->execute($payment, (int) $request->user()->id, $data['bank_reference']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['bank_reference' => $exception->getMessage()]);
        }

        return redirect()->route('panel.finance.index', ['tab' => 'settlement'])->with('status', 'تسویه ثبت شد.');
    }

    /** Sodur e dasti ye factor baraye yek topup/eshterak e bedun e factor. */
    public function issueInvoice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(FinanceInvoiceQuery::KINDS))],
            'source' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $invoice = $this->issuer->forSource($data['kind'], (int) $data['source']);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('panel.finance.index', ['tab' => 'invoices'])->withErrors(['invoice' => $exception->getMessage()]);
        }

        if ($invoice === null) {
            return redirect()->route('panel.finance.index', ['tab' => 'invoices'])
                ->withErrors(['invoice' => 'کاربر این سند «تأیید فاکتور» ندارد؛ ابتدا سطح کاربر را تأیید کنید.']);
        }

        return redirect()->route('panel.finance.index', ['tab' => 'invoices'])
            ->with('status', 'فاکتور '.$invoice->number.' صادر شد.');
    }

    /**
     * Sharj/kasr e kif pool e yek karbar az safhe ye mali — hamun Action e
     * safhe ye karbar؛ faghat karbar ba mobile/ID peyda mishe.
     */
    public function adjustWallet(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user' => ['required', 'string', 'max:32'],
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'string', 'max:32'],
            'note' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $target = $this->users->findByPhoneOrId($data['user']);

        if ($target === null) {
            return $this->backToWallet()->withErrors(['user' => 'کاربری با این شماره یا شناسه پیدا نشد.'])->withInput();
        }

        $amountMinor = Money::rialFromInput($data['amount']);

        if ($amountMinor === null || $amountMinor <= 0) {
            return $this->backToWallet()->withErrors(['amount' => 'مبلغ ریالی معتبر نیست.'])->withInput();
        }

        /** @var User $actor */
        $actor = $request->user();

        try {
            $transaction = $this->adjust->execute($target->id, $data['direction'], $amountMinor, $data['note'], $actor->id, $data['idempotency_key']);
        } catch (WalletException $exception) {
            $message = match ($exception->getCode()) {
                WalletException::INSUFFICIENT => 'موجودی کیف پول کافی نیست.',
                WalletException::BLOCKED => 'کیف پول مسدود است.',
                WalletException::REFERENCE_REUSED => 'این فرم قبلاً ثبت شده است؛ صفحه را تازه کنید و دوباره ثبت کنید.',
                default => 'ثبت تراکنش ممکن نشد.',
            };

            return $this->backToWallet()->withErrors(['amount' => $message])->withInput();
        }

        return $this->backToWallet()->with(
            'status',
            Money::rialDisplay($amountMinor).' ریال برای '.($target->name ?: $target->phone).' ثبت شد (سند #'.$transaction->id.').',
        );
    }

    private function backToWallet(): RedirectResponse
    {
        return redirect()->route('panel.finance.index', ['tab' => 'wallet']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'period' => ['nullable', Rule::in(array_keys(FinanceMonthlyReportQuery::PERIODS))],
            'q' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:32'],
            'direction' => ['nullable', Rule::in(['credit', 'debit'])],
            'account_code' => ['nullable', 'string', 'size:4', 'exists:finance_accounts,code'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'journal_page' => ['nullable', 'integer', 'min:1'],
            'invoice_page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function safeCsv(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}

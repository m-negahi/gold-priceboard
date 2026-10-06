<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Common\Helpers\Money;
use App\Modules\Finance\Application\Actions\SettleGatewayPaymentAction;
use App\Modules\Finance\Application\Queries\FinanceLedgerQuery;
use App\Modules\Wallet\Application\Actions\ReverseWalletAdjustmentAction;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class FinanceController
{
    public function __construct(
        private FinanceLedgerQuery $ledger,
        private ReverseWalletAdjustmentAction $reversals,
        private SettleGatewayPaymentAction $settlements,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('panel::finance.index', [
            'rows' => $this->ledger->ledger($filters, (int) ($filters['page'] ?? 1)),
            'journalRows' => $this->ledger->generalLedger($filters, (int) ($filters['journal_page'] ?? 1)),
            'totals' => $this->ledger->totals($filters),
            'trialBalance' => $this->ledger->trialBalance(),
            'unsettledPayments' => $this->ledger->unsettledPayments(),
            'filters' => $filters,
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

        return redirect()->route('panel.finance.index');
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:32'],
            'direction' => ['nullable', Rule::in(['credit', 'debit'])],
            'account_code' => ['nullable', 'string', 'size:4', 'exists:finance_accounts,code'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'journal_page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function safeCsv(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}

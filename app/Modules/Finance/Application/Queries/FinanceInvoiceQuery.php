<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries;

use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Common\Helpers\Jalali;
use App\Modules\Common\Helpers\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Factor haye rasmi az did e ADMIN (Phase 8 — khaste ye sahebe proje،
 * 2026-10-06).
 *
 * `billing_invoices` append-only ast (trigger e DB)؛ in Query faghat
 * mikhune. Sodur ba `IssueInvoiceAction` (module e Billing) anjam mishe.
 */
final readonly class FinanceInvoiceQuery
{
    public const KINDS = ['wallet_topup' => 'رسید افزایش اعتبار', 'subscription' => 'فاکتور اشتراک'];

    public function list(int $page = 1): LengthAwarePaginator
    {
        return DB::table('billing_invoices as invoices')
            ->join('users', 'users.id', '=', 'invoices.user_id')
            ->orderByDesc('invoices.id')
            ->select('invoices.id', 'invoices.number', 'invoices.kind', 'invoices.amount_minor', 'invoices.issued_at', 'invoices.user_id', 'users.name', 'users.phone')
            ->paginate(30, ['*'], 'invoice_page', $page)->withQueryString()
            ->through(static fn (object $row): array => [
                'id' => (int) $row->id,
                'number' => $row->number,
                'kind' => $row->kind,
                'kindLabel' => self::KINDS[$row->kind] ?? $row->kind,
                'userId' => (int) $row->user_id,
                'userName' => $row->name ?: $row->phone,
                'userPhone' => $row->phone,
                'amountRial' => Money::rialDisplay((int) $row->amount_minor),
                'issuedAt' => Jalali::dateTime(new \DateTimeImmutable((string) $row->issued_at)),
            ]);
    }

    /**
     * Manba' hayi ke mitunan factor begiran vali hanuz nagereftan: topup e
     * verify-shode ya eshterak e karbar e `invoice_verified` bedun e radif
     * tu `billing_invoices`.
     *
     * @return list<array<string, mixed>>
     */
    public function issuable(): array
    {
        $level = UserLevel::InvoiceVerified->value;

        $topups = DB::table('wallet_topup_payments as payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('billing_invoices as invoices', 'invoices.wallet_topup_payment_id', '=', 'payments.id')
            ->where('payments.status', 'verified')
            ->where('users.level', $level)
            ->whereNull('invoices.id')
            ->orderByDesc('payments.id')->limit(50)
            ->get(['payments.id', 'payments.amount_minor', 'payments.verified_at as at', 'payments.user_id', 'users.name', 'users.phone'])
            ->map(static fn (object $row): array => self::candidate('wallet_topup', $row, 'افزایش اعتبار کیف پول'));

        $subscriptions = DB::table('site_subscriptions as subs')
            ->join('users', 'users.id', '=', 'subs.user_id')
            ->join('api_plans as plans', 'plans.id', '=', 'subs.api_plan_id')
            ->leftJoin('billing_invoices as invoices', 'invoices.site_subscription_id', '=', 'subs.id')
            ->where('users.level', $level)
            ->whereNull('invoices.id')
            ->orderByDesc('subs.id')->limit(50)
            ->get(['subs.id', 'subs.price_minor as amount_minor', 'subs.created_at as at', 'subs.user_id', 'users.name', 'users.phone', 'plans.name as plan_name'])
            ->map(static fn (object $row): array => self::candidate('subscription', $row, 'اشتراک '.$row->plan_name));

        return [...$topups->all(), ...$subscriptions->all()];
    }

    /**
     * Factor e kamel baraye namayesh/chap e admin — ba moshakhasat e
     * FOROSHANDE az config (`billing.seller`).
     *
     * @return array<string, mixed>|null
     */
    public function invoice(int $id): ?array
    {
        $invoice = DB::table('billing_invoices')->find($id);

        if ($invoice === null) {
            return null;
        }

        $lines = json_decode((string) $invoice->lines_snapshot, true) ?: [];
        $buyer = json_decode((string) $invoice->buyer_snapshot, true) ?: [];
        $seller = (array) config('billing.seller', []);
        $vatPercent = (int) ($seller['vat_percent'] ?? 0);
        $amount = (int) $invoice->amount_minor;

        // Mablagh e sabt-shode SHAMEL e VAT ast (karbar hamun ro pardakht
        // karde)؛ sahm e VAT faghat baraye namayesh az kol joda mishe.
        $vat = $vatPercent > 0 ? intdiv($amount * $vatPercent, 100 + $vatPercent) : 0;

        return [
            'id' => (int) $invoice->id,
            'number' => $invoice->number,
            'kind' => $invoice->kind,
            'kindLabel' => self::KINDS[$invoice->kind] ?? $invoice->kind,
            'userId' => (int) $invoice->user_id,
            'amount' => $amount,
            'amountRial' => Money::rialDisplay($amount),
            'vatPercent' => $vatPercent,
            'vatRial' => Money::rialDisplay($vat),
            'subtotalRial' => Money::rialDisplay($amount - $vat),
            'buyer' => $buyer,
            'seller' => $seller,
            'lines' => array_map(static fn (array $line): array => [
                ...$line,
                'amountRial' => Money::rialDisplay((int) ($line['amount_rial'] ?? 0)),
                'unitPriceRial' => Money::rialDisplay((int) ($line['unit_price_rial'] ?? 0)),
            ], $lines),
            'issuedAt' => Jalali::dateTime(new \DateTimeImmutable((string) $invoice->issued_at)),
        ];
    }

    /** @return array<string, mixed> */
    private static function candidate(string $kind, object $row, string $description): array
    {
        return [
            'kind' => $kind,
            'kindLabel' => self::KINDS[$kind],
            'id' => (int) $row->id,
            'userId' => (int) $row->user_id,
            'userName' => $row->name ?: $row->phone,
            'description' => $description,
            'amountRial' => Money::rialDisplay((int) $row->amount_minor),
            'at' => $row->at === null ? '—' : Jalali::dateTime(new \DateTimeImmutable((string) $row->at)),
        ];
    }
}

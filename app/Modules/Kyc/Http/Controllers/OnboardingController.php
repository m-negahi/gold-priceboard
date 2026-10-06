<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Http\Controllers;

use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Common\Helpers\Jalali;
use App\Modules\Common\Helpers\NationalId;
use App\Modules\Kyc\Application\Actions\DeclineInvoiceAction;
use App\Modules\Kyc\Application\Actions\SubmitKycRequestAction;
use App\Modules\Kyc\Application\Dto\KycRequestDto;
use App\Modules\Kyc\Application\Queries\KycQuery;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Http\Support\AttachmentRules;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Onboarding (P7-03) — soal e «فاکتور رسمی» + form e hoghoghi.
 *
 * Raghame Farsi GHABL az validation Latin mishan (`NationalId::digits`) va
 * tarikh e Jalali canonical (`YYYY/MM/DD`) mishe — meta ye ticket hamishe
 * yek shekl dare (estelam e P7-05 az hamun mikhune).
 */
final readonly class OnboardingController
{
    public function __construct(
        private KycQuery $kyc,
        private DeclineInvoiceAction $decline,
        private SubmitKycRequestAction $submit,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->is_admin) {
            return redirect()->route('panel.dashboard');
        }

        $pending = $this->kyc->pendingTicketFor($user->id);

        return view('kyc::onboarding', [
            'pendingTicketId' => $pending?->id,
            'verified' => $user->level === UserLevel::InvoiceVerified,
            'firstTime' => $user->needsOnboarding(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $request->validate(['answer' => ['required', Rule::in(['yes', 'no'])]], [
            'answer.required' => 'یکی از گزینه‌ها را انتخاب کنید.',
            'answer.in' => 'یکی از گزینه‌ها را انتخاب کنید.',
        ]);

        if ($request->input('answer') === 'no') {
            $this->decline->execute($user);

            return redirect()->route('home');
        }

        $this->normalize($request);
        $data = $request->validate($this->rules(), [
            ...AttachmentRules::messages(),
            'required' => 'این فیلد الزامی است.',
            'regex' => 'قالب این فیلد درست نیست.',
            'max' => 'این فیلد بیش از حد طولانی است.',
        ]);

        $dto = new KycRequestDto(
            registeredName: trim((string) $data['registered_name']),
            companyNationalId: (string) $data['company_national_id'],
            economicCode: (string) $data['economic_code'],
            postalCode: (string) $data['postal_code'],
            address: trim((string) $data['address']),
            phone: (string) $data['phone'],
            representativeNationalCode: (string) $data['representative_national_code'],
            representativeBirthDate: (string) $data['representative_birth_date'],
        );

        try {
            $ticket = $this->submit->execute($user, $dto, $request->file('files', []));
        } catch (TicketException $e) {
            return back()->withErrors(['answer' => $e->getMessage()]);
        }

        return redirect()->route('account.tickets.show', $ticket->id)
            ->with('status', 'درخواست ارتقای سطح ثبت شد؛ نتیجه بررسی همین‌جا و با پیامک اطلاع داده می‌شود.');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'registered_name' => ['required', 'string', 'max:200'],
            'company_national_id' => ['required', 'string', $this->check(
                static fn (string $v): bool => NationalId::isValidCompany($v),
                'شناسه ملی شرکت معتبر نیست (۱۱ رقم).',
            )],
            'economic_code' => ['required', 'string', 'regex:/^\d{10,14}$/'],
            'postal_code' => ['required', 'string', 'regex:/^\d{10}$/'],
            'address' => ['required', 'string', 'max:500'],
            'phone' => ['required', 'string', 'regex:/^0\d{9,10}$/'],
            'representative_national_code' => ['required', 'string', $this->check(
                static fn (string $v): bool => NationalId::isValidPerson($v),
                'کد ملی نماینده معتبر نیست.',
            )],
            'representative_birth_date' => ['required', 'string', $this->check(
                static fn (string $v): bool => ($at = Jalali::parse($v)) !== null && $at->isBefore(CarbonImmutable::now()),
                'تاریخ تولد معتبر نیست (مثلاً 1370/01/15).',
            )],
            ...AttachmentRules::rules(required: true),
        ];
    }

    /** @param Closure(string): bool $valid */
    private function check(Closure $valid, string $message): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($valid, $message): void {
            if (! is_string($value) || ! $valid($value)) {
                $fail($message);
            }
        };
    }

    /** Raghame Farsi → Latin + tarikh e canonical، GHABL az validation. */
    private function normalize(Request $request): void
    {
        $digits = [];

        foreach (['company_national_id', 'economic_code', 'postal_code', 'phone', 'representative_national_code'] as $field) {
            $value = $request->input($field);

            if (is_string($value)) {
                $clean = str_replace([' ', '-'], '', trim($value));
                $digits[$field] = NationalId::digits($clean) ?? $clean;
            }
        }

        $birth = $request->input('representative_birth_date');

        if (is_string($birth) && ($at = Jalali::parse($birth)) !== null) {
            $digits['representative_birth_date'] = Jalali::format($at, 'Y/m/d');
        }

        $request->merge($digits);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

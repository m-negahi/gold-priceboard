<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Support;

use App\Modules\Auth\Domain\Enums\CustomerType;
use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Common\Helpers\Jalali;
use App\Modules\Common\Helpers\NationalId;
use App\Modules\Common\Helpers\PhoneHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class UserProfileRules
{
    public const PROFILE_FIELDS = ['registered_name', 'company_national_id', 'economic_code', 'postal_code', 'address', 'landline', 'representative_national_code', 'representative_birth_date', 'birth_date'];

    /** @return array<string, mixed> */
    public static function validate(Request $request, ?int $userId = null, bool $withRole = false): array
    {
        $normalized = [];
        foreach (['phone', 'national_code', 'company_national_id', 'economic_code', 'postal_code', 'landline', 'representative_national_code'] as $field) {
            $value = $request->input($field);
            if (is_string($value)) {
                $clean = str_replace([' ', '-'], '', trim($value));
                $normalized[$field] = NationalId::digits($clean) ?? $clean;
            }
        }
        foreach (['birth_date', 'representative_birth_date'] as $field) {
            if (is_string($request->input($field)) && ($date = Jalali::parse($request->input($field))) !== null) {
                $normalized[$field] = Jalali::format($date, 'Y/m/d');
            }
        }
        if (isset($normalized['phone'])) {
            $normalized['phone'] = PhoneHelper::normalize($normalized['phone']) ?? $normalized['phone'];
        }
        $request->merge($normalized);
        $person = static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! NationalId::isValidPerson($value)) {
                $fail('کد ملی معتبر نیست.');
            }
        };
        $birthday = static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ($date = Jalali::parse($value)) === null || ! $date->isBefore(now())) {
                $fail('تاریخ تولد معتبر نیست؛ نمونه: 1370/01/15.');
            }
        };

        return $request->validate([
            'phone' => ['required', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($userId)],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'customer_type' => ['required', Rule::in(CustomerType::values())],
            ...($withRole ? [] : ['level' => ['sometimes', Rule::in(UserLevel::values())]]),
            'national_code' => ['nullable', 'string', $person],
            'registered_name' => ['nullable', 'string', 'max:200'],
            'company_national_id' => ['nullable', 'string', static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! NationalId::isValidCompany($value)) {
                    $fail('شناسه ملی شرکت معتبر نیست.');
                }
            }],
            'economic_code' => ['nullable', 'string', 'regex:/^\d{10,14}$/'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'landline' => ['nullable', 'string', 'regex:/^0\d{9,10}$/'],
            'birth_date' => ['nullable', 'string', $birthday],
            'representative_birth_date' => ['nullable', 'string', $birthday],
            'representative_national_code' => ['nullable', 'string', $person],
            ...($withRole ? ['role' => ['required', Rule::in(['customer', 'admin'])]] : []),
        ], ['unique' => 'این مقدار قبلاً ثبت شده است.', 'required' => 'این فیلد الزامی است.', 'email' => 'ایمیل معتبر نیست.', 'regex' => 'قالب مقدار واردشده معتبر نیست.']);
    }
}

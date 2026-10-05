<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RequestCodeRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        // Format e daghigh ro `SendOtpAction` khodesh check mikone
        // (`PhoneHelper`) — inja faghat shape e request.
        return ['phone' => ['required', 'string', 'max:32']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['phone.required' => 'شمارهٔ موبایل را وارد کنید.'];
    }
}

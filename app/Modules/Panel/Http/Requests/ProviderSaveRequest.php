<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation e SHAPE e form e provider — payam e khosh-khat baraye admin.
 *
 * ⚠️ Invariant haye vaghei (key e ghofl، driver e sabt-shode، band e
 * poll/timeout) tu `ProviderAction`-and ta HAR masir (form/seeder/CLI)
 * hamun ghanun ha ro begire؛ inja faghat UX e khata ye form ast.
 */
final class ProviderSaveRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'key' => ['sometimes', 'string', 'max:32'],
            'driver' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:120'],
            'name_fa' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:reference,platform'],
            'website' => ['nullable', 'url', 'max:190'],
            'upstream_unit' => ['required', 'in:rial,toman,none'],
            'timeout' => ['required', 'integer', 'min:1', 'max:60'],
            'poll_interval_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'freshness_seconds' => ['required', 'integer', 'min:5', 'max:604800'],
            'hide_after_seconds' => ['required', 'integer', 'min:0', 'max:604800'],
            'sort' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'in_comparison' => ['sometimes', 'boolean'],
            'on_board' => ['sometimes', 'boolean'],
            'in_chart' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'نام انگلیسی منبع را وارد کنید.',
            'name_fa.required' => 'نام فارسی منبع را وارد کنید.',
            'driver.required' => 'درایور را انتخاب کنید.',
            'website.url' => 'نشانی وب‌سایت معتبر نیست.',
        ];
    }

    /**
     * Checkbox haye submit-nashode `false`-and، na «dast nazan» — form e
     * kamel ast (bar khalaf e `flags()` ke partial e).
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();

        foreach (['is_active', 'in_comparison', 'on_board', 'in_chart'] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        return $key === null ? $data : data_get($data, $key, $default);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape e form e spread (16 radif). Band e darsad (±100)، symbol e
 * nashenas va takrari tu `ProviderSpreadAction::guard()`-and — inja faghat
 * type/shape ta payam e form khosh-khat bashe.
 */
final class SpreadSaveRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'max:32'],
            'rows.*.symbol' => ['required', 'string', 'max:32'],
            'rows.*.buy_mode' => ['required', 'in:percent,amount'],
            'rows.*.buy_value' => ['required', 'numeric'],
            'rows.*.sell_mode' => ['required', 'in:percent,amount'],
            'rows.*.sell_value' => ['required', 'numeric'],
            'rows.*.is_enabled' => ['sometimes', 'boolean'],
            'rows.*.freshness_seconds' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Radif ha ba checkbox e normalize-shode (submit-nashode = false).
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ((array) $this->validated('rows') as $row) {
            $row['is_enabled'] = (bool) ($row['is_enabled'] ?? false);
            $rows[] = $row;
        }

        return $rows;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Requests;

use App\Modules\Media\Dto\MediaDto;
use Illuminate\Foundation\Http\FormRequest;

/** Virayesh-e alt/title-e yek rasane az modal-e ketabkhane. */
final class UpdateMediaRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'alt' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alt.required' => 'متن جایگزین (alt) الزامی است.',
            'alt.max' => 'متن جایگزین حداکثر ۲۵۵ نویسه.',
            'title.max' => 'عنوان حداکثر ۲۵۵ نویسه.',
        ];
    }

    public function dto(): MediaDto
    {
        $title = $this->validated('title');

        return new MediaDto(alt: (string) $this->validated('alt'), title: is_string($title) ? $title : null);
    }
}

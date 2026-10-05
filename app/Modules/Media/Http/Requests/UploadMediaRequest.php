<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Requests;

use App\Modules\Media\Actions\UploadMediaAction;
use App\Modules\Media\Dto\UploadMediaDto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Upload-e yek tasvir: faghat JPG/PNG/WebP/SVG ta 5 MB, va alt ejbari — bedoone alt hich
 * tasviri vared-e ketabkhane nemishe (docs/frontend/admin-panel.md §21).
 *
 * `dimensions` ab'ad ro az header mikhoone (na decode): raster-e bozorg-tar az 8000px dar har
 * zel' hamin ja rad mishe, ghabl az inke GD baraye moshtaghat hafeze begire (SVG az in rule mo'af-e).
 */
final class UploadMediaRequest extends FormRequest
{
    /** Bozorg-tarin zel'-e mojaz (px); 8000×8000 = 64 MP, ba'd az un GD dige ma'ghool nist. */
    public const MAX_SIDE = 8000;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file',
                'mimetypes:'.implode(',', array_keys(UploadMediaAction::ALLOWED)),
                'max:'.(UploadMediaAction::MAX_BYTES / 1024),
                Rule::dimensions()->maxWidth(self::MAX_SIDE)->maxHeight(self::MAX_SIDE),
            ],
            'alt' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'فایلی انتخاب نشده است.',
            'file.file' => 'فایل معتبر نیست.',
            'file.mimetypes' => 'فقط تصویر JPG، PNG، WebP یا SVG قابل بارگذاری است.',
            'file.max' => 'حجم فایل باید حداکثر ۵ مگابایت باشد.',
            'file.dimensions' => 'ابعاد تصویر باید حداکثر ۸۰۰۰ در ۸۰۰۰ پیکسل باشد.',
            'alt.required' => 'متن جایگزین (alt) الزامی است؛ بگویید تصویر چه چیزی را نشان می‌دهد.',
            'alt.max' => 'متن جایگزین حداکثر ۲۵۵ نویسه.',
            'title.max' => 'عنوان حداکثر ۲۵۵ نویسه.',
        ];
    }

    public function dto(): UploadMediaDto
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');
        $title = $this->validated('title');

        return new UploadMediaDto(
            file: $file,
            alt: (string) $this->validated('alt'),
            title: is_string($title) ? $title : null,
            uploadedBy: $this->user()?->getAuthIdentifier() === null ? null : (int) $this->user()->getAuthIdentifier(),
        );
    }
}

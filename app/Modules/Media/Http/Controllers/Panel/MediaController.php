<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers\Panel;

use App\Modules\Common\Dto\TableRequestDto;
use App\Modules\Common\Http\Resources\TableResponse;
use App\Modules\Media\Application\Actions\DeleteMediaAction;
use App\Modules\Media\Application\Actions\UpdateMediaAction;
use App\Modules\Media\Application\Actions\UploadMediaAction;
use App\Modules\Media\Application\Queries\MediaQuery;
use App\Modules\Media\Domain\Exceptions\MediaInUseException;
use App\Modules\Media\Domain\Exceptions\UnsupportedMediaException;
use App\Modules\Media\Http\Requests\UpdateMediaRequest;
use App\Modules\Media\Http\Requests\UploadMediaRequest;
use App\Modules\Media\Http\Resources\MediaRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * رسانه dar panel: safhe-ye ketabkhane (Alpine, ADR-0020), JSON-e datatable ke picker ham hamun
 * ro mikhune, upload (multipart), virayesh-e alt/title, hazf. Har radif-e javab hamun `MediaRow`-e
 * ta picker, jadval, modal va editor-haye safhe/maghale yek shekl bebinand.
 */
final readonly class MediaController
{
    public function __construct(
        private MediaQuery $media,
        private UploadMediaAction $upload,
        private UpdateMediaAction $update,
        private DeleteMediaAction $delete,
    ) {}

    public function index(): View
    {
        return view('media::panel.media.index', [
            'mimes' => UploadMediaAction::ALLOWED,
            'maxBytes' => UploadMediaAction::MAX_BYTES,
        ]);
    }

    public function table(Request $request): TableResponse
    {
        return new TableResponse($this->media->table(TableRequestDto::fromRequest($request)), MediaRow::from(...));
    }

    public function store(UploadMediaRequest $request): JsonResponse
    {
        try {
            $media = $this->upload->handle($request->dto());
        } catch (UnsupportedMediaException $e) {
            return new JsonResponse(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        return new JsonResponse(['data' => MediaRow::from($this->media->withUsages($media->id) ?? $media), 'message' => 'تصویر بارگذاری شد.'], 201);
    }

    public function update(UpdateMediaRequest $request, int $media): JsonResponse
    {
        $media = $this->update->handle($this->media->findOrFail($media), $request->dto());

        return new JsonResponse(['data' => MediaRow::from($this->media->withUsages($media->id) ?? $media), 'message' => 'رسانه ذخیره شد.']);
    }

    public function destroy(int $media): JsonResponse
    {
        try {
            $this->delete->handle($this->media->findOrFail($media));
        } catch (MediaInUseException $e) {
            return new JsonResponse(['message' => $e->getMessage(), 'errors' => ['media' => [$e->getMessage()]]], 422);
        }

        return new JsonResponse(['data' => ['deleted' => 1], 'message' => 'رسانه حذف شد.']);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Http\Controllers;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Ticket\Application\Queries\TicketQuery;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download e peyvast (P7-02) — DO ghofl:
 *   1. emza ye zamandar (`signed:relative` ru route) — link e kopi-shode
 *      ba'd az engheza kar nemikone.
 *   2. saheb e ticket ya admin (`TicketQuery::attachmentForViewerOrFail`)
 *      — link e emza-dar e saheb dast e karbar e digar 404 ast (IDOR).
 *
 * `Content-Disposition: attachment` + `nosniff`: file e upload-shode hich
 * vaght inline (HTML/SVG e ja'li) tu origin e ma render nemishe.
 */
final readonly class TicketAttachmentController
{
    public function __construct(
        private TicketQuery $tickets,
        private FilesystemFactory $storage,
    ) {}

    public function download(Request $request, int $attachment): StreamedResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $file = $this->tickets->attachmentForViewerOrFail($attachment, $viewer->id, (bool) $viewer->is_admin);

        return $this->storage->disk($file->disk)->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

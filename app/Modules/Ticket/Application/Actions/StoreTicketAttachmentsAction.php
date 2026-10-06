<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Application\Actions;

use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketAttachment;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

/**
 * Zakhire ye madarek e ticket ru disk e PRIVATE (P7-02).
 *
 * - Pasvand az mime e VAGHEI ye mohtava (`getMimeType` = finfo)، na az esm
 *   e karbar — `x.pdf` ke dar vaghe' HTML ast inja ham rad mishe (kamarband
 *   e dovvom e validation e controller).
 * - Esm e ru disk TASADOFI (`{ticket}/{uuid}.{ext}`): esm e karbar nemitune
 *   path traversal/overwrite kone؛ faghat baraye namayesh zakhire mishe.
 * - Shekast vasat e kar: file haye neveshte-shode ye HAMIN darkhast pak
 *   mishan (yatim ru disk namimunan).
 */
final readonly class StoreTicketAttachmentsAction
{
    public const DISK = 'tickets';

    /** mime e vaghei → pasvand e zakhire. */
    public const MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private FilesystemFactory $storage) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return list<TicketAttachment>
     */
    public function execute(Ticket $ticket, ?int $messageId, ?int $uploaderId, array $files): array
    {
        $disk = $this->storage->disk(self::DISK);
        $written = [];
        $rows = [];

        try {
            foreach ($files as $file) {
                $mime = (string) $file->getMimeType();
                $ext = self::MIMES[$mime] ?? throw TicketException::fileType();

                $name = Str::uuid()->toString().'.'.$ext;
                $path = $disk->putFileAs((string) $ticket->id, $file, $name);
                $written[] = $path;

                $rows[] = TicketAttachment::query()->create([
                    'ticket_id' => $ticket->id,
                    'message_id' => $messageId,
                    'uploaded_by' => $uploaderId,
                    'disk' => self::DISK,
                    'path' => $path,
                    'original_name' => self::displayName($file->getClientOriginalName(), $ext),
                    'mime' => $mime,
                    'size_bytes' => (int) $file->getSize(),
                    'sha256' => (string) hash_file('sha256', (string) $file->getRealPath()),
                ]);
            }
        } catch (Throwable $e) {
            foreach ($written as $path) {
                $disk->delete($path);
            }

            throw $e;
        }

        return $rows;
    }

    /** Esm e namayeshi: basename، bedun e control char، kutah. */
    private static function displayName(string $original, string $ext): string
    {
        $base = basename(str_replace('\\', '/', $original));
        $base = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $base);
        $base = trim(mb_substr($base, 0, 120));

        return $base === '' ? 'file.'.$ext : $base;
    }
}

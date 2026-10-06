<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Http\Controllers;

use App\Modules\Pricing\Application\Queries\PublicBoardQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tablo ye OMUMI ye site (Phase 5).
 *
 * `home()` SSR ast (first paint baraye SEO)؛ `boardJson()` hamun data ro
 * ba ETag mide ta Alpine har 15s poll kone — poll e bi-taghir 304 migire,
 * na kol e payload.
 *
 * ⚠️ `/board.json` OMUMI ast (bedun e auth) — bar khalaf e `/api/v1/*` ke
 * HMAC + charge dare. In yeki chizi-e ke tablo ye site azash mikhune.
 */
final readonly class PublicBoardController
{
    public function __construct(private PublicBoardQuery $board) {}

    public function home(): View
    {
        return view('public.board', ['board' => $this->board->present()]);
    }

    public function boardJson(Request $request): JsonResponse
    {
        $board = $this->board->present();

        // ETag e za'if: `W/"..."` — content e semantic yeki-e، hatta age
        // whitespace farg kone.
        $etag = 'W/'.$board['etag'];

        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304, ['ETag' => $etag]);
        }

        return new JsonResponse($board, 200, [
            'ETag' => $etag,
            // 15s — hamun cadence e poll؛ CDN/proxy mitune cache kone.
            'Cache-Control' => 'public, max-age=5',
        ]);
    }
}

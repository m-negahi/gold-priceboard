<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Modules\Pricing\Application\Dto\BoardRowDto;
use App\Modules\Pricing\Application\Queries\BoardQuery;
use App\Modules\Pricing\Application\Queries\ProviderQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gheymat haye zende (P4-06).
 *
 * ── ETag (PLAN §Phase 4) ────────────────────────────────────────────
 * `W/"b{boardVersion}:s{spreadVersion}"` — poll e bi-taghir 304 migire
 * va middleware ye charge REFUND esh mikone: tafavot e beyn e «5,760
 * poll e bi-taghir e ruzane» va «taghir e vaghei». Customer az header e
 * `X-Pb-Cost` mibine kodum ro hesab kardim.
 */
final readonly class PricesController
{
    public function __construct(
        private BoardQuery $board,
        private ProviderQuery $providers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $board = $this->board->board($this->providers->allActive());

        $etag = sprintf('W/"b%d:s%d"', $board->version, $board->spreadVersion);

        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304, ['ETag' => $etag]);
        }

        return new JsonResponse([
            'data' => array_map(static fn (BoardRowDto $row): array => $row->toArray(), $board->rows),
            'degraded' => $board->degraded,
            'boardVersion' => $board->version,
            'spreadVersion' => $board->spreadVersion,
        ], 200, ['ETag' => $etag]);
    }

    public function show(Request $request, string $symbol): JsonResponse
    {
        $instrument = Instrument::tryFrom($symbol);

        if ($instrument === null) {
            return new JsonResponse(['message' => 'نماد ناشناخته است.'], 404);
        }

        $board = $this->board->board($this->providers->allActive());

        $rows = array_values(array_filter(
            $board->rows,
            static fn (BoardRowDto $row): bool => $row->instrument === $instrument,
        ));

        $etag = sprintf('W/"b%d:s%d:%s"', $board->version, $board->spreadVersion, $symbol);

        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304, ['ETag' => $etag]);
        }

        return new JsonResponse([
            'data' => array_map(static fn (BoardRowDto $row): array => $row->toArray(), $rows),
            'degraded' => $board->degraded,
        ], 200, ['ETag' => $etag]);
    }
}

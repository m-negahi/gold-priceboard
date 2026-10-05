<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Pipeline;

use App\Modules\Pricing\Application\Dto\ProviderConfigDto;
use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\RejectionDto;
use App\Modules\Pricing\Application\Dto\SinkResultDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;

/**
 * Halat e yek tick، ke beyn e Stage ha dast be dast mishe.
 *
 * ⚠️ In TANHA class e mutable e kol e module ast — va amdan. Yek pipeline
 * bayad natije ro jam' kone، va alternativ (sakhtan e yek context e taze tu
 * har marhale) faghat sar-o-seda ezafe mikone bedun e hich fayede.
 *
 * Omr esh yek tick ast: tu `IngestTickAction` sakhte mishe va hamun ja ham
 * tamum mishe. Hargez tu container bind nemishe.
 */
final class TickContext
{
    /** @var array<int, RawQuoteDto> */
    public array $raw = [];

    /** @var array<string, SnapshotDto> gheymat haye ghabli (kelid: SnapshotDto::key()) */
    public array $previous = [];

    /** @var array<int, SnapshotDto> chizi ke bayad zakhire beshe */
    public array $snapshots = [];

    /** @var array<int, SnapshotDto> faghat unhayi ke gheymateshun TAGHIR karde */
    public array $changed = [];

    /**
     * Snapshot hayi ke faghat `pendingOutlier` eshun avaz shode.
     *
     * 🔴 Inha bayad tu POSTGRES zakhire shan vali NA tu Redis/Influx:
     * gheymat e namayesh dade shode avaz nashode (un ghablan rad shod)، pas
     * neveshtan e yek tick e jadid ya'ni ja'l kardan e data-i ke upstream
     * nadade. Vali agar `pendingOutlier` payedar nashe، ghanun e «do outlier
     * e mote'ali vaghei-and» hichvaght kar nemikone — tick e ba'di nemidune
     * tick e ghabli chi ro rad karde bud.
     *
     * @var array<int, SnapshotDto>
     */
    public array $holds = [];

    /** @var array<string, ProviderHealthDto> */
    public array $health = [];

    /** @var array<int, RejectionDto> */
    public array $rejections = [];

    /** @var array<string, SinkResultDto> */
    public array $sinks = [];

    /** @var array<string, array<int, string>> providerKey => label haye map-nashode */
    public array $unmapped = [];

    /**
     * @param  array<int, QuoteSourcePort>  $sources
     * @param  array<string, ProviderConfigDto>  $providers
     */
    public function __construct(
        public readonly TickTime $at,
        public readonly array $sources,
        public readonly array $providers,
    ) {}

    public function config(string $providerKey): ProviderConfigDto
    {
        return $this->providers[$providerKey] ?? new ProviderConfigDto($providerKey);
    }

    public function reject(RejectionDto $rejection): void
    {
        $this->rejections[] = $rejection;
    }

    public function recordSink(SinkResultDto $result): void
    {
        $this->sinks[$result->name] = $result;
    }
}

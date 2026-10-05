<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Sources;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\SourceRequestDto;
use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;

/**
 * Source e offline baraye dev — hich request e biruni.
 *
 * ── Chera «harekat e mohasebe-shode» va na «random e zakhire-shode» ──
 * Rah e ma'mul (va rah e `hg_website`) in ast ke akharin gheymat tu Redis
 * negah dashte she va har tick yek delta ye random behesh ezafe she. Vali:
 *   - Source ghabl e test bedun e Redis nist
 *   - Source be yek store vabaste mishe, dar hali ke faghat bayad motarjem bashe
 *   - `final readonly` + halat = nasht e data beyn e request ha zire Swoole
 *
 * In piade-sazi gheymat ro be sorat e yek TABE ye MAHZ az zaman hesab mikone:
 *
 *   price(t) = seed x (1 + mowj e kond + mowj e sari' + jitter)
 *
 * Natije: hich halat، hich IO، kamelan determinist (hamun zaman = hamun
 * gheymat)، va be nazar mirese mesle yek bazaar e vaghei harekat mikone.
 * Test mitune ba `FrozenClock` daghighan ru yek meghdar assert kone.
 */
final readonly class MockQuoteSource implements QuoteSourcePort
{
    public const KEY = 'mock';

    /**
     * Noghte ye shoru' — gheymat haye VAGHEI ye 2026-10-04.
     *
     * @var array<string, int>
     */
    private const SEEDS = [
        'xau-18k-g-irr' => 266_400_000,
        'xau-24k-g-irr' => 355_196_000,
        'xau-melted-g-irr' => 264_921_000,
        'xau-mesghal-irr' => 1_154_030_000,
        'xau-coin-emami-irr' => 2_753_400_000,
        'xau-coin-bahar-irr' => 2_619_450_000,
        'xau-coin-half-irr' => 1_429_600_000,
        'xau-coin-quarter-irr' => 789_900_000,
        'xau-coin-gerami-irr' => 380_000_000,
        'xag-999-g-irr' => 5_534_900,
        'xag-925-g-irr' => 5_119_760,
        'xau-oz-usd' => 41_401_900,
        'xag-oz-usd' => 603_700,
        'xcu-lb-usd' => 64_920,
        'usd-irr' => 2_711_600,
    ];

    /** Fasele ye bid/ask baraye instrument-i ke jofte vaghei dare (0.66%). */
    private const NATIVE_SPREAD = 0.0066;

    /** Daamane ye mowj ha — jam' eshun ~±1.1% ast, shabih e yek rooz e aram. */
    private const SLOW_AMPLITUDE = 0.008;

    private const FAST_AMPLITUDE = 0.002;

    private const JITTER_AMPLITUDE = 0.001;

    /** Dowre ye mowj ha be saniye: ~6 saat va ~20 daghighe. */
    private const SLOW_PERIOD = 21_600;

    private const FAST_PERIOD = 1_200;

    /** @param array<string, int> $seeds */
    public function __construct(
        private ClockPort $clock,
        private array $seeds = [],
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    /** Hich HTTP-i nadare — `FetchStage` mostaghim `fetch()` ro seda mizane. */
    public function request(): ?SourceRequestDto
    {
        return null;
    }

    /**
     * Mock payload e biruni nadare — `fetch()` khodesh hame chiz ro misaze.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parse(array $payload): SourceResultDto
    {
        return $this->fetch();
    }

    public function fetch(): SourceResultDto
    {
        $at = $this->clock->tick();
        $quotes = [];

        foreach (Instrument::cases() as $instrument) {
            $seed = $this->seeds[$instrument->value] ?? self::SEEDS[$instrument->value] ?? null;

            if ($seed === null) {
                continue;
            }

            $minor = $this->priceAt($instrument->value, $seed, $at->timestamp());

            if ($instrument->hasNativeSides()) {
                // Tanha instrument-i ke upstream vaghe'an jofte mide — mock
                // ham bayad hamun shekl ro taghlid kone, vagarna mantegh e
                // `is_derived` tu dev hichvaght test nemishe.
                $half = (int) round($minor * self::NATIVE_SPREAD / 2);

                $quotes[] = $this->quote($instrument, Side::Bid, $minor - $half);
                $quotes[] = $this->quote($instrument, Side::Ask, $minor + $half);

                continue;
            }

            $quotes[] = $this->quote($instrument, Side::Last, $minor);
        }

        return new SourceResultDto(
            quotes: $quotes,
            // Hash az zaman miad, pas har tick avaz mishe — hamun chizi ke
            // yek feed e salem anjam mide.
            payloadHash: md5((string) $at->timestamp()),
            payloadBytes: 0,
        );
    }

    private function quote(Instrument $instrument, Side $side, int $minor): RawQuoteDto
    {
        return new RawQuoteDto(
            providerKey: self::KEY,
            instrument: $instrument,
            side: $side,
            price: new Price(max(1, $minor), $instrument->quote()),
            // Mock hamishe «alan» ast — hichvaght bayat nemishe.
            upstreamAt: $this->clock->now(),
        );
    }

    /**
     * Gheymat be onvan e yek tabe ye mahz az zaman.
     *
     * Se laye: yek mowj e kond (rond e rooz)، yek mowj e sari' (nowsan e
     * kutah-moddat)، va yek jitter e hash-based ke be symbol va zaman
     * vabaste ast (ta hame ye symbol ha ba ham bala-paeen naran).
     */
    private function priceAt(string $symbol, int $seed, int $timestamp): int
    {
        // Har symbol faz e khodesh ro dare — vagarna kol e tablo mesle yek
        // chiz harekat mikone va kheyli masnu'i be nazar mirese.
        $phase = (float) (crc32($symbol) % 1_000) / 1_000 * 2 * M_PI;

        $slow = sin($timestamp / self::SLOW_PERIOD * 2 * M_PI + $phase) * self::SLOW_AMPLITUDE;
        $fast = sin($timestamp / self::FAST_PERIOD * 2 * M_PI + $phase * 2) * self::FAST_AMPLITUDE;

        // Jitter: determinist vali be nazar tasadofi — har tick avaz mishe.
        $noise = (float) (crc32($symbol.':'.$timestamp) % 2_001 - 1_000) / 1_000;
        $jitter = $noise * self::JITTER_AMPLITUDE;

        return (int) round($seed * (1 + $slow + $fast + $jitter));
    }
}

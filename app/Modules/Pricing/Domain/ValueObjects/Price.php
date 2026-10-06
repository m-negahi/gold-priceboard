<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\ValueObjects;

use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use DomainException;

/**
 * Yek meghdar e puli — hamishe be sorat e INT e minor unit.
 *
 *   minor = major x 10^scale
 *   IRR (scale 0): 266,400,000 rial  -> minor = 266_400_000
 *   USD (scale 4): $4140.19          -> minor = 41_401_900
 *
 * ── Chera minor int va na decimal/float ──────────────────────────────
 * `float` ru adad haye rial (ta ~1e10) zaheran salem ast vali ba'd az chand
 * amaliyat daghighat gom mikone va khata saket ast.
 * `decimal(24,6)` daghigh ast vali PHP string bar migardune va har jam'/zarb
 * bcmath mikhahe — va avvalin kasi ke `(float)` bezane hamun khata ro bar
 * migardune.
 * `bigint` e minor har do ro hall mikone: amaliyat e sahih, daghighat e kamel.
 * Headroom: 9.2e18 -> 9.2e14 dollar / 9.2e18 rial. Kheyli emn.
 *
 * ── Shart e in entekhab ──────────────────────────────────────────────
 * 🔴 Int e kham HARGEZ az in class birun nemire. Agar jayi `$price->minor` ro
 * bardashti va ba yek `minor` e arz e digar jam' zadi, javab bi-ma'ni ast va
 * hichi be tu nemige. Baraye hamin `plus()`/`minus()` ru cross-currency
 * `throw` mikonan.
 */
final readonly class Price
{
    public function __construct(
        public int $minor,
        public QuoteCurrency $quote,
    ) {}

    public static function irr(int $minor): self
    {
        return new self($minor, QuoteCurrency::IRR);
    }

    public static function usd(int $minor): self
    {
        return new self($minor, QuoteCurrency::USD);
    }

    public static function zero(QuoteCurrency $quote): self
    {
        return new self(0, $quote);
    }

    public function scale(): int
    {
        return $this->quote->scale();
    }

    /**
     * 🔴 Faghat baraye NAMAYESH va serialize e JSON.
     * Hargez baraye mohasebe — natije ro dobare be `Price` bar nagardun.
     */
    public function toFloat(): float
    {
        return $this->minor / (10 ** $this->quote->scale());
    }

    /**
     * Matn e NAMAYESHI (rial -> toman، dollar ba 2 ragham) — ba bcmath، na float.
     *
     * Chera na `number_format($this->toFloat() / 10)`: bala ye 2^53 raghame
     * akhar saket gom mishe va gerd kardan e `round()` ru float gahi
     * (x.5 -> paeen) eshtebah mikone. Gerd kardan nim-be-bala (az sefr dur).
     */
    public function display(): string
    {
        $precision = $this->quote->displayPrecision();
        $divisor = bcmul(bcpow('10', (string) $this->quote->scale()), (string) $this->quote->displayDivisor());

        $negative = $this->minor < 0;
        $abs = ltrim((string) $this->minor, '-');

        // round(abs × 10^p / divisor) = floor((2·abs·10^p + divisor) / (2·divisor))
        $scaled = bcmul($abs, bcpow('10', (string) $precision));
        $rounded = bcdiv(bcadd(bcmul($scaled, '2'), $divisor), bcmul($divisor, '2'), 0);

        $rounded = str_pad($rounded, $precision + 1, '0', STR_PAD_LEFT);
        $int = $precision > 0 ? substr($rounded, 0, -$precision) : $rounded;
        $fraction = $precision > 0 ? '.'.substr($rounded, -$precision) : '';

        $grouped = strrev(implode(',', str_split(strrev($int), 3)));

        return ($negative && $rounded !== str_repeat('0', strlen($rounded)) ? '-' : '').$grouped.$fraction;
    }

    /**
     * Meghdar e MAJOR e DAGHIGH be sorat e reshte (`4140.1950`، `266400000`)
     * — baraye JSON-LD/schema.org (P5-05). Bedun e gerd kardan، bedun e
     * jodakonande، bedun e float: faghat jabeja kardan e momayez.
     */
    public function decimal(): string
    {
        $scale = $this->quote->scale();
        $sign = $this->minor < 0 ? '-' : '';
        $abs = ltrim((string) $this->minor, '-');

        if ($scale === 0) {
            return $sign.$abs;
        }

        $abs = str_pad($abs, $scale + 1, '0', STR_PAD_LEFT);

        return $sign.substr($abs, 0, -$scale).'.'.substr($abs, -$scale);
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->quote);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->quote);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor < $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->quote === $other->quote && $this->minor === $other->minor;
    }

    public static function min(self $a, self $b): self
    {
        return $a->isLessThan($b) ? $a : $b;
    }

    public static function max(self $a, self $b): self
    {
        return $a->isGreaterThan($b) ? $a : $b;
    }

    /**
     * Darsad e taghir nesbat be yek meghdar e paye.
     * `0.0` bar migardune vaghti paye sefr ast (jaye taghsim bar sefr).
     */
    public function changePercentFrom(self $base): float
    {
        $this->assertSameCurrency($base);

        if ($base->minor === 0) {
            return 0.0;
        }

        return round(($this->minor - $base->minor) / $base->minor * 100, 4);
    }

    /**
     * Shekl e serialize baraye Redis / JSON.
     * `scale` hamrah miad ta reader baraye tafsir e `minor` be enum niaz nadashte bashe.
     *
     * @return array{minor: int, quote: string, scale: int}
     */
    public function toArray(): array
    {
        return [
            'minor' => $this->minor,
            'quote' => $this->quote->value,
            'scale' => $this->quote->scale(),
        ];
    }

    /** @param array{minor: int|string, quote: string} $data */
    public static function fromArray(array $data): self
    {
        return new self((int) $data['minor'], QuoteCurrency::from($data['quote']));
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->quote !== $this->quote) {
            throw new DomainException(
                "Price e do arz e motefavet ghabl e tarkib nist: {$this->quote->value} va {$other->quote->value}."
            );
        }
    }
}

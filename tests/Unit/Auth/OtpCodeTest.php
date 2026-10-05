<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Modules\Auth\Domain\Services\OtpCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OtpCodeTest extends TestCase
{
    private function otp(): OtpCode
    {
        return new OtpCode('test-key-sabet');
    }

    // ── Tolid ───────────────────────────────────────────────────────

    public function test_generates_exactly_six_digits(): void
    {
        for ($i = 0; $i < 200; $i++) {
            self::assertMatchesRegularExpression('/^\d{6}$/', $this->otp()->generate());
        }
    }

    /**
     * 🔴 Sefr e avval MOJAZ ast.
     *
     * `random_int(100000, 999999)` ghalat e ma'mul ast: 10% e fazaye kelid
     * (hame ye code haye ba sefr e avval) ro hazf mikone. Ba 200 nemune،
     * ehtemal e nadidan e hich sefr e avvali agar tolid DOROST bashe
     * `0.9^200 ≈ 7e-10` ast — pas in test flaky nist.
     */
    public function test_leading_zeros_are_possible(): void
    {
        $sawLeadingZero = false;

        for ($i = 0; $i < 200 && ! $sawLeadingZero; $i++) {
            $sawLeadingZero = str_starts_with($this->otp()->generate(), '0');
        }

        self::assertTrue($sawLeadingZero, 'hich code-i ba sefr shoru nashod — fazaye kelid 10% kuchik shode.');
    }

    public function test_respects_a_custom_length(): void
    {
        self::assertMatchesRegularExpression('/^\d{4}$/', $this->otp()->generate(4));
    }

    // ── Hash ────────────────────────────────────────────────────────

    public function test_verify_round_trips(): void
    {
        $hash = $this->otp()->hash('+989121234567', '042531');

        self::assertTrue($this->otp()->verify('+989121234567', '042531', $hash));
        self::assertFalse($this->otp()->verify('+989121234567', '042532', $hash));
    }

    /**
     * 🔴 Hash be PHONE gereh khorde: hash e code e yek shomare baraye
     * shomare ye digar ghabel e replay NIST.
     */
    public function test_the_same_code_hashes_differently_per_phone(): void
    {
        $otp = $this->otp();

        self::assertNotSame(
            $otp->hash('+989121234567', '042531'),
            $otp->hash('+989129999999', '042531'),
        );
        self::assertFalse($otp->verify('+989129999999', '042531', $otp->hash('+989121234567', '042531')));
    }

    /**
     * 🔴 Hash be KEY gereh khorde — dump e Redis bedun e APP_KEY bi-estefade
     * bashe. (Yek code e 6 raghami faghat 10^6 halat dare؛ sha256 e khali
     * amalan hamun plaintext ast.)
     */
    public function test_a_different_key_produces_a_different_hash(): void
    {
        self::assertNotSame(
            (new OtpCode('key-a'))->hash('+989121234567', '042531'),
            (new OtpCode('key-b'))->hash('+989121234567', '042531'),
        );
    }

    public function test_an_empty_key_is_refused(): void
    {
        // APP_KEY e set-nashode bayad HAMIN JA beterke، na saket yek HMAC e
        // bi-kelid besaze.
        $this->expectException(InvalidArgumentException::class);
        new OtpCode('');
    }

    public function test_the_code_itself_never_appears_in_the_hash(): void
    {
        self::assertStringNotContainsString('042531', $this->otp()->hash('+989121234567', '042531'));
    }
}

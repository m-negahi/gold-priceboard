<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Domain\Services\TransientFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Siasat e retry — tasmim e asli ye `P1-25`.
 *
 * Retry e bi-ghaid budget e tick ro mikhore: `Http::pool` montazer e HAME
 * mimune، pas yek retry e eshtebah baraye jobran e yek surakh tu YEK series
 * yek surakh tu HAME ye series misaze.
 */
final class TransientFailureTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function retryable(): array
    {
        return [
            'TLS handshake (hamun ke vaghe-an didim)' => ['cURL error 35: SSL connect error'],
            'nemitune vasl she' => ['cURL error 7: Failed to connect to host'],
            'host resolve nashod' => ['cURL error 6: Could not resolve host'],
            'javab e khali' => ['cURL error 52: Empty reply from server'],
            'reset e ettesal' => ['cURL error 56: Recv failure: Connection reset by peer'],
        ];
    }

    #[DataProvider('retryable')]
    public function test_connection_level_failures_are_retryable(string $message): void
    {
        self::assertTrue(TransientFailure::isRetryable(new RuntimeException($message)));
    }

    /**
     * 🔴 Timeout retry NEMISHE.
     *
     * Timeout ya'ni budget e KAMEL e un provider ghablan kharj shode
     * (`estjt` 20 saniye!). Retry un ro DO barabar mikone — gerun tarin
     * shekast، bad tarin kandida baraye takrar.
     */
    public function test_a_timeout_is_not_retryable(): void
    {
        self::assertFalse(TransientFailure::isRetryable(
            new RuntimeException('cURL error 28: Operation timed out after 20000 milliseconds'),
        ));
    }

    /** @return array<string, array{0: string}> */
    public static function notRetryable(): array
    {
        return [
            // Markup avaz shode — talash e dobare DAGHIGHAN hamun javab ro
            // mide. Bayad DIDE she، na takrar.
            'khata ye parse' => ['markup e upstream avaz shode'],
            'JSON nist' => ['pasokh JSON nist.'],
            // Eshtebah e CONFIG، na ghat'i.
            'pasokh e na-movafagh' => ['pasokh e na-movafagh (404).'],
            'hichi az pool' => ['hich pasokhi az pool nayumad.'],
            // Khata ye SSL e gheyr e ettesali (certificate) — retry un ro
            // durost nemikone.
            'certificate' => ['cURL error 60: SSL certificate problem'],
        ];
    }

    #[DataProvider('notRetryable')]
    public function test_permanent_failures_are_not_retryable(string $message): void
    {
        self::assertFalse(TransientFailure::isRetryable(new RuntimeException($message)));
    }

    public function test_the_curl_code_is_extracted_from_the_message(): void
    {
        // Match ru MATN ast، na ru class e exception: `Domain/` nabayad
        // `Illuminate\Http\Client\ConnectionException` ro bebine.
        self::assertSame(35, TransientFailure::curlCode('cURL error 35: SSL connect error'));
        self::assertSame(28, TransientFailure::curlCode('cURL error 28: timed out'));
        self::assertNull(TransientFailure::curlCode('yek chiz e dige'));
    }

    public function test_the_match_is_case_insensitive(): void
    {
        self::assertTrue(TransientFailure::isRetryable(new RuntimeException('curl error 35: ssl')));
    }
}

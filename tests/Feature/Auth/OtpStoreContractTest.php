<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Domain\Ports\OtpStorePort;
use App\Modules\Auth\Infrastructure\Otp\RedisOtpStore;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fakes\InMemoryOtpStore;
use Tests\TestCase;

/**
 * 🔴 GHARARDAD e `OtpStorePort` — ru HAR DO implementation ejra mishe.
 *
 * Dars e `P1-26` (sabt shode tu `docs/TASKS.md`): fake-i ke merge mikard
 * dar hali ke adapter e vaghei jaygozin mikard، yek bug ro az cheshm e KOL e
 * test suite penhan kard. Rah e jologiri hamin file ast: har raftar yek bar
 * ru fake va yek bar ru Redis e vaghei assert mishe — vagerai ye saket
 * gheyr e momken mishe.
 */
final class OtpStoreContractTest extends TestCase
{
    private const PHONE = '+989121234567';

    protected function setUp(): void
    {
        parent::setUp();

        // Db e 13 (test) — kelid haye in test pak mishan ta flaky nashan.
        foreach (Redis::connection('default')->keys('otp:*') as $key) {
            // `keys()` kelid ha ro BA prefix bar migardune vali `del` khodesh
            // prefix mizane — pas prefix bardashte mishe.
            $bare = str_replace((string) config('database.redis.options.prefix', ''), '', $key);
            Redis::connection('default')->del($bare);
        }
    }

    /** @return array<string, array{0: string}> */
    public static function stores(): array
    {
        return [
            'redis (vaghei)' => ['redis'],
            'in-memory (fake)' => ['fake'],
        ];
    }

    private function store(string $kind): OtpStorePort
    {
        return $kind === 'redis' ? new RedisOtpStore : new InMemoryOtpStore;
    }

    // ── put / get ───────────────────────────────────────────────────

    #[DataProvider('stores')]
    public function test_a_stored_entry_comes_back_with_zero_attempts(string $kind): void
    {
        $store = $this->store($kind);
        $store->put(self::PHONE, 'hash-1', 120);

        $entry = $store->get(self::PHONE);

        self::assertNotNull($entry);
        self::assertSame('hash-1', $entry->codeHash);
        self::assertSame(0, $entry->attempts);
    }

    #[DataProvider('stores')]
    public function test_a_missing_phone_returns_null(string $kind): void
    {
        self::assertNull($this->store($kind)->get('+989000000000'));
    }

    /**
     * 🔴 `put` e jadid shomaresh e ghabli ro ham SEFR mikone.
     *
     * Vagarna: 2 ghalat ru code e ghabli + resend -> code e jadid faghat 1
     * talash dare. Resend bayad yek shoru' e kamel bashe.
     */
    #[DataProvider('stores')]
    public function test_a_new_code_resets_the_attempt_counter(string $kind): void
    {
        $store = $this->store($kind);
        $store->put(self::PHONE, 'hash-1', 120);
        $store->recordFailedAttempt(self::PHONE);
        $store->recordFailedAttempt(self::PHONE);

        $store->put(self::PHONE, 'hash-2', 120);

        $entry = $store->get(self::PHONE);
        self::assertSame('hash-2', $entry?->codeHash);
        self::assertSame(0, $entry?->attempts);
    }

    // ── talash haye ghalat ──────────────────────────────────────────

    #[DataProvider('stores')]
    public function test_failed_attempts_count_up(string $kind): void
    {
        $store = $this->store($kind);
        $store->put(self::PHONE, 'hash-1', 120);

        self::assertSame(1, $store->recordFailedAttempt(self::PHONE));
        self::assertSame(2, $store->recordFailedAttempt(self::PHONE));
        self::assertSame(2, $store->get(self::PHONE)?->attempts);
    }

    /**
     * 🔴 Talash ru entry e NABUDE entry NEMISAZE.
     *
     * `HINCRBY` e khali kelid ro ba shomaresh e tanha va BEDUN e TTL misaze
     * — yek kelid e yatim e abadi. Lua ye adapter in ro mibande؛ fake ham
     * bayad hamin raftar ro dashte bashe.
     */
    #[DataProvider('stores')]
    public function test_an_attempt_on_a_missing_entry_returns_minus_one_and_creates_nothing(string $kind): void
    {
        $store = $this->store($kind);

        self::assertSame(-1, $store->recordFailedAttempt(self::PHONE));
        self::assertNull($store->get(self::PHONE), 'nabayad entry sakhte shode bashe.');
    }

    #[DataProvider('stores')]
    public function test_invalidate_removes_the_entry(string $kind): void
    {
        $store = $this->store($kind);
        $store->put(self::PHONE, 'hash-1', 120);

        $store->invalidate(self::PHONE);

        self::assertNull($store->get(self::PHONE));
        self::assertSame(-1, $store->recordFailedAttempt(self::PHONE));
    }

    // ── cooldown ────────────────────────────────────────────────────

    #[DataProvider('stores')]
    public function test_cooldown_is_zero_when_never_started(string $kind): void
    {
        self::assertSame(0, $this->store($kind)->cooldownRemaining(self::PHONE));
    }

    #[DataProvider('stores')]
    public function test_cooldown_counts_down_from_its_start(string $kind): void
    {
        $store = $this->store($kind);
        $store->startCooldown(self::PHONE, 60);

        $remaining = $store->cooldownRemaining(self::PHONE);

        self::assertGreaterThan(0, $remaining);
        self::assertLessThanOrEqual(60, $remaining);
    }

    #[DataProvider('stores')]
    public function test_cooldown_is_independent_of_the_code_entry(string $kind): void
    {
        $store = $this->store($kind);
        $store->put(self::PHONE, 'hash-1', 120);
        $store->startCooldown(self::PHONE, 60);

        // Batel shodan e code (masraf ya 3 ghalat) nabayad ghofl e resend ro
        // baz kone.
        $store->invalidate(self::PHONE);

        self::assertGreaterThan(0, $store->cooldownRemaining(self::PHONE));
    }

    // ── TTL (har implementation ba abzar e khodesh) ─────────────────

    public function test_the_redis_entry_actually_expires(): void
    {
        $store = new RedisOtpStore;
        $store->put(self::PHONE, 'hash-1', 120);

        // Sabr nemikonim — TTL e vaghei ye kelid check mishe. ⚠️ `keys()` ba
        // prefix bar migardune، pas `ttl` ru kelid e bedun e prefix.
        $ttl = (int) Redis::connection('default')->ttl('otp:code:'.self::PHONE);

        self::assertGreaterThan(100, $ttl, 'TTL set nashode — code hargez expire nemishe!');
        self::assertLessThanOrEqual(120, $ttl);
    }

    public function test_the_fake_entry_expires_with_time(): void
    {
        $store = new InMemoryOtpStore;
        $store->put(self::PHONE, 'hash-1', 120);

        $store->advanceSeconds(119);
        self::assertNotNull($store->get(self::PHONE));

        $store->advanceSeconds(2);
        self::assertNull($store->get(self::PHONE));
        self::assertSame(-1, $store->recordFailedAttempt(self::PHONE));
    }

    public function test_the_fake_cooldown_expires_with_time(): void
    {
        $store = new InMemoryOtpStore;
        $store->startCooldown(self::PHONE, 60);

        $store->advanceSeconds(61);

        self::assertSame(0, $store->cooldownRemaining(self::PHONE));
    }
}

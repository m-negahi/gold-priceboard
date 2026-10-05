<?php

declare(strict_types=1);

namespace App\Modules\Auth\Console\Commands;

use App\Modules\Auth\Domain\Ports\OtpStorePort;
use App\Modules\Common\Helpers\PhoneHelper;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RedisStore;
use Illuminate\Console\Command;

/**
 * Pak kardan e KOL e vaziat e OTP e yek shomare — FAGHAT local/testing.
 *
 * ── Chera vojud dare ─────────────────────────────────────────────────
 * E2E (Playwright) login haye poshte-sar-e-ham mizane va MOSTAGHIM be
 * cooldown e 60s va rate limit e vaghei mikhore — ke hadaf e khod e un
 * mohafez ha-st. In command ghabl az har test vaziat ro sefr mikone ta
 * limit ha tu app KAMELAN armed bemunan (hich `if (testing)` tu masir e
 * asli nist) va suite tu chand saniye ejra beshe.
 *
 * 🔴 Kharej az local/testing kolan ejra NEMISHE — abzar e dor zadan e
 * rate limit tu production nist.
 */
final class OtpFlushCommand extends Command
{
    protected $signature = 'otp:flush {phone : shomare (har format e Irani)}';

    protected $description = 'Vaziat e OTP e yek shomare ro pak mikone (code, cooldown, rate limit) — faghat dev.';

    public function handle(OtpStorePort $store, RateLimiter $limiter): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('faghat local/testing.');

            return self::FAILURE;
        }

        $phone = PhoneHelper::normalize((string) $this->argument('phone'));

        if ($phone === null) {
            $this->error('shomare motabar nist.');

            return self::FAILURE;
        }

        $store->invalidate($phone);
        $store->clearCooldown($phone);

        $limiter->clear("otp:send:phone:{$phone}");
        $limiter->clear("otp:verify:phone:{$phone}");

        /*
         * 🔴 Limiter haye IP ham pak mishan — dar DEV in dorost e, na suraakh:
         * kol e e2e + curl haye dasti az YEK IP (gateway e docker) miyan va
         * saghf e 10/saat tu yek saat e kari sari' por mishe؛ un vaght
         * e2e be jaye rate-limit e vaghei، baghimande ye run haye ghabli ro
         * test mikone (flaky). In command kolan faghat local/testing ejra
         * mishe، pas hich mohafez e production-i lams nemishe.
         *
         * Kelid ha tu CACHE store-and (RateLimiter) — scan ru hamun
         * connection e cache ba prefix e khodesh.
         */
        $store = cache()->store()->getStore();

        if ($store instanceof RedisStore) {
            $connection = $store->connection();
            $prefix = $store->getPrefix();

            foreach (['otp:send:ip:*', 'otp:verify:ip:*'] as $pattern) {
                foreach ($connection->keys($prefix.$pattern) as $key) {
                    // `keys()` BA prefix e redis-client barmigardune vali
                    // `del` e client khodesh prefix mizane (dars e P1/P2-04).
                    $bare = str_replace((string) config('database.redis.options.prefix', ''), '', $key);
                    $connection->del($bare);
                }
            }
        }

        $this->info("OTP state cleared: {$phone}");

        return self::SUCCESS;
    }
}

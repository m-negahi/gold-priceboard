<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Exceptions;

use App\Modules\Pricing\Domain\Enums\ProviderKind;

/** Tanzimat e provider ghabl e ghabul nist. */
final class InvalidProviderException extends PricingException
{
    /**
     * 🔴 `key` ba'd az sakht GHOFL ast.
     *
     * `providers.key` faghat yek esm nist — hoviat e in provider tu HAR SE
     * sink ast:
     *   - tag e `provider` tu Influx (hame ye candle ha va tick ha)
     *   - kelid e Redis e tablo
     *   - `price_changes` (daftarche ye append-only)
     *
     * Influx 2 rename e tag nadare: baraya avaz kardanesh bayad HAR point ro
     * dobare neveshti va ghadimi ha ro delete kard. Pas avaz kardan e `key`
     * ya'ni kol e tarikhche ye chart e un provider YATIM mishe — chart khali
     * mishe va hich khata-i ham nemiad. Agar vaghe'an bayad avaz she، provider
     * e jadid besaz va ghadimi ro deactivate kon.
     */
    public static function keyIsLocked(string $from, string $to): self
    {
        return new self(
            "شناسهٔ تأمین‌کننده پس از ساخت قابل تغییر نیست (از «{$from}» به «{$to}»). ".
            'تمام تاریخچهٔ نمودار با این شناسه ذخیره شده است؛ در صورت نیاز تأمین‌کنندهٔ جدید بسازید.',
        );
    }

    /**
     * Driver e nashenas.
     *
     * Bedun e in check، `SourceManager::make()` tu HAR tick throw mikone va
     * `IngestTickAction` un ro catch mikone va faghat log mizane — provider
     * tu panel «fa'al» be nazar mirese vali hichvaght hich gheymati nemide.
     * Khata inja، sar e vaght e zakhire، hazar bar behtar az yek log e saket
     * ast.
     *
     * @param  array<int, string>  $known
     */
    public static function unknownDriver(string $driver, array $known): self
    {
        return new self(
            "درایور «{$driver}» شناخته‌شده نیست. مقادیر مجاز: ".implode('، ', $known).'.',
        );
    }

    public static function blankKey(): self
    {
        return new self('شناسهٔ تأمین‌کننده نمی‌تواند خالی باشد.');
    }

    public static function malformedKey(string $key): self
    {
        return new self(
            "شناسهٔ «{$key}» معتبر نیست؛ فقط حروف کوچک انگلیسی، رقم، خط تیره و زیرخط مجاز است.",
        );
    }

    public static function duplicateKey(string $key): self
    {
        return new self("تأمین‌کننده‌ای با شناسهٔ «{$key}» از قبل وجود دارد.");
    }

    public static function unknownKind(string $kind): self
    {
        return new self(
            "نوع «{$kind}» معتبر نیست. مقادیر مجاز: ".implode('، ', ProviderKind::values()).'.',
        );
    }

    public static function pollIntervalOutOfRange(int $seconds, int $min, int $max): self
    {
        return new self(
            "فاصلهٔ فراخوانی باید بین {$min} و {$max} ثانیه باشد (مقدار داده‌شده: {$seconds}).",
        );
    }

    /**
     * Saghf e motlagh e `timeout`.
     *
     * ── ⚠️ Chera shart «timeout < poll_interval» NIST ───────────────
     * Avval hamun shart ro gozashtam va test e khodam un ro rad kard: config
     * e VAGHEI ye `estjt` tu seeder `timeout = 20` ba fasele ye 15s ast — ba
     * andaze-giri: un endpoint 6.2 ta 9.1 saniye javab mide.
     *
     * Un shart eshtebah bud chun halghe ye worker ba
     * `sleep $((15 - $(date +%s) % 15))` ru grid HAM-TARAZ mishe: yek tick e
     * 20 saniye-i ya'ni YEK tick e ja-mande، na drift e daemi. Pas timeout e
     * bozorgtar az fasele yek tanzim e ghabel e defa'e، na yek khata.
     *
     * Chizi ke VAGHE'AN bad ast timeout e kheyli bozorg ast: `Http::pool`
     * montazer e HAME ye provider ha mimune، pas yek timeout e 300 saniye-i
     * yek provider e gir-karde ro tabdil mikone be 5 daghighe ke HICH
     * gheymati — az hich provider-i — neveshte nemishe.
     */
    public static function timeoutOutOfRange(int $timeout, int $max): self
    {
        return new self(
            "زمان انتظار باید بین ۱ و {$max} ثانیه باشد (مقدار داده‌شده: {$timeout}). ".
            'زمان انتظار بیش از حد، یک تأمین‌کنندهٔ معلق را به توقف کل چرخهٔ قیمت تبدیل می‌کند.',
        );
    }
}

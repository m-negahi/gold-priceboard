<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Media\Application\Actions\SyncMediaUsageAction;
use App\Modules\Media\Infrastructure\Persistence\Models\Media;
use App\Modules\Pricing\Application\Dto\ProviderInputDto;
use App\Modules\Pricing\Domain\Enums\HealthState;
use App\Modules\Pricing\Domain\Exceptions\InvalidProviderException;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Sources\NerkhQuoteSource;
use App\Modules\Pricing\Infrastructure\Sources\SourceManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD e provider — tanha masir e neveshtan ru jadval e `providers`.
 *
 * ── Chera Action va na `Provider::create()` tu Livewire ─────────────
 * Se invariant inja zendegi mikonan ke hich validation e form nemitune
 * ta'min kone، chun be halat e GHABLI ye radif va be code e app vabaste-and:
 *
 *   1. `key` ba'd az sakht GHOFL ast (hoviat e Influx/Redis/audit).
 *   2. `driver` bayad tu `SourceManager` sabt shode bashe.
 *   3. `poll_interval_seconds` ke avaz she، `next_poll_at` bayad SEFR she.
 *
 * Har masiri (form، seeder، command) bayad az hamin ja rad beshe.
 */
final readonly class ProviderAction
{
    /** Kamtar az in، rate limit e upstream ro mizanim. */
    public const MIN_POLL_SECONDS = 5;

    /** Bishtar az in، provider amalan khamoosh ast — behtar `is_active=false`. */
    public const MAX_POLL_SECONDS = 3_600;

    /**
     * Saghf e `timeout`. `Http::pool` montazer e HAME ye provider ha mimune،
     * pas yek timeout e afsar-gosikhte yek provider e gir-karde ro tabdil
     * mikone be chand daghighe ke hich gheymati — az hich provider-i —
     * neveshte nemishe.
     */
    public const MAX_TIMEOUT_SECONDS = 60;

    public function create(ProviderInputDto $input): Provider
    {
        $this->validate($input);

        if (Provider::withTrashed()->where('key', $input->key)->exists()) {
            // `withTrashed` amdi: yek provider e soft-delete-shode hanuz
            // tarikhche ye Influx va `price_changes` dare. Estefade ye dobare
            // az kelid esh, data ye do provider e motefavet ro ru ham mirize.
            throw InvalidProviderException::duplicateKey($input->key);
        }

        $columns = $input->toColumns();

        // `next_poll_at = null` ya'ni «tu avvalin tick poll she» —
        // `scopeDueForPoll` null ro due hesab mikone.
        $columns['next_poll_at'] = null;

        return $this->persist(new Provider, $input, $columns);
    }

    public function update(Provider $provider, ProviderInputDto $input): Provider
    {
        $this->validate($input);

        if ($input->key !== $provider->key) {
            throw InvalidProviderException::keyIsLocked($provider->key, $input->key);
        }

        $columns = $input->toColumns();

        // Agar fasele ye poll avaz shode، `next_poll_at` e ghadim dige
        // ma'ni nadare: kasi ke fasele ro az 3600 be 15 miare entezar dare
        // FORI asar kone، na ta yek sa'at e ba'd. Bedun e in، admin fekr
        // mikone tanzim kar nakarde.
        if ($input->pollIntervalSeconds !== $provider->poll_interval_seconds) {
            $columns['next_poll_at'] = null;
        }

        // Fa'al kardan e dobare = yek shoru' e taze.
        //
        // ⚠️ In tanha jayi-e ke gheyr az `RecordProviderHealthAction` soton
        // haye salamat ro mizare، va amdi-e: provider-i ke dar hal e shekast
        // khamoosh shode، ba `health_state=down` va `consecutive_failures=20`
        // dobare roshan mishe va panel un ro ghermez neshun mide GHABL az
        // inke hatta yek bar emtehan she.
        if ($input->isActive && ! $provider->is_active) {
            $columns['next_poll_at'] = null;
            $columns['consecutive_failures'] = 0;
            $columns['health_state'] = HealthState::Unknown->value;
            $columns['last_error'] = null;
        }

        return $this->persist($provider, $input, $columns);
    }

    /**
     * Toggle haye lista ye panel.
     *
     * Jodagane az `update()` chun form e kamel ro lazem nadare — va chun yek
     * `update()` e nesf-kare ba DTO ye khali mitune baghi ye soton ha ro be
     * default bargardune.
     *
     * @param  array<string, bool>  $flags
     */
    public function setFlags(Provider $provider, array $flags): Provider
    {
        $allowed = ['is_active', 'in_comparison', 'on_board', 'in_chart', 'is_trust_anchor'];

        $columns = [];

        foreach ($allowed as $flag) {
            if (array_key_exists($flag, $flags)) {
                $columns[$flag] = (bool) $flags[$flag];
            }
        }

        if ($columns === []) {
            return $provider;
        }

        if (($columns['is_active'] ?? false) && ! $provider->is_active) {
            $columns['next_poll_at'] = null;
            $columns['consecutive_failures'] = 0;
            $columns['health_state'] = HealthState::Unknown->value;
            $columns['last_error'] = null;
        }

        if (($columns['is_trust_anchor'] ?? false) && $provider->driver === 'manual') {
            throw new InvalidProviderException('منبع دستی نمی‌تواند مرجع اعتماد باشد.');
        }

        $provider->forceFill($columns)->save();

        return $provider->refresh();
    }

    public function activate(Provider $provider): Provider
    {
        return $this->setFlags($provider, ['is_active' => true]);
    }

    /**
     * Khamoosh kardan — gheymat haye ghabli PAK NEMISHAN.
     *
     * `price_snapshots` dast nemikhore chun `EloquentSnapshotStore::all()`
     * faghat provider haye FA'AL ro mibine: radif ha ru tablo nemian vali
     * agar provider dobare roshan she، `dayOpen`/`direction` e un rooz hanuz
     * sar e jash ast.
     */
    public function deactivate(Provider $provider): Provider
    {
        return $this->setFlags($provider, ['is_active' => false]);
    }

    /**
     * Soft delete.
     *
     * 🔴 Hargez `forceDelete` nemishe: `price_changes` va tag haye Influx be
     * in `key` eshare mikonan va `provider_spread_revisions` ba
     * `cascadeOnDelete` sakhte shode — yek hard delete kol e daftarche ye
     * audit e un provider ro ba khodesh mibare.
     */
    public function delete(Provider $provider): void
    {
        // Provider e hazf-shode nabayad poll beshe، hatta agar `is_active`
        // esh `true` munde bud.
        $provider->forceFill(['is_active' => false])->save();
        $provider->delete();
    }

    public function restore(Provider $provider): Provider
    {
        $provider->restore();

        return $this->setFlags($provider, ['is_active' => true]);
    }

    /** Logo va estefade-ash bayad ba provider tu yek transaction sabt shan. */
    private function persist(Provider $provider, ProviderInputDto $input, array $columns): Provider
    {
        return DB::transaction(function () use ($provider, $input, $columns): Provider {
            if ($provider->exists) {
                $provider = Provider::query()->lockForUpdate()->findOrFail($provider->id);
                if ($input->logoPath === null) {
                    unset($columns['logo_path']);
                }
            }

            if ($input->logoSpecified) {
                $media = $input->logoMediaId === null ? null : Media::query()->lockForUpdate()->find($input->logoMediaId);
                if ($input->logoMediaId !== null && $media === null) {
                    throw ValidationException::withMessages(['logo_media_id' => 'تصویر انتخاب‌شده دیگر موجود نیست.']);
                }
                $columns['logo_media_id'] = $media?->id;
                $columns['logo_path'] = $media?->path;
            }

            $provider->fill($columns)->save();
            if ($input->logoSpecified) {
                app(SyncMediaUsageAction::class)->handle($provider, ['logo' => $input->logoMediaId]);
            }

            return $provider->refresh();
        });
    }

    private function validate(ProviderInputDto $input): void
    {
        if ($input->isTrustAnchor && $input->driver === 'manual') {
            throw new InvalidProviderException('منبع دستی نمی‌تواند مرجع اعتماد باشد.');
        }

        if ($input->key === '') {
            throw InvalidProviderException::blankKey();
        }

        // Kelid tu kelid e Redis، tag e Influx va URL e panel miad. Allowlist
        // e sakht، na sanitize — sanitize kardan yek kelid e ghalat ro be yek
        // kelid e MOTEFAVET e ghalat tabdil mikone.
        if (preg_match('/^[a-z0-9_-]{2,32}$/', $input->key) !== 1) {
            throw InvalidProviderException::malformedKey($input->key);
        }

        $drivers = SourceManager::drivers();

        if (! in_array($input->driver, $drivers, true)) {
            throw InvalidProviderException::unknownDriver($input->driver, $drivers);
        }

        if ($input->driver === NerkhQuoteSource::KEY && array_key_exists('item', $input->options)
            && (! is_string($input->options['item']) || ! in_array($input->options['item'], NerkhQuoteSource::itemKeys(), true))) {
            throw new InvalidProviderException('کلید item برای درایور نرخ معتبر نیست.');
        }

        if ($input->pollIntervalSeconds < self::MIN_POLL_SECONDS
            || $input->pollIntervalSeconds > self::MAX_POLL_SECONDS) {
            throw InvalidProviderException::pollIntervalOutOfRange(
                $input->pollIntervalSeconds,
                self::MIN_POLL_SECONDS,
                self::MAX_POLL_SECONDS,
            );
        }

        // ⚠️ Shart e «timeout < pollInterval» amdan INJA NIST. Dalil e kamel
        // tu `InvalidProviderException::timeoutOutOfRange()` ast — kholase:
        // config e vaghei ye estjt `timeout = 20` ba fasele ye 15s ast (un
        // endpoint andaze-giri shode 6.2–9.1s javab mide)، va halghe ye
        // worker ru grid ham-taraz mishe pas natije esh yek tick e ja-mande
        // ast، na drift.
        if ($input->timeout < 1 || $input->timeout > self::MAX_TIMEOUT_SECONDS) {
            throw InvalidProviderException::timeoutOutOfRange(
                $input->timeout,
                self::MAX_TIMEOUT_SECONDS,
            );
        }
    }
}

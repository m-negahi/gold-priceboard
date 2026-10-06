<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Ghanun haye laye-bandi ye `app/Modules/CLAUDE.md` — ru SOURCE check mishan،
 * na tu code review. Har rule daghighan file hayi ke naghz esh kardan ro
 * esm mibare، pas yek failure mesle yek to-do list khunde mishe.
 *
 * Eghtebas az `tests/Architecture/LayerRulesTest.php` e proje ye ghabli
 * (priceboard)، motabegh e sakhtar e Domain/Application/Infrastructure.
 */
final class LayerRulesTest extends TestCase
{
    /**
     * Jayi ke Eloquent mojaz ast: Action/Query (Application)، kol e
     * Infrastructure (Persistence/Cache/Sources/…)، Policy/Observer،
     * seeder/factory/migration، va Query haye Panel.
     */
    private const MODEL_LAYERS = [
        '/Application/Actions/', '/Application/Queries/', '/Panel/Queries/', '/Infrastructure/',
        '/Common/Actions/', '/Common/Queries/', '/Policies/', '/Observers/', '/database/', '/Console/Commands/',
        // Job = Action e sadaf-shode؛ Middleware e API (HMAC/idempotency) masir e dagh
        // ast va amdan mostaghim mikhune — tasmim e sabt-shode، na estesna ye saket.
        '/Jobs/', '/Http/Middleware/',
    ];

    private const MODEL_STATICS = 'query|where|whereIn|whereKey|find|findOrFail|first|firstWhere|all|get|create|insert|upsert|updateOrCreate|firstOrCreate|firstOrNew|destroy|count|exists|with|withTrashed|withoutEvents';

    /** Chiz hayi ke tu `Domain/` mamnu'-and: har rad-pa ye Laravel ya IO. */
    private const DOMAIN_FORBIDDEN = [
        'use Illuminate\\' => '/^use Illuminate\\\\/m',
        'config(' => '/(?<![\w>$])config\(/',
        'app(' => '/(?<![\w>$])app\(/',
        // `function now(` (ClockPort/TickTime) e'lan ast، na seda zadan e helper e Laravel.
        'now(' => '/(?<![\w>$:])(?<!function )now\(/',
        'DB::' => '/\bDB::/',
        'Cache::' => '/\bCache::/',
        'Http::' => '/\bHttp::/',
        'Log::' => '/\bLog::/',
    ];

    public function test_eloquent_is_reached_only_from_actions_queries_and_infrastructure(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path => $code) {
            if ($this->isIn($path, self::MODEL_LAYERS)) {
                continue;
            }

            foreach ($this->importedModels($code) as $model) {
                if (preg_match('/\b'.$model.'::('.self::MODEL_STATICS.')\(/', $code) === 1 || preg_match('/\bnew '.$model.'\(/', $code) === 1) {
                    $offenders[] = "{$path} uses {$model} directly";
                }
            }

            if (preg_match('/\bDB::/', $code) === 1) {
                $offenders[] = "{$path} uses `DB::` outside a Query/Action";
            }
        }

        $this->assertSame([], $offenders, "Eloquent belongs in Actions/Queries/Infrastructure:\n".implode("\n", $offenders));
    }

    /** 🔴 `Domain/` hich chiz e Laravel nemibine (CLAUDE.md، ghanun e non-negotiable). */
    public function test_domain_is_pure_php(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path => $code) {
            if (! str_contains($path, '/Domain/')) {
                continue;
            }

            foreach (self::DOMAIN_FORBIDDEN as $label => $pattern) {
                if (preg_match($pattern, $this->withoutComments($code)) === 1) {
                    $offenders[] = "{$path} uses `{$label}`";
                }
            }
        }

        $this->assertSame([], $offenders, "Domain must stay framework-free:\n".implode("\n", $offenders));
    }

    /** Model ha faghat tu `Infrastructure/Persistence/Models/` (CLAUDE.md). */
    public function test_models_live_only_in_infrastructure_persistence(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path => $code) {
            if (preg_match('/^(?:abstract )?class \w+ extends (?:Model|Authenticatable|Pivot)\b/m', $code) !== 1) {
                continue;
            }

            if (! str_contains($path, '/Infrastructure/Persistence/Models/')) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, "Model outside Infrastructure/Persistence/Models:\n".implode("\n", $offenders));
    }

    /**
     * Controller ha Action/Query ro tu constructor migiran (CLAUDE.md:
     * «Query/Action ro constructor-inject kon»). Method injection vabastegi
     * ro az signature e class penhan mikone. (Command ha estesna: `handle()`
     * e Laravel idiom e method-injection ast.)
     */
    public function test_controllers_take_actions_and_queries_in_the_constructor(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path => $code) {
            if (! $this->isIn($path, ['/Http/Controllers/'])) {
                continue;
            }

            preg_match_all('/function (?!__construct)(\w+)\(([^)]*)\)/', $code, $methods, PREG_SET_ORDER);

            foreach ($methods as [, $method, $parameters]) {
                if (preg_match('/\b\w+(Action|Query)\s+\$/', $parameters) === 1) {
                    $offenders[] = "{$path}::{$method}()";
                }
            }
        }

        $this->assertSame([], $offenders, "Method-injected Action/Query:\n".implode("\n", $offenders));
    }

    public function test_views_never_import_a_model(): void
    {
        $offenders = [];

        foreach ([...iterator_to_array($this->sources('resources/views', '*.blade.php')), ...iterator_to_array($this->sources('app', '*.blade.php'))] as $path => $code) {
            if (preg_match('/Persistence\\\\Models\\\\/', $code) === 1) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, "Blade reaching for a model:\n".implode("\n", $offenders));
    }

    /** @return list<string> */
    private function importedModels(string $code): array
    {
        preg_match_all('/^use App\\\\Modules\\\\\w+\\\\Infrastructure\\\\Persistence\\\\Models\\\\(\w+);$/m', $code, $matches);

        return $matches[1];
    }

    /** @param list<string> $layers */
    private function isIn(string $path, array $layers): bool
    {
        foreach ($layers as $layer) {
            if (str_contains($path, $layer)) {
                return true;
            }
        }

        return false;
    }

    /** Comment ha ro hazf mikone ta docblock-i ke `Http::pool` ro tozih mide offender nashe. */
    private function withoutComments(string $code): string
    {
        return (string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $code);
    }

    /** @return iterable<string, string> path => source */
    private function sources(string $directory = 'app', string $pattern = '*.php'): iterable
    {
        $root = dirname(__DIR__, 2);

        foreach (Finder::create()->files()->in($root.'/'.$directory)->name($pattern) as $file) {
            yield substr($file->getPathname(), strlen($root) + 1) => $file->getContents();
        }
    }
}

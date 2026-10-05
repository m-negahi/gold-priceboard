<?php

declare(strict_types=1);

namespace App\Modules\Common\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Base-e har provider-e module. Ghesmat-haye ghararadi ro sim-keshi mikone ta module boilerplate
 * tekrar nakone: `Config/{key}.php` zir-e `modules.{key}` merge mishe, `Resources/views` mishe
 * namespace-e view-e `{key}::`, va `Console/Commands` baraye CLI sabt mishan. Module faghat
 * baraye binding-haye khodesh `register()`/`boot()` ro override mikone.
 *
 * Migration ha va route ha amdan per-module NISTAND (ADR-0027): har migration tu
 * `database/migrations` va har route tu `routes/web.php` / `routes/api.php` hast, ta tarikhche-ye
 * schema va naghshe-ye URL har kodum daghighan yek ja baraye khundan dashte bashan.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Esm-e directory zir-e `app/Modules`, masalan `Common`. */
    abstract protected function module(): string;

    /**
     * Command-haye artisan-e in module. Esm-esh `consoleCommands()`-e chon
     * ServiceProvider::commands() az ghabl hast va argument migire.
     *
     * @return list<class-string>
     */
    protected function consoleCommands(): array
    {
        return [];
    }

    public function register(): void
    {
        $config = $this->modulePath('Config/'.$this->key().'.php');

        if (is_file($config)) {
            $this->mergeConfigFrom($config, 'modules.'.$this->key());
        }
    }

    public function boot(): void
    {
        $views = $this->modulePath('Resources/views');

        if (is_dir($views)) {
            $this->loadViewsFrom($views, $this->key());
        }

        if ($this->app->runningInConsole() && $this->consoleCommands() !== []) {
            $this->commands($this->consoleCommands());
        }
    }

    /** Key-e horoof-kuchik-e module baraye namespace-e config va view. */
    protected function key(): string
    {
        return strtolower($this->module());
    }

    protected function modulePath(string $path = ''): string
    {
        return app_path('Modules/'.$this->module().($path === '' ? '' : '/'.$path));
    }
}

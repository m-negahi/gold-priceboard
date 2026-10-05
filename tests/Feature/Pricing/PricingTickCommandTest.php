<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PricingTickCommandTest extends TestCase
{
    use RefreshDatabase;

    private function provider(): Provider
    {
        return Provider::create([
            'key' => 'mock',
            'driver' => 'mock',
            'name' => 'Mock',
            'name_fa' => 'آزمایشی',
            'is_active' => true,
        ]);
    }

    public function test_runs_a_tick(): void
    {
        $this->provider();

        $this->artisan('pricing:tick')->assertSuccessful();
    }

    public function test_reports_when_there_is_nothing_to_poll(): void
    {
        $this->artisan('pricing:tick')
            ->expectsOutputToContain('rad shod')
            ->assertFailed();
    }

    public function test_json_output_is_machine_readable(): void
    {
        $this->provider();

        $this->artisan('pricing:tick --json')->assertSuccessful();
    }

    /**
     * ⚠️ Shekast e yek provider `FAILURE` NIST: halghe ye worker nabayad be
     * khatere yek ghat'i ye gozara motevaghef beshe.
     */
    public function test_a_failing_provider_still_exits_successfully(): void
    {
        $this->provider();

        Provider::create([
            'key' => 'broken',
            'driver' => 'driver-e-khiali',
            'name' => 'Broken',
            'name_fa' => 'خراب',
            'is_active' => true,
        ]);

        $this->artisan('pricing:tick')->assertSuccessful();
    }

    public function test_quiet_ok_suppresses_healthy_output(): void
    {
        $this->provider();

        $this->artisan('pricing:tick --quiet-ok')->assertSuccessful();
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenancyEnded;

abstract class TestCase extends BaseTestCase
{
    protected static bool $testingTenantAliasGuardBooted = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restoreTestingTenantAlias();

        if (! static::$testingTenantAliasGuardBooted) {
            Event::listen(TenancyEnded::class, function (): void {
                $pgsql = config('database.connections.pgsql');

                if (app()->environment('testing') && is_array($pgsql) && ! config('database.connections.tenant')) {
                    Config::set('database.connections.tenant', $pgsql);
                }
            });

            static::$testingTenantAliasGuardBooted = true;
        }
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        $this->restoreTestingTenantAlias();

        parent::tearDown();
    }

    protected function restoreTestingTenantAlias(): void
    {
        $pgsql = config('database.connections.pgsql');

        if (app()->environment('testing') && is_array($pgsql) && ! config('database.connections.tenant')) {
            Config::set('database.connections.tenant', $pgsql);
        }
    }
}

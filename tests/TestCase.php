<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Tests;

use Illuminate\Foundation\Application;
use ITuoiProfessionistiDigitali\Connector\ConnectorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [LaravelDataServiceProvider::class, ConnectorServiceProvider::class];
    }

    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * @param  Application  $app
     */
    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('platform.url', 'https://platform.test');
        $app['config']->set('platform.client_id', 'client-id');
        $app['config']->set('platform.client_secret', 'client-secret');
        $app['config']->set('platform.signing_secret', 'signing-secret');
    }
}

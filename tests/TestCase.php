<?php

namespace Dotlib\BrunoDocs\Tests;

use Dotlib\BrunoDocs\BrunoDocsServiceProvider;
use Dotlib\BrunoDocs\Tests\Support\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Register the package provider.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            BrunoDocsServiceProvider::class,
        ];
    }

    /**
     * Point the docs at the fixture collection and the test user provider.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.locale', 'pt_BR');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('bruno-docs.collection', dirname(__DIR__).'/tests/fixtures/collection');
        $app['config']->set('bruno-docs.cache', false);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Load the users migration.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/tests/database/migrations');
    }

    /**
     * Register the named login route used by the auth middleware.
     *
     * @param  \Illuminate\Routing\Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/login', static fn () => 'login')->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('vendor:publish', [
            '--tag' => 'bruno-docs-assets',
            '--force' => true,
        ]);
    }
}

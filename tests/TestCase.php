<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ElliottLawson\LaravelMcp\Providers\McpServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Your additional setup
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            McpServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Set a test application key
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.debug', true);

        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up MCP configuration
        $app['config']->set('mcp.api_key', 'test-api-key');
        $app['config']->set('mcp.message_store_id', 'test-message-store');

        // Configure MCP HTTP routes
        $app['config']->set('mcp.http', [
            'route_prefix' => 'mcp',
            'middleware' => ['web'],
        ]);
    }

    /**
     * Define routes setup.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    protected function defineRoutes($router)
    {
        // Load the package routes directly
        $router->middleware(['web'])
            ->prefix('mcp')
            ->group(__DIR__ . '/../routes/mcp.php');
    }
}

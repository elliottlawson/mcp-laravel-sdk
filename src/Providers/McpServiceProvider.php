<?php

namespace ElliottLawson\LaravelMcp\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ElliottLawson\LaravelMcp\McpManager;
use ElliottLawson\McpPhpSdk\Server\McpServer;
use ElliottLawson\LaravelMcp\Support\EventListener;

class McpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge the package configuration with the application configuration
        $this->mergeConfigFrom(__DIR__ . '/../../config/mcp.php', 'mcp');

        // Register the McpManager as a singleton
        $this->app->singleton(McpManager::class, function ($app) {
            return new McpManager($app);
        });

        // Register the McpServer as a singleton
        $this->app->singleton(McpServer::class, function ($app) {
            return $app->make(McpManager::class)->server();
        });

        // Register the facades
        $this->app->alias(McpManager::class, 'mcp.manager');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish the configuration file
        $this->publishes([
            __DIR__ . '/../../config/mcp.php' => config_path('mcp.php'),
        ], 'mcp-config');

        // Load routes with conditional middleware based on config
        $this->registerRoutes();

        // Register commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                \ElliottLawson\LaravelMcp\Console\Commands\McpServerCommand::class,
            ]);
        }

        // Register event listeners
        if ($this->app->resolved(McpServer::class)) {
            $this->registerEventListeners();
        } else {
            $this->app->afterResolving(McpServer::class, function () {
                $this->registerEventListeners();
            });
        }
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        // Get route configuration
        $routeConfig = $this->app->make('config')->get('mcp.http', []);
        $prefix = $routeConfig['route_prefix'] ?? 'mcp';
        $middleware = $routeConfig['middleware'] ?? ['web'];

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(__DIR__ . '/../../routes/mcp.php');
    }

    /**
     * Register event listeners for MCP events.
     */
    protected function registerEventListeners(): void
    {
        $events = $this->app->make('events');
        $server = $this->app->make(McpServer::class);

        EventListener::register($events, $server);
    }
}

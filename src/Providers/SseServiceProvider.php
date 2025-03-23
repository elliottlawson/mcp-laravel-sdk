<?php

namespace ElliottLawson\LaravelMcp\Providers;

use Illuminate\Support\ServiceProvider;
use ElliottLawson\LaravelMcp\Support\LaravelNativeSseTransport;

class SseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(LaravelNativeSseTransport::class, function ($app) {
            return new LaravelNativeSseTransport(
                config('mcp.sse.heartbeat_interval', 30),
                config('mcp.sse.max_execution_time', 0)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register event listeners for SSE events
        $events = $this->app['events'];

        $events->listen('mcp.sse.connected', function ($event) {
            // Log connection
            $this->app['log']->info('SSE connection established', [
                'connection_id' => $event['connection_id'] ?? null,
            ]);
        });

        $events->listen('mcp.sse.disconnected', function ($event) {
            // Log disconnection
            $this->app['log']->info('SSE connection closed', [
                'connection_id' => $event['connection_id'] ?? null,
            ]);
        });
    }
}

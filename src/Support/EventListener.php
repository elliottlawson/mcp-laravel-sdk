<?php

namespace ElliottLawson\LaravelMcp\Support;

use ElliottLawson\LaravelMcp\McpManager;

/**
 * Registers event listeners for MCP Server events.
 */
class EventListener
{
    /**
     * Register event listeners with the provided event dispatcher.
     *
     * @param  object  $events  Laravel event dispatcher instance
     */
    public static function register($events, McpManager $server): void
    {
        // MCP resource events
        $events->listen('mcp.resource.accessed', function ($resourceUri) use ($server) {
            // Log resource access
            $server->log('info', "Resource accessed: $resourceUri");
        });

        // MCP tool events
        $events->listen('mcp.tool.executed', function ($toolName, $params) use ($server) {
            // Log tool execution
            $server->log('info', "Tool executed: $toolName");
        });

        // MCP server events
        $events->listen('mcp.server.started', function () use ($server) {
            // Log server start
            $server->log('info', 'Server started');
        });

        $events->listen('mcp.server.stopped', function () use ($server) {
            // Log server stop
            $server->log('info', 'Server stopped');
        });

        // MCP transport events
        $events->listen('mcp.transport.connected', function ($transport) use ($server) {
            // Log transport connection
            $server->log('info', 'Transport connected: ' . get_class($transport));
        });

        $events->listen('mcp.transport.disconnected', function ($transport) use ($server) {
            // Log transport disconnection
            $server->log('info', 'Transport disconnected: ' . get_class($transport));
        });

        // MCP request events
        $events->listen('mcp.request.received', function ($request) use ($server) {
            // Log request received
            $server->log('info', 'Request received');
        });

        $events->listen('mcp.response.sent', function ($response) use ($server) {
            // Log response sent
            $server->log('info', 'Response sent');
        });
    }
}

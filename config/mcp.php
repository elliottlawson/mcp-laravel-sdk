<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the Laravel MCP server.
    | The Model Context Protocol (MCP) enables bidirectional communication
    | between AI models and your Laravel application.
    |
    */

    // Server information
    'server' => [
        'name' => env('MCP_SERVER_NAME', 'Laravel MCP Server'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
        'heartbeat_interval' => env('MCP_HEARTBEAT_INTERVAL', 30), // seconds
    ],

    // Server capabilities
    'capabilities' => [
        'resources' => true,
        'tools' => true,
        'prompts' => true,
        'logging' => [
            'level' => env('MCP_LOG_LEVEL', 'info'),
        ],
    ],

    // HTTP settings
    'http' => [
        'middleware' => ['api'],
        'prefix' => env('MCP_ROUTE_PREFIX', 'mcp'),
        'cors' => [
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
        ],
    ],

    // SSE (Server-Sent Events) settings
    'sse' => [
        'enabled' => true,
        'heartbeat_interval' => env('MCP_HEARTBEAT_INTERVAL', 30), // seconds
        'reconnect_time' => 3000, // milliseconds
        'buffer_size' => 4096, // bytes
    ],

    // Authentication settings
    'auth' => [
        'enabled' => env('MCP_AUTH_ENABLED', false),
        'driver' => env('MCP_AUTH_DRIVER', 'token'), // options: 'token', 'session', 'sanctum'
        'guard' => env('MCP_AUTH_GUARD', 'api'),
    ],

    // Resource definitions - register your resources here or use the API
    'resources' => [
        // Example:
        // 'users' => \App\Mcp\Resources\UserResource::class,
        // 'posts' => [
        //     'handler' => \App\Mcp\Resources\PostResource::class,
        //     'options' => [
        //         'relations' => ['author', 'comments'],
        //         'limit' => 100,
        //     ],
        // ],
    ],

    // Tool definitions - register your tools here or use the API
    'tools' => [
        // Example:
        // 'file_search' => [
        //     'description' => 'Search for files in the application',
        //     'parameters' => [
        //         'type' => 'object',
        //         'properties' => [
        //             'query' => ['type' => 'string', 'description' => 'Search query'],
        //             'path' => ['type' => 'string', 'description' => 'Path to search in'],
        //         ],
        //         'required' => ['query']
        //     ],
        //     'handler' => \App\Mcp\Tools\FileSearchTool::class,
        // ],
    ],

    // Prompt definitions - register your prompts here or use the API
    'prompts' => [
        // Example:
        // 'system' => 'You are a helpful assistant integrated with Laravel.',
        // 'greeting' => [
        //     'content' => 'Hello, how can I assist you with your Laravel application today?',
        //     'variables' => [
        //         'app_name' => config('app.name'),
        //     ],
        // ],
    ],
];

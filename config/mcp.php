<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the MCP server.
    |
    */

    // Server information
    'name' => env('MCP_SERVER_NAME', 'Laravel MCP Server'),

    // Server capabilities
    'capabilities' => [
        'supports_streaming' => true,
        'supports_binary_transfer' => false,
    ],

    // Resource definitions
    'resources' => [
        // Example:
        // 'users' => App\Mcp\Resources\UserResource::class,
        // 'posts' => [
        //     'class' => App\Mcp\Resources\PostResource::class,
        //     'options' => [
        //         'max_results' => 100,
        //     ],
        // ],
    ],

    // Tool definitions
    'tools' => [
        // Example:
        // 'file_search' => App\Mcp\Tools\FileSearchTool::class,
        // 'database_query' => [
        //     'class' => App\Mcp\Tools\DatabaseQueryTool::class,
        //     'options' => [
        //         'timeout' => 5,
        //     ],
        // ],
    ],

    // Prompt definitions
    'prompts' => [
        // Example:
        // 'system' => 'You are a helpful assistant integrated with Laravel.',
        // 'welcome' => [
        //     'content' => 'Welcome to the Laravel MCP server!',
        // ],
    ],

    // HTTP settings
    'http' => [
        'middleware' => ['api'],
        'prefix' => 'mcp',
        'cors' => [
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
        ],
    ],

    // SSE (Server-Sent Events) settings
    'sse' => [
        'enabled' => true,
        'heartbeat_interval' => 30, // seconds
    ],

    // Authentication settings
    'auth' => [
        'enabled' => false,
        'driver' => 'token', // options: 'token', 'session', 'oauth'
        'guard' => 'api',
    ],
];

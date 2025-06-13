# Laravel MCP Server

A Laravel-native implementation of the Model Context Protocol (MCP) Server, allowing seamless integration of MCP capabilities into Laravel applications. Compatible with Laravel 10.x, 11.x, and 12.x, with optimizations for the latest Laravel versions.

## Installation

You can install the package via composer:

```bash
composer require elliottlawson/mcp-laravel-sdk
```

The service provider will be automatically registered through Laravel's package discovery.

## Configuration

### Laravel 10 and 11

For Laravel 10 and 11, publish the configuration file using:

```bash
php artisan vendor:publish --tag=mcp-config
```

This will create a `config/mcp.php` file that you can customize.

### Laravel 12

For Laravel 12, the configuration should be placed in the `bootstrap/mcp.php` file, following Laravel 12's new configuration approach. Create this file manually or publish it using:

```bash
php artisan vendor:publish --tag=mcp-config
```

## Features

- Full Laravel-native implementation with no external JSON-RPC dependencies
- Proper SSE implementation that works with Laravel's response lifecycle
- Direct integration with Laravel's Eloquent models
- Simple registration API for resources, tools, and prompts
- JSON-RPC 2.0 compliant API
- Support for batch requests
- Comprehensive error handling

## Basic Usage

### Registering Resources, Tools, and Prompts

You can register resources, tools, and prompts in your service provider or directly in your application code:

```php
use ElliottLawson\LaravelMcp\Facades\Mcp;
use App\Models\User;
use ElliottLawson\LaravelMcp\Tools\CommandTool;
use ElliottLawson\LaravelMcp\Prompts\FilePrompt;

// Register a resource from an Eloquent model
Mcp::resource('users', User::class);

// Register a tool with a schema and handler
Mcp::tool('echo', [
    'type' => 'object',
    'properties' => [
        'message' => [
            'type' => 'string',
            'description' => 'The message to echo'
        ]
    ]
], new CommandTool('echo', [
    'command' => 'echo',
    'args' => ['message']
]));

// Register a prompt
Mcp::prompt('system', 'You are a helpful assistant integrated with Laravel.');
// Or from a file
Mcp::prompt('system', new FilePrompt('system', storage_path('prompts/system.txt')));
```

### Configuration File

Here's an example configuration file:

```php
<?php

// For Laravel 10-11: config/mcp.php
// For Laravel 12: bootstrap/mcp.php

return [
    // Server information
    'name' => env('MCP_SERVER_NAME', 'Laravel MCP Server'),
    'version' => env('MCP_SERVER_VERSION', '1.0.0'),
    
    // HTTP configuration
    'http' => [
        'route_prefix' => 'mcp',
        'middleware' => ['web'],
        'cors' => [
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'X-Requested-With'],
        ],
    ],
    
    // SSE configuration
    'sse' => [
        'heartbeat_interval' => 30, // seconds
        'max_execution_time' => 0, // 0 for no limit
        'retry_interval' => 3000, // milliseconds
    ],
    
    // Register resources
    'resources' => [
        'users' => App\Mcp\Resources\UserResource::class,
        // Or register a model directly
        'products' => App\Models\Product::class,
    ],
    
    // Register tools
    'tools' => [
        'calculator' => App\Mcp\Tools\CalculatorTool::class,
    ],
    
    // Register prompts
    'prompts' => [
        'system' => 'You are a helpful assistant integrated with Laravel.',
        // Or reference a file
        'chat' => resource_path('prompts/chat.txt'),
    ],
];
```

## Creating Custom Components

### Custom Resource

```php
namespace App\Mcp\Resources;

use ElliottLawson\LaravelMcp\Resources\BaseResource;
use ElliottLawson\LaravelMcp\Contracts\ResourceContract;

class CustomResource implements ResourceContract
{
    public function getData(array $params)
    {
        // Implement your resource logic here
        return [
            'data' => [
                // Your resource data
            ],
            'meta' => [
                'total' => 1,
                'per_page' => 15,
                'current_page' => 1,
            ],
        ];
    }
}
```

### Custom Tool

```php
namespace App\Mcp\Tools;

use ElliottLawson\LaravelMcp\Tools\BaseTool;
use ElliottLawson\LaravelMcp\Contracts\ToolContract;

class CalculatorTool implements ToolContract
{
    public function execute(array $params)
    {
        $a = $params['a'] ?? 0;
        $b = $params['b'] ?? 0;
        $operation = $params['operation'] ?? 'add';
        
        switch ($operation) {
            case 'add':
                $result = $a + $b;
                break;
            case 'subtract':
                $result = $a - $b;
                break;
            case 'multiply':
                $result = $a * $b;
                break;
            case 'divide':
                $result = $b != 0 ? $a / $b : 'Error: Division by zero';
                break;
            default:
                $result = 'Error: Unknown operation';
        }
        
        return [
            'result' => $result,
            'operation' => $operation,
            'a' => $a,
            'b' => $b,
        ];
    }
}
```

## Making MCP Requests

### JSON-RPC Request Format

```json
{
    "jsonrpc": "2.0",
    "method": "resource.get",
    "params": {
        "resource": "users",
        "query": {
            "id": 1
        }
    },
    "id": 1
}
```

### Available Methods

- `server.info` - Get server information
- `resource.get` - Get a resource
- `tool.execute` - Execute a tool
- `prompt.get` - Get a prompt

### Example Response

```json
{
    "jsonrpc": "2.0",
    "result": {
        "data": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com"
        },
        "meta": {
            "total": 1,
            "per_page": 15,
            "current_page": 1
        }
    },
    "id": 1
}
```

## SSE Connection

The MCP server supports Server-Sent Events (SSE) for real-time communication. Connect to the SSE endpoint:

```javascript
const clientId = 'your-client-id';
const eventSource = new EventSource(`/mcp/sse?client_id=${clientId}`);

eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Received data:', data);
};

eventSource.addEventListener('error', (event) => {
    console.error('SSE connection error:', event);
    eventSource.close();
});
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

# Laravel MCP Server

A Laravel wrapper for the Model Context Protocol (MCP) Server, allowing seamless integration of MCP capabilities into Laravel applications. Compatible with Laravel 9.x, 10.x, 11.x, and 12.x.

## Installation

You can install the package via composer:

```bash
composer require elliottlawson/laravel-mcp-server
```

The service provider will be automatically registered through Laravel's package discovery.

## Publishing the Configuration

You can publish the configuration file using:

```bash
php artisan vendor:publish --tag=mcp-config
```

This will create a `config/mcp.php` file that you can customize.

## Features

- Full integration with Laravel's ecosystem (Eloquent, Events, etc.)
- Support for Server-Sent Events (SSE) with Laravel-optimized transport
- Automatic resource registration from Eloquent models
- Simplified tool and prompt management
- Easy server startup with artisan command

## Usage

### Basic Usage

```php
use ElliottLawson\LaravelMcp\Facades\Mcp;

// Register a resource
Mcp::resource('users', '/users/{id}', function($params) {
    // Handle resource request
});

// Register a tool
Mcp::tool('file_search', [
    'type' => 'object',
    'properties' => [
        'query' => ['type' => 'string']
    ]
], function($params) {
    // Handle tool execution
});

// Register a prompt
Mcp::prompt('system', 'You are a helpful assistant integrated with Laravel.');
```

### Eloquent Model Integration

You can easily expose Eloquent models as MCP resources:

```php
use App\Models\Product;

// Register a model as a resource
Mcp::model(Product::class);

// With custom URI template and options
Mcp::model(Product::class, '/catalog/products/{id}', [
    'attributes' => ['id', 'name', 'price', 'description'],
    'per_page' => 25
]);
```

### Configuration

You can configure resources, tools, and prompts in the `config/mcp.php` file:

```php
// config/mcp.php
return [
    'name' => 'My Laravel MCP Server',
    'version' => '1.0.0',
    
    'capabilities' => [
        'resources' => [
            'enabled' => true,
            'list_changes' => false
        ],
        'tools' => true,
        'prompts' => true,
        'logging' => [
            'level' => 'info'
        ]
    ],
    
    'resources' => [
        'users' => App\Mcp\Resources\UserResource::class,
    ],
    
    'tools' => [
        'file_search' => App\Mcp\Tools\FileSearchTool::class,
    ],
    
    'prompts' => [
        'system' => 'You are a helpful assistant integrated with Laravel.',
    ],
    
    'http' => [
        'route_prefix' => 'mcp',
        'middleware' => ['web'],
    ],
    
    'sse' => [
        'heartbeat_interval' => 30,
    ],
];
```

### Starting the Server

You can start the MCP server using the provided Artisan command:

```bash
php artisan mcp:serve
```

With options:

```bash
php artisan mcp:serve --host=0.0.0.0 --port=9000 --debug
```

## Creating Custom Resources

You can create custom resources by extending the `EloquentResource` class:

```php
namespace App\Mcp\Resources;

use ElliottLawson\LaravelMcp\Support\EloquentResource;
use App\Models\User;

class UserResource extends EloquentResource
{
    protected string $modelClass = User::class;
    
    protected array $attributes = ['id', 'name', 'email', 'created_at'];
    
    protected array $relationships = [
        'posts' => 'hasMany',
        'profile' => 'hasOne'
    ];
}
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

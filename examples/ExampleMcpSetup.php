<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use ElliottLawson\LaravelMcp\McpManager;
use App\Models\User;
use App\Models\Post;
use App\Resources\UserResource;
use App\Tools\WeatherTool;
use ElliottLawson\LaravelMcp\Resources\ModelResource;
use ElliottLawson\LaravelMcp\Prompts\FilePrompt;
use ElliottLawson\LaravelMcp\Tools\HttpTool;

/**
 * Example service provider for setting up MCP in a Laravel application.
 */
class ExampleMcpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register any services here
    }

    /**
     * Bootstrap services.
     */
    public function boot(McpManager $mcp): void
    {
        // Register resources
        $this->registerResources($mcp);
        
        // Register tools
        $this->registerTools($mcp);
        
        // Register prompts
        $this->registerPrompts($mcp);
    }
    
    /**
     * Register MCP resources.
     */
    protected function registerResources(McpManager $mcp): void
    {
        // Register a model resource using the ModelResource class
        $mcp->resource('users', function () {
            return new ModelResource('users', User::class, [
                'fields' => ['id', 'name', 'email', 'created_at'],
                'with' => ['posts'],
                'per_page' => 10,
            ]);
        });
        
        // Register a model resource using a closure
        $mcp->resource('posts', function ($params) {
            $query = Post::query();
            
            // Apply filters
            if (isset($params['user_id'])) {
                $query->where('user_id', $params['user_id']);
            }
            
            // Apply pagination
            $perPage = $params['per_page'] ?? 15;
            $page = $params['page'] ?? 1;
            
            return $query->paginate($perPage, ['*'], 'page', $page);
        });
        
        // Register a custom resource class
        $mcp->resource('custom_users', function () {
            return new UserResource();
        });
    }
    
    /**
     * Register MCP tools.
     */
    protected function registerTools(McpManager $mcp): void
    {
        // Register an HTTP tool
        $mcp->tool('http', function () {
            return new HttpTool('http', [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Laravel MCP Client',
                ],
            ]);
        });
        
        // Register a tool using a closure
        $mcp->tool('echo', function ($params) {
            return [
                'message' => $params['message'] ?? 'Hello, world!',
                'timestamp' => now()->timestamp,
            ];
        }, [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'The message to echo',
                ],
            ],
        ]);
        
        // Register a custom tool class
        $mcp->tool('weather', function () {
            return new WeatherTool('weather', [
                'api_key' => env('WEATHER_API_KEY'),
            ]);
        });
    }
    
    /**
     * Register MCP prompts.
     */
    protected function registerPrompts(McpManager $mcp): void
    {
        // Register a file prompt
        $mcp->prompt('welcome', function () {
            return new FilePrompt('welcome', resource_path('prompts/welcome.txt'), [
                'description' => 'Welcome message for new users',
            ]);
        });
        
        // Register a prompt using a closure
        $mcp->prompt('greeting', function ($variables) {
            $template = 'Hello, {{name}}! Welcome to {{app_name}}.';
            
            // Replace variables in the format {{variable_name}}
            return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($variables) {
                $key = trim($matches[1]);
                return $variables[$key] ?? $matches[0];
            }, $template);
        });
    }
}

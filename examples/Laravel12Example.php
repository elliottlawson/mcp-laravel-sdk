<?php

namespace App\Bootstrap;

use Illuminate\Support\ServiceProvider;
use ElliottLawson\LaravelMcp\McpManager;
use App\Models\User;
use App\Models\Post;
use ElliottLawson\LaravelMcp\Resources\ModelResource;
use ElliottLawson\LaravelMcp\Prompts\FilePrompt;
use ElliottLawson\LaravelMcp\Tools\HttpTool;
use ElliottLawson\LaravelMcp\Tools\CommandTool;

/**
 * Example bootstrap class for setting up MCP in a Laravel 12 application.
 * 
 * In Laravel 12, provider registrations are moved to the bootstrap directory.
 */
class McpBootstrap
{
    /**
     * Bootstrap MCP services.
     */
    public function __invoke(McpManager $mcp): void
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
        // Register a model resource for users
        $mcp->resource('users', new ModelResource('users', User::class, [
            'fields' => ['id', 'name', 'email', 'created_at'],
            'with' => ['posts'],
            'per_page' => 10,
        ]));
        
        // Register a model resource for posts
        $mcp->resource('posts', new ModelResource('posts', Post::class, [
            'fields' => ['id', 'title', 'content', 'user_id', 'created_at'],
            'with' => ['user'],
            'per_page' => 15,
        ]));
        
        // Register a resource with a callback function
        $mcp->resource('recent_posts', function ($params) {
            return Post::with('user')
                ->orderBy('created_at', 'desc')
                ->take($params['limit'] ?? 5)
                ->get();
        });
    }
    
    /**
     * Register MCP tools.
     */
    protected function registerTools(McpManager $mcp): void
    {
        // Register an HTTP tool for making API requests
        $mcp->tool('http', new HttpTool('http', [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Laravel MCP Client',
                'Accept' => 'application/json',
            ],
        ]));
        
        // Register a command tool for executing shell commands
        $mcp->tool('command', new CommandTool('command', [
            'timeout' => 60,
            'cwd' => base_path(),
        ]));
        
        // Register a simple tool with a callback function
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
    }
    
    /**
     * Register MCP prompts.
     */
    protected function registerPrompts(McpManager $mcp): void
    {
        // Register a file prompt
        $mcp->prompt('welcome', new FilePrompt('welcome', resource_path('prompts/welcome.txt'), [
            'description' => 'Welcome message for new users',
        ]));
        
        // Register a simple prompt with a string template
        $mcp->prompt('greeting', 'Hello, {{name}}! Welcome to {{app_name}}.');
        
        // Register a prompt with a callback function
        $mcp->prompt('dynamic_greeting', function ($variables) {
            $time = now()->hour;
            
            if ($time < 12) {
                $greeting = 'Good morning';
            } elseif ($time < 18) {
                $greeting = 'Good afternoon';
            } else {
                $greeting = 'Good evening';
            }
            
            return "{$greeting}, {{name}}! Welcome to {{app_name}}.";
        });
    }
}

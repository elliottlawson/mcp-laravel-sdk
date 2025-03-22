<?php

namespace ElliottLawson\LaravelMcp;

use Illuminate\Database\Eloquent\Model;
use ElliottLawson\McpPhpSdk\Server\McpServer;
use Illuminate\Contracts\Foundation\Application;
use ElliottLawson\McpPhpSdk\Server\ServerCapabilities;
use ElliottLawson\LaravelMcp\Support\ResourceRegistrar;
use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;

/**
 * Manages the MCP server and its resources, tools, and prompts.
 */
class McpManager
{
    /**
     * The MCP server instance.
     */
    protected ?McpServer $server = null;

    /**
     * The Laravel application instance.
     */
    protected Application $app;

    /**
     * The resource registrar instance.
     */
    protected ?ResourceRegistrar $resourceRegistrar = null;

    /**
     * Create a new MCP Manager instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get or create the MCP server instance.
     */
    public function server(): McpServer
    {
        if ($this->server === null) {
            $serverConfig = [
                'name' => $this->app->make('config')->get('mcp.name', 'Laravel MCP Server'),
                'version' => $this->app->make('config')->get('mcp.version', '1.0.0'),
            ];

            // Add capabilities configuration
            $capabilities = $this->configureCapabilities();
            if ($capabilities) {
                $serverConfig['capabilities'] = $capabilities;
            }

            $this->server = new McpServer($serverConfig);

            // Register resources from config if any
            $this->registerConfiguredResources();

            // Register tools from config if any
            $this->registerConfiguredTools();

            // Register prompts from config if any
            $this->registerConfiguredPrompts();
        }

        return $this->server;
    }

    /**
     * Configure server capabilities from config.
     */
    protected function configureCapabilities(): ?ServerCapabilities
    {
        $capConfig = $this->app->make('config')->get('mcp.capabilities', []);
        if (empty($capConfig)) {
            return null;
        }

        $capabilities = ServerCapabilities::create();

        // Configure resource capabilities
        if (isset($capConfig['resources'])) {
            $capabilities = $capabilities->withResources(
                $capConfig['resources']['enabled'] ?? true,
                $capConfig['resources']['list_changes'] ?? false
            );
        }

        // Configure tool capabilities
        if (isset($capConfig['tools'])) {
            $capabilities = $capabilities->withTools($capConfig['tools'] ?? true);
        }

        // Configure prompt capabilities
        if (isset($capConfig['prompts'])) {
            $capabilities = $capabilities->withPrompts($capConfig['prompts'] ?? true);
        }

        // Configure logging capabilities
        if (isset($capConfig['logging'])) {
            $capabilities = $capabilities->withLogging($capConfig['logging']['level'] ?? 'info');
        }

        return $capabilities;
    }

    /**
     * Register a resource with the MCP server.
     */
    public function resource(string $name, $uriPattern, ?callable $handler = null): self
    {
        $this->server()->resource($name, $uriPattern, $handler);

        return $this;
    }

    /**
     * Register resources from the configuration.
     */
    protected function registerConfiguredResources(): void
    {
        $resources = $this->app->make('config')->get('mcp.resources', []);

        foreach ($resources as $uri => $resourceConfig) {
            if (is_string($resourceConfig)) {
                // If the resource is just a class name
                $resource = $this->app->make($resourceConfig);
                $this->server()->resource($uri, $resource, []);
            } elseif (is_array($resourceConfig) && isset($resourceConfig['class'])) {
                // If the resource is a configuration array
                $class = $resourceConfig['class'];
                $resource = $this->app->make($class);

                // Apply any additional configuration
                if (isset($resourceConfig['options']) && is_array($resourceConfig['options'])) {
                    $this->server()->resource($uri, $resource, $resourceConfig['options']);
                } else {
                    $this->server()->resource($uri, $resource, []);
                }
            }
        }
    }

    /**
     * Register tools from the configuration.
     */
    protected function registerConfiguredTools(): void
    {
        $tools = $this->app->make('config')->get('mcp.tools', []);

        foreach ($tools as $name => $toolConfig) {
            if (is_string($toolConfig)) {
                // If the tool is just a class name
                $tool = $this->app->make($toolConfig);
                $this->server()->tool($name, $tool, []);
            } elseif (is_array($toolConfig) && isset($toolConfig['class'])) {
                // If the tool is a configuration array
                $class = $toolConfig['class'];
                $tool = $this->app->make($class);

                // Apply any additional configuration for schema and handler
                $schema = $toolConfig['schema'] ?? null;
                $handler = $toolConfig['handler'] ?? null;

                if ($schema && $handler) {
                    $this->server()->tool($name, $schema, $handler);
                } else {
                    $this->server()->tool($name, $tool, []);
                }
            }
        }
    }

    /**
     * Register prompts from the configuration.
     */
    protected function registerConfiguredPrompts(): void
    {
        $prompts = $this->app->make('config')->get('mcp.prompts', []);

        foreach ($prompts as $name => $promptConfig) {
            if (is_string($promptConfig)) {
                // If the prompt is just a string
                $this->server()->prompt($name, $promptConfig, []);
            } elseif (is_array($promptConfig) && isset($promptConfig['text'])) {
                // If the prompt is a configuration array
                $text = $promptConfig['text'];

                // Apply any additional configuration
                if (isset($promptConfig['schema']) && isset($promptConfig['handler'])) {
                    $this->server()->prompt($name, $promptConfig['schema'], $promptConfig['handler']);
                } else {
                    $this->server()->prompt($name, $text, []);
                }
            }
        }
    }

    /**
     * Register a tool with the MCP server.
     */
    public function tool(string $name, $schema, callable $handler): self
    {
        $this->server()->tool($name, $schema, $handler);

        return $this;
    }

    /**
     * Register a prompt with the MCP server.
     */
    public function prompt(string $name, $schema, ?callable $handler = null): self
    {
        $this->server()->prompt($name, $schema, $handler);

        return $this;
    }

    /**
     * Get the resource registrar instance.
     */
    public function getResourceRegistrar(): ResourceRegistrar
    {
        if ($this->resourceRegistrar === null) {
            $this->resourceRegistrar = new ResourceRegistrar($this);
        }

        return $this->resourceRegistrar;
    }

    /**
     * Register a model as a resource.
     */
    public function model($model, ?string $uriTemplate = null, array $options = []): self
    {
        $this->getResourceRegistrar()->register($model, $uriTemplate, $options);

        return $this;
    }

    /**
     * Connect a transport to the server.
     */
    public function connect(TransportInterface $transport): self
    {
        $this->server()->connect($transport);

        return $this;
    }

    /**
     * Log a message to the MCP server.
     */
    public function log(string $level, string $message, array $context = []): self
    {
        $this->server()->log($level, $message, null, $context);

        return $this;
    }
}

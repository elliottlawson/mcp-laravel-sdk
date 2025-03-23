<?php

namespace ElliottLawson\LaravelMcp;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Sajya\Server\Procedure;
use ElliottLawson\LaravelMcp\Procedures\ResourceProcedure;
use ElliottLawson\LaravelMcp\Procedures\ToolProcedure;
use ElliottLawson\LaravelMcp\Procedures\PromptProcedure;
use ElliottLawson\LaravelMcp\Procedures\ServerProcedure;
use ElliottLawson\LaravelMcp\Contracts\ResourceContract;
use ElliottLawson\LaravelMcp\Contracts\ToolContract;
use ElliottLawson\LaravelMcp\Contracts\PromptContract;
use ElliottLawson\LaravelMcp\Events\ResourceRegistered;
use ElliottLawson\LaravelMcp\Events\ToolRegistered;
use ElliottLawson\LaravelMcp\Events\PromptRegistered;
use ElliottLawson\LaravelMcp\Support\ResourceRegistrar;
use ElliottLawson\LaravelMcp\Support\ToolExecutor;
use ElliottLawson\LaravelMcp\Support\PromptManager;
use ElliottLawson\LaravelMcp\Exceptions\InvalidResourceException;
use ElliottLawson\LaravelMcp\Exceptions\InvalidToolException;
use ElliottLawson\LaravelMcp\Exceptions\InvalidPromptException;

/**
 * Manages the MCP server and its resources, tools, and prompts.
 */
class McpManager
{
    /**
     * The Laravel application instance.
     */
    protected Application $app;

    /**
     * The registered resources.
     */
    protected array $resources = [];

    /**
     * The registered tools.
     */
    protected array $tools = [];

    /**
     * The registered prompts.
     */
    protected array $prompts = [];

    /**
     * The resource registrar instance.
     */
    protected ?ResourceRegistrar $resourceRegistrar = null;

    /**
     * The tool executor instance.
     */
    protected ?ToolExecutor $toolExecutor = null;

    /**
     * The prompt manager instance.
     */
    protected ?PromptManager $promptManager = null;

    /**
     * The registered JSON-RPC procedures.
     */
    protected array $procedures = [];

    /**
     * Create a new MCP Manager instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->registerCoreProcedures();
        $this->loadConfiguredItems();
    }

    /**
     * Register the core JSON-RPC procedures.
     */
    protected function registerCoreProcedures(): void
    {
        // Register server procedure for handling server-related methods
        $this->procedures[] = new ServerProcedure($this);
        
        // Register resource procedure for handling resource-related methods
        $this->procedures[] = new ResourceProcedure($this);
        
        // Register tool procedure for handling tool-related methods
        $this->procedures[] = new ToolProcedure($this);
        
        // Register prompt procedure for handling prompt-related methods
        $this->procedures[] = new PromptProcedure($this);
    }

    /**
     * Get all registered JSON-RPC procedures.
     */
    public function getProcedures(): array
    {
        return $this->procedures;
    }

    /**
     * Register a custom JSON-RPC procedure.
     */
    public function registerProcedure(Procedure $procedure): self
    {
        $this->procedures[] = $procedure;
        
        return $this;
    }

    /**
     * Load resources, tools, and prompts from configuration.
     */
    protected function loadConfiguredItems(): void
    {
        $this->registerConfiguredResources();
        $this->registerConfiguredTools();
        $this->registerConfiguredPrompts();
    }

    /**
     * Register a resource with the MCP server.
     *
     * @param string $name The name of the resource
     * @param mixed $handler The resource handler (class name, instance, or closure)
     * @param array $options Additional options for the resource
     * 
     * @return self
     */
    public function resource(string $name, $handler, array $options = []): self
    {
        // Normalize the resource name
        $name = Str::snake($name);
        
        // Resolve the handler if it's a class name
        if (is_string($handler) && class_exists($handler)) {
            $handler = $this->app->make($handler);
        }
        
        // Check if the handler is valid
        if (!$this->isValidResourceHandler($handler)) {
            throw new InvalidResourceException("Invalid resource handler for '{$name}'");
        }
        
        // Register the resource
        $this->resources[$name] = [
            'handler' => $handler,
            'options' => $options,
        ];
        
        // Dispatch event
        if (class_exists(ResourceRegistered::class)) {
            event(new ResourceRegistered($name, $handler, $options));
        }
        
        return $this;
    }

    /**
     * Check if a resource handler is valid.
     */
    protected function isValidResourceHandler($handler): bool
    {
        return $handler instanceof ResourceContract
            || $handler instanceof Model
            || is_callable($handler);
    }

    /**
     * Register a tool with the MCP server.
     *
     * @param string $name The name of the tool
     * @param mixed $schema The JSON schema for the tool parameters
     * @param mixed $handler The tool handler (class name, instance, or closure)
     * 
     * @return self
     */
    public function tool(string $name, $schema, $handler = null): self
    {
        // Normalize the tool name
        $name = Str::snake($name);
        
        // If only schema is provided and it's a class or object
        if ($handler === null && (is_string($schema) || is_object($schema))) {
            if (is_string($schema) && class_exists($schema)) {
                $schema = $this->app->make($schema);
            }
            
            if ($schema instanceof ToolContract) {
                $handler = $schema;
                $schema = $schema->getSchema();
            }
        }
        
        // Resolve the handler if it's a class name
        if (is_string($handler) && class_exists($handler)) {
            $handler = $this->app->make($handler);
        }
        
        // Check if the handler is valid
        if (!$this->isValidToolHandler($handler)) {
            throw new InvalidToolException("Invalid tool handler for '{$name}'");
        }
        
        // Register the tool
        $this->tools[$name] = [
            'schema' => $schema,
            'handler' => $handler,
        ];
        
        // Dispatch event
        if (class_exists(ToolRegistered::class)) {
            event(new ToolRegistered($name, $schema, $handler));
        }
        
        return $this;
    }

    /**
     * Check if a tool handler is valid.
     */
    protected function isValidToolHandler($handler): bool
    {
        return $handler instanceof ToolContract
            || is_callable($handler);
    }

    /**
     * Register a prompt with the MCP server.
     *
     * @param string $name The name of the prompt
     * @param mixed $content The prompt content or schema
     * @param mixed $handler The prompt handler (optional)
     * 
     * @return self
     */
    public function prompt(string $name, $content, $handler = null): self
    {
        // Normalize the prompt name
        $name = Str::snake($name);
        
        // If content is a class name, resolve it
        if (is_string($content) && class_exists($content)) {
            $content = $this->app->make($content);
        }
        
        // If content is a PromptContract instance
        if ($content instanceof PromptContract) {
            $handler = $content;
            $content = $content->getContent();
        }
        
        // Resolve the handler if it's a class name
        if (is_string($handler) && class_exists($handler)) {
            $handler = $this->app->make($handler);
        }
        
        // Register the prompt
        $this->prompts[$name] = [
            'content' => $content,
            'handler' => $handler,
        ];
        
        // Dispatch event
        if (class_exists(PromptRegistered::class)) {
            event(new PromptRegistered($name, $content, $handler));
        }
        
        return $this;
    }

    /**
     * Get all registered resources.
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Get all registered tools.
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Get all registered prompts.
     */
    public function getPrompts(): array
    {
        return $this->prompts;
    }

    /**
     * Get server information.
     */
    public function getServerInfo(): array
    {
        return [
            'name' => config('mcp.server.name', 'Laravel MCP Server'),
            'version' => config('mcp.server.version', '1.0.0'),
            'capabilities' => $this->getCapabilities(),
        ];
    }

    /**
     * Get server capabilities.
     */
    public function getCapabilities(): array
    {
        $config = config('mcp.capabilities', []);
        
        return [
            'resources' => $config['resources'] ?? true,
            'tools' => $config['tools'] ?? true,
            'prompts' => $config['prompts'] ?? true,
            'logging' => $config['logging']['level'] ?? 'info',
        ];
    }

    /**
     * Register resources from the configuration.
     */
    protected function registerConfiguredResources(): void
    {
        $resources = config('mcp.resources', []);

        foreach ($resources as $name => $resourceConfig) {
            if (is_string($resourceConfig)) {
                // If the resource is just a class name
                $this->resource($name, $resourceConfig);
            } elseif (is_array($resourceConfig) && isset($resourceConfig['handler'])) {
                // If the resource is a configuration array
                $handler = $resourceConfig['handler'];
                $options = $resourceConfig['options'] ?? [];
                
                $this->resource($name, $handler, $options);
            }
        }
    }

    /**
     * Register tools from the configuration.
     */
    protected function registerConfiguredTools(): void
    {
        $tools = config('mcp.tools', []);

        foreach ($tools as $name => $toolConfig) {
            if (is_string($toolConfig)) {
                // If the tool is just a class name
                $this->tool($name, $toolConfig);
            } elseif (is_array($toolConfig)) {
                if (isset($toolConfig['handler'])) {
                    // If the tool has a handler and schema
                    $schema = $toolConfig['parameters'] ?? $toolConfig['schema'] ?? [];
                    $handler = $toolConfig['handler'];
                    
                    $this->tool($name, $schema, $handler);
                }
            }
        }
    }

    /**
     * Register prompts from the configuration.
     */
    protected function registerConfiguredPrompts(): void
    {
        $prompts = config('mcp.prompts', []);

        foreach ($prompts as $name => $promptConfig) {
            if (is_string($promptConfig)) {
                // If the prompt is just a string
                $this->prompt($name, $promptConfig);
            } elseif (is_array($promptConfig)) {
                if (isset($promptConfig['content'])) {
                    // If the prompt has content and possibly a handler
                    $content = $promptConfig['content'];
                    $handler = $promptConfig['handler'] ?? null;
                    
                    $this->prompt($name, $content, $handler);
                }
            }
        }
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
     * Get the tool executor instance.
     */
    public function getToolExecutor(): ToolExecutor
    {
        if ($this->toolExecutor === null) {
            $this->toolExecutor = new ToolExecutor($this);
        }
        
        return $this->toolExecutor;
    }

    /**
     * Get the prompt manager instance.
     */
    public function getPromptManager(): PromptManager
    {
        if ($this->promptManager === null) {
            $this->promptManager = new PromptManager($this);
        }
        
        return $this->promptManager;
    }
}

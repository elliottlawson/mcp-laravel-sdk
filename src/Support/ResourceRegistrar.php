<?php

namespace ElliottLawson\LaravelMcp\Support;

use ReflectionClass;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use ElliottLawson\LaravelMcp\McpManager;

/**
 * Handles registration of Eloquent models as MCP resources.
 */
class ResourceRegistrar
{
    protected McpManager $manager;

    /**
     * Create a new resource registrar.
     */
    public function __construct(McpManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Register a model as a resource.
     *
     * @param  string|Model  $model  The model class or instance
     * @param  string|null  $uriTemplate  The URI template (defaults to /{model}/{id})
     * @param  array  $options  Additional options
     */
    public function register($model, ?string $uriTemplate = null, array $options = []): void
    {
        $modelClass = is_string($model) ? $model : get_class($model);
        $reflection = new ReflectionClass($modelClass);
        $resourceName = $reflection->getShortName();

        if ($uriTemplate === null) {
            // Generate a default URI template based on the model name
            $modelName = Str::snake(Str::pluralStudly($resourceName));
            $uriTemplate = "/{$modelName}/{id}";
        }

        // Create the handler schema and function based on the model
        $schema = $this->createResourceSchema($modelClass);

        // Create a dynamic resource handler
        $handler = function ($params) use ($modelClass, $options) {
            $model = new $modelClass;

            // For a GET request with an ID, return a single model
            if (isset($params['id'])) {
                $instance = $model->find($params['id']);
                if (!$instance) {
                    return null;
                }

                // Apply any transforms if specified
                return $this->transformModel($instance, $options);
            }

            // For a list request without an ID, return a paginated list
            $query = $model->newQuery();

            // Apply any filters from the options
            if (isset($options['filter']) && is_callable($options['filter'])) {
                $query = $options['filter']($query);
            }

            // Apply pagination
            $perPage = $options['per_page'] ?? 15;
            $page = $params['page'] ?? 1;

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform the paginator items if needed
            if (isset($options['transform']) && is_callable($options['transform'])) {
                $items = $paginator->getCollection()
                    ->map(function ($item) use ($options) {
                        return $this->transformModel($item, $options);
                    })
                    ->toArray();

                // Create a transformed paginator response
                return [
                    'items' => $items,
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                ];
            }

            // Otherwise return the default paginator format
            return $paginator;
        };

        // Register with the MCP server
        $this->manager->resource($resourceName, $uriTemplate, $handler);
    }

    /**
     * Create a JSON schema for a model resource.
     */
    protected function createResourceSchema(string $modelClass): array
    {
        $model = new $modelClass;
        $table = $model->getTable();
        $reflection = new ReflectionClass($modelClass);
        $resourceName = $reflection->getShortName();

        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'string',
                    'description' => "The ID of the {$resourceName}",
                ],
                // Additional properties could be dynamically generated based on the model columns
            ],
        ];
    }

    /**
     * Transform a model based on options.
     *
     * @return mixed
     */
    protected function transformModel(Model $model, array $options)
    {
        if (isset($options['transform']) && is_callable($options['transform'])) {
            return $options['transform']($model);
        }

        // Include only specific attributes if specified
        if (isset($options['attributes']) && is_array($options['attributes'])) {
            return $model->only($options['attributes']);
        }

        // Default to the full model as an array
        return $model->toArray();
    }
}

<?php

namespace ElliottLawson\LaravelMcp\Procedures;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use ElliottLawson\LaravelMcp\Exceptions\ResourceNotFoundException;

/**
 * Procedure for handling resource-related methods.
 */
class ResourceProcedure extends BaseProcedure
{
    /**
     * The name of the procedure that will be used for RPC.
     *
     * @var string
     */
    public static string $name = 'resource';

    /**
     * List all available resources.
     *
     * @return array
     */
    public function list(): array
    {
        $resources = $this->manager->getResources();
        $result = [];

        foreach ($resources as $name => $resource) {
            $result[$name] = [
                'name' => $name,
                'metadata' => $resource['handler'] instanceof \ElliottLawson\LaravelMcp\Contracts\ResourceContract
                    ? $resource['handler']->getMetadata()
                    : [],
            ];
        }

        return $result;
    }

    /**
     * Get a resource by name.
     *
     * @param string $name The resource name
     * @param array $params The parameters for the resource request
     * @return mixed
     * @throws ResourceNotFoundException
     */
    public function get(string $name, array $params = [])
    {
        $resources = $this->manager->getResources();

        if (!isset($resources[$name])) {
            throw new ResourceNotFoundException("Resource '{$name}' not found");
        }

        $resource = $resources[$name];
        $handler = $resource['handler'];
        $options = $resource['options'] ?? [];

        // Merge options with params, with params taking precedence
        $params = array_merge($options, $params);

        try {
            // If the handler is a ResourceContract
            if ($handler instanceof \ElliottLawson\LaravelMcp\Contracts\ResourceContract) {
                return $handler->getData($params);
            }

            // If the handler is a Model
            if ($handler instanceof \Illuminate\Database\Eloquent\Model) {
                return $this->handleModelResource($handler, $params);
            }

            // If the handler is a callable
            if (is_callable($handler)) {
                return $handler($params);
            }

            // If we got here, we don't know how to handle this resource
            throw new \InvalidArgumentException("Invalid resource handler for '{$name}'");
        } catch (\Exception $e) {
            Log::error("Error getting resource '{$name}': " . $e->getMessage(), [
                'exception' => $e,
                'params' => $params,
            ]);

            throw $e;
        }
    }

    /**
     * Get the schema for a resource.
     *
     * @param string $name The resource name
     * @return array|null
     * @throws ResourceNotFoundException
     */
    public function schema(string $name): ?array
    {
        $resources = $this->manager->getResources();

        if (!isset($resources[$name])) {
            throw new ResourceNotFoundException("Resource '{$name}' not found");
        }

        $resource = $resources[$name];
        $handler = $resource['handler'];

        // If the handler is a ResourceContract
        if ($handler instanceof \ElliottLawson\LaravelMcp\Contracts\ResourceContract) {
            return $handler->getSchema();
        }

        // For other types, we don't have a schema
        return null;
    }

    /**
     * Handle a model resource.
     *
     * @param \Illuminate\Database\Eloquent\Model $model The model
     * @param array $params The parameters for the resource request
     * @return mixed
     */
    protected function handleModelResource(\Illuminate\Database\Eloquent\Model $model, array $params = [])
    {
        $query = $model->newQuery();

        // Apply filters
        if (isset($params['filters']) && is_array($params['filters'])) {
            foreach ($params['filters'] as $field => $value) {
                $query->where($field, $value);
            }
        }

        // Apply search
        if (isset($params['search']) && isset($params['searchFields']) && is_array($params['searchFields'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search, $params) {
                foreach ($params['searchFields'] as $field) {
                    $q->orWhere($field, 'LIKE', "%{$search}%");
                }
            });
        }

        // Apply sorting
        if (isset($params['sort'])) {
            $direction = isset($params['sortDirection']) && strtolower($params['sortDirection']) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($params['sort'], $direction);
        }

        // Apply relations
        if (isset($params['with']) && is_array($params['with'])) {
            $query->with($params['with']);
        }

        // Apply pagination
        $perPage = $params['perPage'] ?? 15;
        $page = $params['page'] ?? 1;

        // Check if we're fetching a single item
        if (isset($params['id'])) {
            return $query->find($params['id']);
        }

        // Return paginated results
        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}

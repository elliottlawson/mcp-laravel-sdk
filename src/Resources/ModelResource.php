<?php

namespace ElliottLawson\LaravelMcp\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Resource implementation for Eloquent models.
 */
class ModelResource extends BaseResource
{
    /**
     * The model instance or class name.
     */
    protected $model;

    /**
     * The fields to include in the resource.
     */
    protected array $fields = [];

    /**
     * The relations to include in the resource.
     */
    protected array $with = [];

    /**
     * The default number of items per page.
     */
    protected int $perPage = 15;

    /**
     * Create a new model resource instance.
     *
     * @param string $name The resource name
     * @param string|Model $model The model class or instance
     * @param array $options Additional options for the resource
     * @param array $metadata Additional metadata for the resource
     */
    public function __construct(string $name, $model, array $options = [], array $metadata = [])
    {
        parent::__construct($name, $metadata);

        $this->model = $model;
        
        // Set options
        if (isset($options['fields']) && is_array($options['fields'])) {
            $this->fields = $options['fields'];
        }
        
        if (isset($options['with']) && is_array($options['with'])) {
            $this->with = $options['with'];
        }
        
        if (isset($options['per_page']) && is_int($options['per_page'])) {
            $this->perPage = $options['per_page'];
        }
        
        // Generate schema based on model
        $this->generateSchema();
    }

    /**
     * Get the resource data.
     *
     * @param array $params The parameters for the resource request
     * @return mixed The resource data
     */
    public function getData(array $params = [])
    {
        // Get a fresh query builder
        $query = $this->getQuery();
        
        // Apply relations
        if (!empty($this->with)) {
            $query->with($this->with);
        }
        
        // Apply additional relations from params
        if (isset($params['with']) && is_array($params['with'])) {
            $query->with($params['with']);
        }
        
        // If requesting a specific ID
        if (isset($params['id'])) {
            return $this->getById($query, $params['id']);
        }
        
        // Apply filters
        $query = $this->applyFilters($query, $params);
        
        // Apply search
        $query = $this->applySearch($query, $params);
        
        // Apply sorting
        $query = $this->applySorting($query, $params);
        
        // Apply pagination
        return $this->applyPagination($query, $params);
    }

    /**
     * Get a model by ID.
     *
     * @param Builder $query The query builder
     * @param mixed $id The model ID
     * @return Model|null The model instance
     */
    protected function getById(Builder $query, $id)
    {
        $model = $query->find($id);
        
        if (!$model) {
            return null;
        }
        
        return $this->formatModel($model);
    }

    /**
     * Apply filters to the query.
     *
     * @param Builder $query The query builder
     * @param array $params The parameters for the resource request
     * @return Builder The modified query builder
     */
    protected function applyFilters(Builder $query, array $params): Builder
    {
        if (isset($params['filters']) && is_array($params['filters'])) {
            foreach ($params['filters'] as $field => $value) {
                if (is_array($value) && count($value) === 2 && isset($value[0]) && is_string($value[0])) {
                    // If the value is an array with an operator and value
                    $query->where($field, $value[0], $value[1]);
                } else {
                    // Simple equality filter
                    $query->where($field, $value);
                }
            }
        }
        
        return $query;
    }

    /**
     * Apply search to the query.
     *
     * @param Builder $query The query builder
     * @param array $params The parameters for the resource request
     * @return Builder The modified query builder
     */
    protected function applySearch(Builder $query, array $params): Builder
    {
        if (isset($params['search']) && !empty($params['search'])) {
            $search = $params['search'];
            $searchFields = $params['searchFields'] ?? $this->getSearchableFields();
            
            if (!empty($searchFields)) {
                $query->where(function (Builder $q) use ($search, $searchFields) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'LIKE', "%{$search}%");
                    }
                });
            }
        }
        
        return $query;
    }

    /**
     * Apply sorting to the query.
     *
     * @param Builder $query The query builder
     * @param array $params The parameters for the resource request
     * @return Builder The modified query builder
     */
    protected function applySorting(Builder $query, array $params): Builder
    {
        $sortField = $params['sort'] ?? 'id';
        $sortDirection = isset($params['sortDirection']) && strtolower($params['sortDirection']) === 'desc' ? 'desc' : 'asc';
        
        return $query->orderBy($sortField, $sortDirection);
    }

    /**
     * Apply pagination to the query.
     *
     * @param Builder $query The query builder
     * @param array $params The parameters for the resource request
     * @return LengthAwarePaginator The paginated results
     */
    protected function applyPagination(Builder $query, array $params)
    {
        $perPage = $params['perPage'] ?? $this->perPage;
        $page = $params['page'] ?? 1;
        
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        
        // Format each model in the paginator
        $items = $paginator->getCollection()->map(function ($model) {
            return $this->formatModel($model);
        });
        
        return new LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Format a model instance.
     *
     * @param Model $model The model instance
     * @return array The formatted model
     */
    protected function formatModel(Model $model): array
    {
        // If specific fields are defined, only include those
        if (!empty($this->fields)) {
            return $model->only($this->fields);
        }
        
        // Otherwise, return the full model as an array
        return $model->toArray();
    }

    /**
     * Get a query builder for the model.
     *
     * @return Builder The query builder
     */
    protected function getQuery(): Builder
    {
        if ($this->model instanceof Model) {
            return $this->model->newQuery();
        }
        
        return (new $this->model)->newQuery();
    }

    /**
     * Get the searchable fields for the model.
     *
     * @return array The searchable fields
     */
    protected function getSearchableFields(): array
    {
        // By default, use the fields property if set
        if (!empty($this->fields)) {
            return $this->fields;
        }
        
        // Otherwise, try to get the fillable fields from the model
        $model = $this->model instanceof Model ? $this->model : new $this->model;
        
        return $model->getFillable();
    }

    /**
     * Generate a JSON schema for the model.
     */
    protected function generateSchema(): void
    {
        $model = $this->model instanceof Model ? $this->model : new $this->model;
        $table = $model->getTable();
        
        // Start with a basic schema
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => "The ID of the {$this->name}",
                ],
            ],
        ];
        
        // Add properties based on fillable fields
        $fillable = $model->getFillable();
        foreach ($fillable as $field) {
            $schema['properties'][$field] = [
                'type' => 'string', // Default to string type
                'description' => "The {$field} of the {$this->name}",
            ];
        }
        
        $this->schema = $schema;
    }
}

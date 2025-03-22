<?php

namespace ElliottLawson\LaravelMcp\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * A trait that helps integrate Eloquent models with MCP resources.
 */
trait EloquentResourceTrait
{
    /**
     * The Eloquent model class name.
     */
    protected string $modelClass;

    /**
     * The attributes to include in the schema.
     */
    protected array $attributes = [];

    /**
     * The relationships to include.
     */
    protected array $relationships = [];

    /**
     * Build the schema for this resource.
     */
    public function getSchema(): array
    {
        $model = $this->getModelInstance();
        $table = $model->getTable();

        $properties = [];

        // Get the attribute definitions
        foreach ($this->attributes as $attribute) {
            $properties[$attribute] = $this->getPropertyType($model, $attribute);
        }

        // Add relationship properties if any
        foreach ($this->relationships as $relation => $type) {
            $properties[$relation] = [
                'type' => $type === 'hasMany' ? 'array' : 'object',
                'description' => "The $relation related to this $table",
            ];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Get a property type definition.
     */
    protected function getPropertyType(Model $model, string $attribute): array
    {
        // This is a simplified implementation - in a real-world scenario,
        // you'd want to introspect the database column type or model casts
        $cast = $model->getCasts()[$attribute] ?? null;

        return match ($cast) {
            'int', 'integer' => [
                'type' => 'integer',
                'description' => "The $attribute of the " . class_basename($model),
            ],
            'float', 'double', 'decimal' => [
                'type' => 'number',
                'description' => "The $attribute of the " . class_basename($model),
            ],
            'bool', 'boolean' => [
                'type' => 'boolean',
                'description' => "The $attribute of the " . class_basename($model),
            ],
            'array', 'json', 'collection' => [
                'type' => 'array',
                'description' => "The $attribute of the " . class_basename($model),
            ],
            default => [
                'type' => 'string',
                'description' => "The $attribute of the " . class_basename($model),
            ],
        };
    }

    /**
     * Get a model instance.
     */
    protected function getModelInstance(): Model
    {
        return app($this->modelClass);
    }

    /**
     * Get the base query for this resource.
     */
    protected function getBaseQuery(): Builder
    {
        return $this->getModelInstance()->newQuery();
    }

    /**
     * Apply filters to the query.
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        foreach ($filters as $field => $value) {
            if (in_array($field, $this->attributes)) {
                if (is_array($value) && isset($value['operator']) && isset($value['value'])) {
                    $query->where($field, $value['operator'], $value['value']);
                } else {
                    $query->where($field, $value);
                }
            }
        }

        return $query;
    }

    /**
     * Apply sorting to the query.
     */
    protected function applySort(Builder $query, array $sort): Builder
    {
        foreach ($sort as $field => $direction) {
            if (in_array($field, $this->attributes)) {
                $query->orderBy($field, $direction === 'desc' ? 'desc' : 'asc');
            }
        }

        return $query;
    }

    /**
     * Apply pagination to the query.
     */
    protected function applyPagination(Builder $query, int $page, int $perPage): Builder
    {
        return $query->skip(($page - 1) * $perPage)->take($perPage);
    }
}

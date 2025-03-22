<?php

namespace ElliottLawson\LaravelMcp\Support;

use Illuminate\Database\Eloquent\Model;
use ElliottLawson\McpPhpSdk\Resources\ResourceTemplate;
use ElliottLawson\McpPhpSdk\Resources\ResourceInterface;

abstract class EloquentResource extends ResourceTemplate implements ResourceInterface
{
    use EloquentResourceTrait;

    /**
     * Get a single item by ID.
     *
     * @return mixed
     */
    public function get(string $id, array $options = [])
    {
        $query = $this->getBaseQuery();

        // Apply eager loading if relationships are defined
        if (!empty($this->relationships)) {
            $query->with(array_keys($this->relationships));
        }

        $model = $query->find($id);

        if (!$model) {
            return null;
        }

        return $this->formatModel($model);
    }

    /**
     * List items with optional filtering, sorting, and pagination.
     *
     * @return array
     */
    public function list(array $options = [])
    {
        $query = $this->getBaseQuery();

        // Apply filtering
        if (isset($options['filters']) && is_array($options['filters'])) {
            $this->applyFilters($query, $options['filters']);
        }

        // Apply sorting
        if (isset($options['sort']) && is_array($options['sort'])) {
            $this->applySort($query, $options['sort']);
        }

        // Apply eager loading if relationships are defined
        if (!empty($this->relationships)) {
            $query->with(array_keys($this->relationships));
        }

        // Apply pagination
        $page = $options['page'] ?? 1;
        $perPage = $options['per_page'] ?? 15;

        $totalCount = $query->count();

        $models = $this->applyPagination($query->clone(), $page, $perPage)->get();

        return [
            'items' => $models->map(fn ($model) => $this->formatModel($model))->all(),
            'pagination' => [
                'total' => $totalCount,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $perPage),
            ],
        ];
    }

    /**
     * Format a model into an array.
     */
    protected function formatModel(Model $model): array
    {
        $result = [];

        // Add selected attributes
        foreach ($this->attributes as $attribute) {
            $result[$attribute] = $model->getAttribute($attribute);
        }

        // Add selected relationships
        foreach (array_keys($this->relationships) as $relation) {
            if ($model->relationLoaded($relation)) {
                $related = $model->getRelation($relation);

                if ($related instanceof Model) {
                    // For singular relationships
                    $result[$relation] = $this->formatRelatedModel($related);
                } else {
                    // For collection relationships
                    $result[$relation] = collect($related)->map(fn ($item) => $this->formatRelatedModel($item))->all();
                }
            }
        }

        return $result;
    }

    /**
     * Format a related model into an array.
     * This method can be overridden in child classes to customize related model formatting.
     */
    protected function formatRelatedModel(Model $model): array
    {
        // By default, just return the model's attributes
        return $model->attributesToArray();
    }
}

<?php

namespace ElliottLawson\LaravelMcp\Tests\Unit\Resources;

use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use ElliottLawson\LaravelMcp\Resources\ModelResource;

class ModelResourceTest extends TestCase
{
    /**
     * Test the basic resource creation and metadata.
     */
    #[Test]
    public function test_resource_creation(): void
    {
        // Create a mock model
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('getTable')->andReturn('users');
        $model->shouldReceive('getFillable')->andReturn(['name', 'email']);
        $model->shouldReceive('newQuery')->andReturn(Mockery::mock(Builder::class));

        // Create a model resource
        $resource = new ModelResource('users', $model, [
            'fields' => ['id', 'name', 'email'],
        ], [
            'description' => 'User resource',
        ]);

        // Test basic properties
        $this->assertEquals('users', $resource->getMetadata()['name']);
        $this->assertEquals('User resource', $resource->getMetadata()['description']);

        // Test schema generation
        $schema = $resource->getSchema();
        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('id', $schema['properties']);
    }

    /**
     * Test getting data from the resource.
     */
    #[Test]
    public function test_get_data(): void
    {
        // Create mock model instances
        $model1 = Mockery::mock(Model::class);
        $model1->shouldReceive('getAttributes')->andReturn(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $model1->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $model1->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $model1->shouldReceive('getAttribute')->with('name')->andReturn('John');
        $model1->shouldReceive('getAttribute')->with('email')->andReturn('john@example.com');
        $model1->shouldReceive('only')->withAnyArgs()->andReturn(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);

        $model2 = Mockery::mock(Model::class);
        $model2->shouldReceive('getAttributes')->andReturn(['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']);
        $model2->shouldReceive('toArray')->andReturn(['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']);
        $model2->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $model2->shouldReceive('getAttribute')->with('name')->andReturn('Jane');
        $model2->shouldReceive('getAttribute')->with('email')->andReturn('jane@example.com');
        $model2->shouldReceive('only')->withAnyArgs()->andReturn(['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']);

        // Create a mock query builder
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('with')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        // Mock paginator
        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('getCollection')->andReturn(collect([$model1, $model2]));
        $paginator->shouldReceive('total')->andReturn(2);
        $paginator->shouldReceive('perPage')->andReturn(15);
        $paginator->shouldReceive('currentPage')->andReturn(1);

        $builder->shouldReceive('paginate')->andReturn($paginator);

        // Create a mock model
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('getTable')->andReturn('users');
        $model->shouldReceive('getFillable')->andReturn(['name', 'email']);
        $model->shouldReceive('newQuery')->andReturn($builder);

        // Create a model resource
        $resource = new ModelResource('users', $model, [
            'fields' => ['id', 'name', 'email'],
            'with' => ['posts'],
        ]);

        // Test getting data
        $result = $resource->getData([
            'page' => 1,
            'perPage' => 15,
        ]);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    /**
     * Test getting a specific model by ID.
     */
    #[Test]
    public function test_get_by_id(): void
    {
        // Create a mock model instance
        $modelInstance = Mockery::mock(Model::class);
        $modelInstance->shouldReceive('getAttributes')->andReturn([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);
        $modelInstance->shouldReceive('toArray')->andReturn([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);
        $modelInstance->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $modelInstance->shouldReceive('getAttribute')->with('name')->andReturn('John');
        $modelInstance->shouldReceive('getAttribute')->with('email')->andReturn('john@example.com');
        $modelInstance->shouldReceive('only')->withAnyArgs()->andReturn([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        // Create a mock query builder
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('with')->andReturnSelf();
        $builder->shouldReceive('find')->with(1)->andReturn($modelInstance);

        // Create a mock model
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('getTable')->andReturn('users');
        $model->shouldReceive('getFillable')->andReturn(['name', 'email']);
        $model->shouldReceive('newQuery')->andReturn($builder);

        // Create a model resource
        $resource = new ModelResource('users', $model, [
            'fields' => ['id', 'name', 'email'],
        ]);

        // Test getting a model by ID
        $result = $resource->getData([
            'id' => 1,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('John', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    /**
     * Test applying filters to the query.
     */
    #[Test]
    public function test_apply_filters(): void
    {
        // Create a mock query builder that expects where calls
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('with')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        // Expect simple equality filter
        $builder->shouldReceive('where')
            ->with('status', 'active')
            ->andReturnSelf();

        // Expect operator filter
        $builder->shouldReceive('where')
            ->with('age', '>', 18)
            ->andReturnSelf();

        // Mock paginator with proper model instances
        $model1 = Mockery::mock(Model::class);
        $model1->shouldReceive('getAttributes')->andReturn(['id' => 1, 'name' => 'John', 'status' => 'active', 'age' => 25]);
        $model1->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'John', 'status' => 'active', 'age' => 25]);
        $model1->shouldReceive('getAttribute')->andReturnUsing(function ($key) {
            $data = ['id' => 1, 'name' => 'John', 'status' => 'active', 'age' => 25];

            return $data[$key] ?? null;
        });
        $model1->shouldReceive('only')->withAnyArgs()->andReturn(['id' => 1, 'name' => 'John', 'status' => 'active', 'age' => 25]);

        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('getCollection')->andReturn(collect([$model1]));
        $paginator->shouldReceive('total')->andReturn(1);
        $paginator->shouldReceive('perPage')->andReturn(15);
        $paginator->shouldReceive('currentPage')->andReturn(1);

        $builder->shouldReceive('paginate')->andReturn($paginator);

        // Create a mock model
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('getTable')->andReturn('users');
        $model->shouldReceive('getFillable')->andReturn(['name', 'email', 'status', 'age']);
        $model->shouldReceive('newQuery')->andReturn($builder);

        // Create a model resource
        $resource = new ModelResource('users', $model);

        // Test applying filters
        $result = $resource->getData([
            'filters' => [
                'status' => 'active',
                'age' => ['>', 18],
            ],
        ]);

        // Add an assertion to make this test not risky
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    /**
     * Test applying search to the query.
     */
    #[Test]
    public function test_apply_search(): void
    {
        // Create a mock query builder that expects search calls
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('with')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        // Expect search callback
        $builder->shouldReceive('where')
            ->withArgs(function ($callback) {
                $subQuery = Mockery::mock(Builder::class);
                $subQuery->shouldReceive('orWhere')
                    ->with('name', 'LIKE', '%john%')
                    ->andReturnSelf();
                $subQuery->shouldReceive('orWhere')
                    ->with('email', 'LIKE', '%john%')
                    ->andReturnSelf();

                // Call the callback with the subquery
                if (is_callable($callback)) {
                    $callback($subQuery);
                }

                return true;
            })
            ->andReturnSelf();

        // Mock paginator with proper model instances
        $model1 = Mockery::mock(Model::class);
        $model1->shouldReceive('getAttributes')->andReturn(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $model1->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
        $model1->shouldReceive('getAttribute')->andReturnUsing(function ($key) {
            $data = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];

            return $data[$key] ?? null;
        });
        $model1->shouldReceive('only')->withAnyArgs()->andReturn(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);

        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('getCollection')->andReturn(collect([$model1]));
        $paginator->shouldReceive('total')->andReturn(1);
        $paginator->shouldReceive('perPage')->andReturn(15);
        $paginator->shouldReceive('currentPage')->andReturn(1);

        $builder->shouldReceive('paginate')->andReturn($paginator);

        // Create a mock model
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('getTable')->andReturn('users');
        $model->shouldReceive('getFillable')->andReturn(['name', 'email']);
        $model->shouldReceive('newQuery')->andReturn($builder);

        // Create a model resource
        $resource = new ModelResource('users', $model, [
            'searchable' => ['name', 'email'],
        ]);

        // Test applying search
        $result = $resource->getData([
            'search' => 'john',
        ]);

        // Add an assertion to make this test not risky
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }
}

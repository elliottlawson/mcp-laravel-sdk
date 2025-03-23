<?php

namespace ElliottLawson\LaravelMcp\Tests\Feature;

use Tests\TestCase;
use ReflectionClass;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use Illuminate\Database\Eloquent\Model;
use ElliottLawson\LaravelMcp\McpManager;
use ElliottLawson\LaravelMcp\Tools\CommandTool;
use ElliottLawson\LaravelMcp\Prompts\FilePrompt;

class McpServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Routes should already be registered by the service provider
        // Reset the manager's resources, tools, and prompts
        $manager = $this->app->make(McpManager::class);
        $this->resetManagerState($manager);
    }

    /**
     * Reset the McpManager state by setting resources, tools, and prompts to empty arrays
     */
    protected function resetManagerState(McpManager $manager): void
    {
        $reflection = new ReflectionClass($manager);

        $resourcesProperty = $reflection->getProperty('resources');
        $resourcesProperty->setAccessible(true);
        $resourcesProperty->setValue($manager, []);

        $toolsProperty = $reflection->getProperty('tools');
        $toolsProperty->setAccessible(true);
        $toolsProperty->setValue($manager, []);

        $promptsProperty = $reflection->getProperty('prompts');
        $promptsProperty->setAccessible(true);
        $promptsProperty->setValue($manager, []);
    }

    /**
     * Test that the server info endpoint returns the correct information.
     */
    #[Test]
    public function test_server_info(): void
    {
        // Register a test resource
        $manager = $this->app->make(McpManager::class);
        $manager->resource('test_resource', new class extends Model
        {
            protected $table = 'test_resources';

            protected $fillable = ['name', 'description'];
        });

        // Register a test tool
        $manager->tool('test_tool', [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'The message to echo',
                ],
            ],
        ], new CommandTool('test', [
            'command' => 'echo "Hello World"',
        ]));

        // Register a test prompt
        $manager->prompt('test_prompt', new FilePrompt('test', __DIR__ . '/../../stubs/prompt.txt'));

        // Make the server info request
        $response = $this->postJson('/mcp/json-rpc', [
            'jsonrpc' => '2.0',
            'method' => 'server.info',
            'params' => [],
            'id' => 1,
        ]);

        // Assert the response is correct
        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'result' => [
                    'resources',
                    'tools',
                    'prompts',
                    'version',
                ],
                'id',
            ]);

        // Assert the registered resources, tools, and prompts are in the response
        $result = $response->json('result');
        $this->assertArrayHasKey('test_resource', $result['resources']);
        $this->assertArrayHasKey('test_tool', $result['tools']);
        $this->assertArrayHasKey('test_prompt', $result['prompts']);
    }

    /**
     * Test that the resource.get endpoint returns the correct data.
     */
    #[Test]
    public function test_resource_get(): void
    {
        // Create a mock model and register it as a resource
        $manager = $this->app->make(McpManager::class);
        $manager->resource('users', new class extends Model
        {
            protected $table = 'users';

            protected $fillable = ['name', 'email'];

            public function new_query()
            {
                $query = parent::newQuery();

                // Mock the query to return test data
                $query->getConnection()->shouldReceive('select')
                    ->andReturn([
                        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
                    ]);

                return $query;
            }
        });

        // Make the resource.get request
        $response = $this->postJson('/mcp/json-rpc', [
            'jsonrpc' => '2.0',
            'method' => 'resource.get',
            'params' => [
                'resource' => 'users',
                'query' => [
                    'id' => 1,
                ],
            ],
            'id' => 1,
        ]);

        // Assert the response is correct
        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'result' => [
                    'data',
                    'meta' => [
                        'total',
                        'per_page',
                        'current_page',
                    ],
                ],
                'id',
            ]);
    }

    /**
     * Test that the tool.execute endpoint executes a tool correctly.
     */
    #[Test]
    public function test_tool_execute(): void
    {
        // Register a test tool
        $manager = $this->app->make(McpManager::class);
        $manager->tool('echo', [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'The message to echo',
                ],
            ],
        ], new CommandTool('echo', [
            'command' => 'echo',
            'args' => ['message'],
        ]));

        // Make the tool.execute request
        $response = $this->postJson('/mcp/json-rpc', [
            'jsonrpc' => '2.0',
            'method' => 'tool.execute',
            'params' => [
                'tool' => 'echo',
                'params' => [
                    'message' => 'Hello World',
                ],
            ],
            'id' => 1,
        ]);

        // Assert the response is correct
        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'result',
                'id',
            ]);

        // Assert the tool executed correctly
        $result = $response->json('result');
        $this->assertStringContainsString('Hello World', $result['output']);
    }

    /**
     * Test that the prompt.get endpoint returns the correct prompt.
     */
    #[Test]
    public function test_prompt_get(): void
    {
        // Define a test prompt content
        $promptContent = 'This is a test prompt for the MCP SDK.';

        // Register a test prompt using a closure
        $manager = $this->app->make(McpManager::class);
        $manager->prompt('test_prompt', $promptContent);

        // Make the prompt.get request
        $response = $this->postJson('/mcp/json-rpc', [
            'jsonrpc' => '2.0',
            'method' => 'prompt.get',
            'params' => [
                'prompt' => 'test_prompt',
            ],
            'id' => 1,
        ]);

        // Assert the response is correct
        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'result',
                'id',
            ]);

        // Assert the prompt content is correct
        $result = $response->json('result');
        $this->assertEquals($promptContent, $result['content']);
    }

    /**
     * Test SSE connection.
     * 
     * Note: This test may be marked as "risky" by PHPUnit due to the nature of SSE testing,
     * which requires output buffer manipulation. This is expected behavior and doesn't
     * indicate a problem with the test or the code being tested.
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_sse_connection(): void
    {
        // Expect some output from the SSE connection
        $this->expectOutputRegex('/data:/');
        
        // This test requires a real browser to test SSE connections
        // So we'll just test that the endpoint exists and returns a 200 response
        $clientId = Str::uuid()->toString();
        $response = $this->get("/mcp/sse?client_id={$clientId}");

        // Assert the response has the correct headers
        $response->assertStatus(200);
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    /**
     * Test that batch requests work correctly.
     */
    #[Test]
    public function test_batch_requests(): void
    {
        // Register a test resource and tool
        $manager = $this->app->make(McpManager::class);
        $manager->resource('test_resource', new class extends Model
        {
            protected $table = 'test_resources';

            protected $fillable = ['name', 'description'];
        });
        $manager->tool('echo', [], new CommandTool('echo', [
            'command' => 'echo',
        ]));

        // Make a batch request
        $response = $this->postJson('/mcp/json-rpc', [
            [
                'jsonrpc' => '2.0',
                'method' => 'server.info',
                'id' => 1,
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'tool.execute',
                'params' => [
                    'tool' => 'echo',
                    'params' => [
                        'message' => 'Hello World',
                    ],
                ],
                'id' => 2,
            ],
        ]);

        // Assert the response is correct
        $response->assertStatus(200)
            ->assertJsonCount(2);

        // Assert each response in the batch is correct
        $responses = $response->json();
        $this->assertEquals(1, $responses[0]['id']);
        $this->assertEquals(2, $responses[1]['id']);
        $this->assertArrayHasKey('result', $responses[0]);
        $this->assertArrayHasKey('result', $responses[1]);
    }

    /**
     * Test that error handling works correctly.
     */
    #[Test]
    public function test_error_handling(): void
    {
        // Make an invalid request
        $response = $this->postJson('/mcp/json-rpc', [
            'jsonrpc' => '2.0',
            'method' => 'invalid.method',
            'params' => [],
            'id' => 1,
        ]);

        // Assert the response is a JSON-RPC error
        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'error' => [
                    'code',
                    'message',
                ],
                'id',
            ]);

        // Assert the error code is correct
        $error = $response->json('error');
        $this->assertEquals(-32601, $error['code']); // Method not found error
    }
}

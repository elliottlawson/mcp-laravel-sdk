<?php

namespace ElliottLawson\LaravelMcp\Tests\Unit\Tools;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use ElliottLawson\LaravelMcp\Tools\HttpTool;

class HttpToolTest extends TestCase
{
    /**
     * Test the HTTP tool creation and schema.
     */
    #[Test]
    public function test_http_tool_creation(): void
    {
        // Create an HTTP tool
        $tool = new HttpTool('http-tool', [
            'timeout' => 30,
        ], [
            'description' => 'Test HTTP tool',
        ]);

        // Test basic properties
        $this->assertEquals('http-tool', $tool->getMetadata()['name']);
        $this->assertEquals('Test HTTP tool', $tool->getMetadata()['description']);

        // Test schema
        $schema = $tool->getSchema();
        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertContains('url', $schema['required']);
        $this->assertArrayHasKey('url', $schema['properties']);
        $this->assertArrayHasKey('method', $schema['properties']);
    }

    /**
     * Test executing a GET request.
     */
    #[Test]
    public function test_execute_get_request(): void
    {
        // Mock the HTTP facade
        Http::fake([
            'example.com/*' => Http::response(['data' => 'test'], 200, ['Content-Type' => 'application/json']),
        ]);

        // Create an HTTP tool
        $tool = new HttpTool('http-tool');

        // Execute a GET request
        $result = $tool->execute([
            'url' => 'https://example.com/api',
            'method' => 'GET',
            'query' => ['param' => 'value'],
        ]);

        // Test the result
        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['successful']);
        $this->assertEquals(['data' => 'test'], $result['body']);

        // Verify the request was made
        Http::assertSent(function ($request) {
            return $request->url() == 'https://example.com/api?param=value' &&
                   $request->method() == 'GET';
        });
    }

    /**
     * Test executing a POST request.
     */
    #[Test]
    public function test_execute_post_request(): void
    {
        // Mock the HTTP facade
        Http::fake([
            'example.com/*' => Http::response(['success' => true], 201, ['Content-Type' => 'application/json']),
        ]);

        // Create an HTTP tool
        $tool = new HttpTool('http-tool');

        // Execute a POST request
        $result = $tool->execute([
            'url' => 'https://example.com/api',
            'method' => 'POST',
            'data' => ['name' => 'Test', 'value' => 123],
            'headers' => ['X-API-Key' => 'test-key'],
        ]);

        // Test the result
        $this->assertEquals(201, $result['status']);
        $this->assertTrue($result['successful']);
        $this->assertEquals(['success' => true], $result['body']);

        // Verify the request was made
        Http::assertSent(function ($request) {
            return $request->url() == 'https://example.com/api' &&
                   $request->method() == 'POST' &&
                   $request->data() == ['name' => 'Test', 'value' => 123] &&
                   $request->hasHeader('X-API-Key', 'test-key');
        });
    }

    /**
     * Test handling a failed request.
     */
    #[Test]
    public function test_handle_failed_request(): void
    {
        // Mock the HTTP facade to return an error
        Http::fake([
            'example.com/*' => Http::response(['error' => 'Not found'], 404, ['Content-Type' => 'application/json']),
        ]);

        // Create an HTTP tool
        $tool = new HttpTool('http-tool');

        // Execute a request that will fail
        $result = $tool->execute([
            'url' => 'https://example.com/api/nonexistent',
            'method' => 'GET',
        ]);

        // Test the result
        $this->assertEquals(404, $result['status']);
        $this->assertFalse($result['successful']);
        $this->assertEquals(['error' => 'Not found'], $result['body']);
    }

    /**
     * Test handling an exception during the request.
     */
    #[Test]
    public function test_handle_request_exception(): void
    {
        // Mock the HTTP facade to throw an exception
        Http::fake([
            'example.com/*' => function () {
                throw new \Exception('Connection failed');
            },
        ]);

        // Create an HTTP tool
        $tool = new HttpTool('http-tool');

        // Execute a request that will throw an exception
        $result = $tool->execute([
            'url' => 'https://example.com/api',
            'method' => 'GET',
        ]);

        // Test the result
        $this->assertEquals(0, $result['status']);
        $this->assertFalse($result['successful']);
        $this->assertEquals('Connection failed', $result['error']);
    }

    /**
     * Test parameter validation.
     */
    #[Test]
    public function test_parameter_validation(): void
    {
        // Create an HTTP tool
        $tool = new HttpTool('http-tool');

        // Test with missing required parameter
        $this->expectException(\InvalidArgumentException::class);
        $tool->execute([
            'method' => 'GET',
        ]);
    }
}

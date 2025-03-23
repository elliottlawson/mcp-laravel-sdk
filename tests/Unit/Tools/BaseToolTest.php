<?php

namespace ElliottLawson\LaravelMcp\Tests\Unit\Tools;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ElliottLawson\LaravelMcp\Tools\BaseTool;

class BaseToolTest extends TestCase
{
    /**
     * Test the basic tool creation and metadata.
     */
    #[Test]
    public function test_tool_creation(): void
    {
        // Create a concrete implementation of BaseTool for testing
        $tool = new class('test-tool', ['type' => 'object'], ['description' => 'Test tool']) extends BaseTool
        {
            public function execute(array $params = []): string
            {
                return 'executed';
            }
        };

        // Test basic properties
        $this->assertEquals('test-tool', $tool->getMetadata()['name']);
        $this->assertEquals('Test tool', $tool->getMetadata()['description']);
        $this->assertEquals(['type' => 'object'], $tool->getSchema());
    }

    /**
     * Test setting and getting metadata.
     */
    #[Test]
    public function test_metadata_manipulation(): void
    {
        // Create a concrete implementation of BaseTool for testing
        $tool = new class('test-tool') extends BaseTool
        {
            public function execute(array $params = []): string
            {
                return 'executed';
            }
        };

        // Test setting metadata
        $tool->setMetadata(['version' => '1.0']);
        $this->assertEquals('1.0', $tool->getMetadata()['version']);

        // Test setting a specific metadata value
        $tool->setMetadataValue('author', 'Test Author');
        $this->assertEquals('Test Author', $tool->getMetadata()['author']);
    }

    /**
     * Test the execute method.
     */
    #[Test]
    public function test_tool_execution(): void
    {
        // Create a concrete implementation of BaseTool for testing
        $tool = new class('test-tool') extends BaseTool
        {
            public function execute(array $params = []): string
            {
                return 'executed with ' . ($params['value'] ?? 'no params');
            }
        };

        // Test execution
        $this->assertEquals('executed with test', $tool->execute(['value' => 'test']));
        $this->assertEquals('executed with no params', $tool->execute());
    }

    /**
     * Test parameter validation.
     */
    #[Test]
    public function test_parameter_validation(): void
    {
        // Create a concrete implementation of BaseTool with schema for testing
        $tool = new class('test-tool', ['type' => 'object', 'required' => ['required_param'], 'properties' => ['required_param' => ['type' => 'string'], 'number_param' => ['type' => 'number'], 'boolean_param' => ['type' => 'boolean']]]) extends BaseTool
        {
            public function execute(array $params = []): bool
            {
                return $this->validateParameters($params);
            }
        };

        // Test valid parameters
        $this->assertTrue($tool->execute([
            'required_param' => 'test',
            'number_param' => 123,
            'boolean_param' => true,
        ]));

        // Test missing required parameter
        $this->assertFalse($tool->execute([
            'number_param' => 123,
        ]));

        // Test invalid parameter type
        $this->assertFalse($tool->execute([
            'required_param' => 'test',
            'number_param' => 'not a number',
        ]));
    }
}

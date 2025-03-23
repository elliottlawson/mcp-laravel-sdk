<?php

namespace ElliottLawson\LaravelMcp\Tests\Unit\Prompts;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ElliottLawson\LaravelMcp\Prompts\BasePrompt;

class BasePromptTest extends TestCase
{
    /**
     * Test the basic prompt creation and metadata.
     */
    #[Test]
    public function test_prompt_creation(): void
    {
        // Create a concrete implementation of BasePrompt for testing
        $prompt = new class('test-prompt', 'This is a {{type}} prompt.', ['description' => 'Test prompt']) extends BasePrompt {};

        // Test basic properties
        $this->assertEquals('test-prompt', $prompt->getMetadata()['name']);
        $this->assertEquals('Test prompt', $prompt->getMetadata()['description']);
        $this->assertEquals('This is a {{type}} prompt.', $prompt->getContent());
    }

    /**
     * Test setting and getting metadata.
     */
    #[Test]
    public function test_metadata_manipulation(): void
    {
        // Create a concrete implementation of BasePrompt for testing
        $prompt = new class('test-prompt', 'Test content') extends BasePrompt {};

        // Test setting metadata
        $prompt->setMetadata(['version' => '1.0']);
        $this->assertEquals('1.0', $prompt->getMetadata()['version']);

        // Test setting a specific metadata value
        $prompt->setMetadataValue('author', 'Test Author');
        $this->assertEquals('Test Author', $prompt->getMetadata()['author']);
    }

    /**
     * Test setting content.
     */
    #[Test]
    public function test_content_manipulation(): void
    {
        // Create a concrete implementation of BasePrompt for testing
        $prompt = new class('test-prompt', 'Original content') extends BasePrompt {};

        // Test setting content
        $prompt->setContent('Updated content');
        $this->assertEquals('Updated content', $prompt->getContent());
    }

    /**
     * Test variable processing.
     */
    #[Test]
    public function test_variable_processing(): void
    {
        // Create a concrete implementation of BasePrompt for testing
        $prompt = new class('test-prompt', 'Hello, {{name}}! Welcome to {{app_name}}.') extends BasePrompt {};

        // Test variable replacement
        $processed = $prompt->process([
            'name' => 'John',
            'app_name' => 'Laravel MCP',
        ]);
        $this->assertEquals('Hello, John! Welcome to Laravel MCP.', $processed);

        // Test with missing variables
        $processed = $prompt->process([
            'name' => 'Jane',
        ]);
        $this->assertEquals('Hello, Jane! Welcome to {{app_name}}.', $processed);

        // Test with no variables
        $processed = $prompt->process([]);
        $this->assertEquals('Hello, {{name}}! Welcome to {{app_name}}.', $processed);
    }

    /**
     * Test complex variable processing.
     */
    #[Test]
    public function test_complex_variable_processing(): void
    {
        // Create a concrete implementation of BasePrompt for testing
        $prompt = new class('test-prompt', 'User: {{user.name}} ({{user.email}})\nRole: {{user.role}}') extends BasePrompt {};

        // Test with nested variable names
        $processed = $prompt->process([
            'user.name' => 'John Doe',
            'user.email' => 'john@example.com',
            'user.role' => 'Admin',
        ]);
        $this->assertEquals('User: John Doe (john@example.com)\nRole: Admin', $processed);
    }
}

<?php

namespace ElliottLawson\LaravelMcp\Tests\Unit\Prompts;

use Tests\TestCase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use ElliottLawson\LaravelMcp\Prompts\FilePrompt;

class FilePromptTest extends TestCase
{
    /**
     * Test the file prompt creation and content loading.
     */
    #[Test]
    public function test_file_prompt_creation(): void
    {
        // Mock the file system
        File::shouldReceive('exists')
            ->once()
            ->with('/path/to/prompt.txt')
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->with('/path/to/prompt.txt')
            ->andReturn('This is a test prompt with {{variable}}.');

        // Create a file prompt
        $prompt = new FilePrompt('test-prompt', '/path/to/prompt.txt', [
            'description' => 'Test file prompt',
        ]);

        // Test basic properties
        $this->assertEquals('test-prompt', $prompt->getMetadata()['name']);
        $this->assertEquals('Test file prompt', $prompt->getMetadata()['description']);
        $this->assertEquals('This is a test prompt with {{variable}}.', $prompt->getContent());
        $this->assertEquals('/path/to/prompt.txt', $prompt->getFilePath());
    }

    /**
     * Test handling non-existent files.
     */
    #[Test]
    public function test_non_existent_file(): void
    {
        // Mock the file system
        File::shouldReceive('exists')
            ->once()
            ->with('/path/to/nonexistent.txt')
            ->andReturn(false);

        // Create a file prompt with a non-existent file
        $prompt = new FilePrompt('test-prompt', '/path/to/nonexistent.txt');

        // Test that content is empty
        $this->assertEquals('', $prompt->getContent());
    }

    /**
     * Test reloading content from file.
     */
    #[Test]
    public function test_reload_content(): void
    {
        // Mock the file system for initial load
        File::shouldReceive('exists')
            ->once()
            ->with('/path/to/prompt.txt')
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->with('/path/to/prompt.txt')
            ->andReturn('Initial content.');

        // Create a file prompt
        $prompt = new FilePrompt('test-prompt', '/path/to/prompt.txt');
        $this->assertEquals('Initial content.', $prompt->getContent());

        // Mock the file system for reload
        File::shouldReceive('exists')
            ->once()
            ->with('/path/to/prompt.txt')
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->with('/path/to/prompt.txt')
            ->andReturn('Updated content.');

        // Reload the prompt
        $prompt->reload();
        $this->assertEquals('Updated content.', $prompt->getContent());
    }

    /**
     * Test variable processing in file prompts.
     */
    #[Test]
    public function test_variable_processing(): void
    {
        // Mock the file system
        File::shouldReceive('exists')
            ->once()
            ->with('/path/to/prompt.txt')
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->with('/path/to/prompt.txt')
            ->andReturn('Hello, {{name}}! Your account was created on {{date}}.');

        // Create a file prompt
        $prompt = new FilePrompt('test-prompt', '/path/to/prompt.txt');

        // Test variable replacement
        $processed = $prompt->process([
            'name' => 'John',
            'date' => '2025-03-23',
        ]);
        $this->assertEquals('Hello, John! Your account was created on 2025-03-23.', $processed);
    }

    /**
     * Test error handling during file loading.
     */
    #[Test]
    public function test_error_handling(): void
    {
        // Mock the file system to throw an exception
        File::shouldReceive('exists')
            ->once()
            ->with('/path/to/prompt.txt')
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->with('/path/to/prompt.txt')
            ->andThrow(new \Exception('File read error'));

        // Create a file prompt
        $prompt = new FilePrompt('test-prompt', '/path/to/prompt.txt');

        // Test that content is empty due to error
        $this->assertEquals('', $prompt->getContent());
    }
}

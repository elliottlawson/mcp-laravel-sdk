<?php

namespace ElliottLawson\LaravelMcp\Tests\Unit\Tools;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Process;
use ElliottLawson\LaravelMcp\Tools\CommandTool;

class CommandToolTest extends TestCase
{
    /**
     * Test the command tool creation and schema.
     */
    #[Test]
    public function test_command_tool_creation(): void
    {
        // Create a command tool
        $tool = new CommandTool('command-tool', [
            'timeout' => 60,
        ], [
            'description' => 'Test command tool',
        ]);

        // Test basic properties
        $this->assertEquals('command-tool', $tool->getMetadata()['name']);
        $this->assertEquals('Test command tool', $tool->getMetadata()['description']);

        // Test schema
        $schema = $tool->getSchema();
        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertContains('command', $schema['required']);
        $this->assertArrayHasKey('command', $schema['properties']);
        $this->assertArrayHasKey('cwd', $schema['properties']);
    }

    /**
     * Test executing a successful command.
     */
    #[Test]
    public function test_execute_successful_command(): void
    {
        // Mock the Process facade
        $processResult = $this->mock(\Illuminate\Process\ProcessResult::class);
        $processResult->shouldReceive('exitCode')->andReturn(0);
        $processResult->shouldReceive('output')->andReturn("Hello, world!\n");
        $processResult->shouldReceive('errorOutput')->andReturn('');
        $processResult->shouldReceive('successful')->andReturn(true);

        $processInstance = $this->mock(\Illuminate\Process\PendingProcess::class);
        $processInstance->shouldReceive('run')->with('echo "Hello, world!"')->andReturn($processResult);

        $processFacade = $this->mock(\Illuminate\Process\Factory::class);
        $processFacade->shouldReceive('timeout')->andReturn($processInstance);
        Process::swap($processFacade);

        // Create a command tool
        $tool = new CommandTool('command-tool');

        // Execute a command
        $result = $tool->execute([
            'command' => 'echo "Hello, world!"',
        ]);

        // Test the result
        $this->assertEquals(0, $result['exit_code']);
        $this->assertEquals("Hello, world!\n", $result['output']);
        $this->assertEquals('', $result['error_output']);
        $this->assertTrue($result['successful']);
        $this->assertEquals('echo "Hello, world!"', $result['command']);
    }

    /**
     * Test executing a command with working directory.
     */
    #[Test]
    public function test_execute_command_with_working_directory(): void
    {
        // Mock the Process facade
        $processResult = $this->mock(\Illuminate\Process\ProcessResult::class);
        $processResult->shouldReceive('exitCode')->andReturn(0);
        $processResult->shouldReceive('output')->andReturn("/custom/directory\n");
        $processResult->shouldReceive('errorOutput')->andReturn('');
        $processResult->shouldReceive('successful')->andReturn(true);

        $processInstance = $this->mock(\Illuminate\Process\PendingProcess::class);
        $processInstance->shouldReceive('path')->with('/custom/directory')->andReturnSelf();
        $processInstance->shouldReceive('run')->with('pwd')->andReturn($processResult);

        $processFacade = $this->mock(\Illuminate\Process\Factory::class);
        $processFacade->shouldReceive('timeout')->andReturn($processInstance);
        Process::swap($processFacade);

        // Create a command tool
        $tool = new CommandTool('command-tool');

        // Execute a command with a working directory
        $result = $tool->execute([
            'command' => 'pwd',
            'cwd' => '/custom/directory',
        ]);

        // Test the result
        $this->assertEquals(0, $result['exit_code']);
        $this->assertEquals("/custom/directory\n", $result['output']);
        $this->assertTrue($result['successful']);
    }

    /**
     * Test executing a command with environment variables.
     */
    #[Test]
    public function test_execute_command_with_environment_variables(): void
    {
        // Mock the Process facade
        $processResult = $this->mock(\Illuminate\Process\ProcessResult::class);
        $processResult->shouldReceive('exitCode')->andReturn(0);
        $processResult->shouldReceive('output')->andReturn("TEST_VALUE\n");
        $processResult->shouldReceive('errorOutput')->andReturn('');
        $processResult->shouldReceive('successful')->andReturn(true);

        $processInstance = $this->mock(\Illuminate\Process\PendingProcess::class);
        $processInstance->shouldReceive('env')->with(['TEST_VAR' => 'TEST_VALUE'])->andReturnSelf();
        $processInstance->shouldReceive('run')->with('echo $TEST_VAR')->andReturn($processResult);

        $processFacade = $this->mock(\Illuminate\Process\Factory::class);
        $processFacade->shouldReceive('timeout')->andReturn($processInstance);
        Process::swap($processFacade);

        // Create a command tool
        $tool = new CommandTool('command-tool');

        // Execute a command with environment variables
        $result = $tool->execute([
            'command' => 'echo $TEST_VAR',
            'env' => ['TEST_VAR' => 'TEST_VALUE'],
        ]);

        // Test the result
        $this->assertEquals(0, $result['exit_code']);
        $this->assertEquals("TEST_VALUE\n", $result['output']);
        $this->assertTrue($result['successful']);
    }

    /**
     * Test handling a failed command.
     */
    #[Test]
    public function test_handle_failed_command(): void
    {
        // Mock the Process facade
        $processResult = $this->mock(\Illuminate\Process\ProcessResult::class);
        $processResult->shouldReceive('exitCode')->andReturn(1);
        $processResult->shouldReceive('output')->andReturn('');
        $processResult->shouldReceive('errorOutput')->andReturn("Command not found\n");
        $processResult->shouldReceive('successful')->andReturn(false);

        $processInstance = $this->mock(\Illuminate\Process\PendingProcess::class);
        $processInstance->shouldReceive('run')->with('nonexistent-command')->andReturn($processResult);

        $processFacade = $this->mock(\Illuminate\Process\Factory::class);
        $processFacade->shouldReceive('timeout')->andReturn($processInstance);
        Process::swap($processFacade);

        // Create a command tool
        $tool = new CommandTool('command-tool');

        // Execute a command that will fail
        $result = $tool->execute([
            'command' => 'nonexistent-command',
        ]);

        // Test the result
        $this->assertEquals(1, $result['exit_code']);
        $this->assertEquals("Command not found\n", $result['error_output']);
        $this->assertFalse($result['successful']);
    }

    /**
     * Test handling an exception during command execution.
     */
    #[Test]
    public function test_handle_command_exception(): void
    {
        // Mock the Process facade to throw an exception
        $processInstance = $this->mock(\Illuminate\Process\PendingProcess::class);
        $processInstance->shouldReceive('run')->andThrow(new \Exception('Process failed to start'));

        $processFacade = $this->mock(\Illuminate\Process\Factory::class);
        $processFacade->shouldReceive('timeout')->andReturn($processInstance);
        Process::swap($processFacade);

        // Create a command tool
        $tool = new CommandTool('command-tool');

        // Execute a command that will throw an exception
        $result = $tool->execute([
            'command' => 'some-command',
        ]);

        // Test the result
        $this->assertEquals(-1, $result['exit_code']);
        $this->assertEquals('Process failed to start', $result['error']);
        $this->assertFalse($result['successful']);
    }

    /**
     * Test parameter validation.
     */
    #[Test]
    public function test_parameter_validation(): void
    {
        // Create a command tool
        $tool = new CommandTool('command-tool');

        // Test with missing required parameter
        $this->expectException(\InvalidArgumentException::class);
        $tool->execute([
            'cwd' => '/tmp',
        ]);
    }
}

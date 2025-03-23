<?php

namespace ElliottLawson\LaravelMcp\Tools;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

/**
 * Tool implementation for executing shell commands.
 */
class CommandTool extends BaseTool
{
    /**
     * The default command options.
     */
    protected array $options = [];

    /**
     * Create a new command tool instance.
     *
     * @param string $name The tool name
     * @param array $options Default command options
     * @param array $metadata Additional metadata for the tool
     */
    public function __construct(string $name, array $options = [], array $metadata = [])
    {
        // Define the JSON schema for the tool parameters
        $schema = [
            'type' => 'object',
            'required' => ['command'],
            'properties' => [
                'command' => [
                    'type' => 'string',
                    'description' => 'The command to execute',
                ],
                'cwd' => [
                    'type' => 'string',
                    'description' => 'The working directory for the command',
                ],
                'env' => [
                    'type' => 'object',
                    'description' => 'Environment variables for the command',
                ],
                'timeout' => [
                    'type' => 'integer',
                    'description' => 'The command timeout in seconds',
                ],
            ],
        ];

        parent::__construct($name, $schema, array_merge([
            'description' => 'Executes shell commands',
        ], $metadata));

        $this->options = $options;
    }

    /**
     * Execute the tool with the given parameters.
     *
     * @param array $params The parameters for the tool execution
     * @return mixed The result of the tool execution
     */
    public function execute(array $params = [])
    {
        // Validate parameters
        if (!$this->validateParameters($params)) {
            throw new \InvalidArgumentException('Invalid parameters for command tool');
        }

        // Get the command
        $command = $params['command'];

        // Merge default options with provided parameters
        $options = array_merge($this->options, $params);

        // Prepare the process
        $process = Process::timeout($options['timeout'] ?? 60);

        // Set working directory if provided
        if (isset($options['cwd'])) {
            $process = $process->path($options['cwd']);
        }

        // Set environment variables if provided
        if (isset($options['env']) && is_array($options['env'])) {
            $process = $process->env($options['env']);
        }

        try {
            // Execute the command
            $result = $process->run($command);

            // Format the response
            return [
                'exit_code' => $result->exitCode(),
                'output' => $result->output(),
                'error_output' => $result->errorOutput(),
                'successful' => $result->successful(),
                'command' => $command,
            ];
        } catch (\Exception $e) {
            Log::error("Command tool error: {$e->getMessage()}", [
                'exception' => $e,
                'command' => $command,
            ]);

            return [
                'exit_code' => -1,
                'error' => $e->getMessage(),
                'successful' => false,
                'command' => $command,
            ];
        }
    }
}

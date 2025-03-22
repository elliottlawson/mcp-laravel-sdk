<?php

namespace ElliottLawson\LaravelMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Process\Process;
use ElliottLawson\LaravelMcp\McpManager;

class McpServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mcp:serve 
                           {--host=localhost : The host to serve the application on}
                           {--port=8000 : The port to serve the application on}
                           {--debug : Enable debug mode for more verbose output}
                           {--no-ansi : Disable ANSI output}';

    /**
     * The console command description.
     */
    protected $description = 'Start the MCP server on a local development server';

    /**
     * Execute the console command.
     */
    public function handle(McpManager $mcpManager): int
    {
        $host = $this->option('host');
        $port = $this->option('port');
        $debug = $this->option('debug');

        $this->info('Starting MCP server on ' . $host . ':' . $port);

        if ($debug) {
            $this->comment('Debug mode enabled. Verbose output will be shown.');
        }

        $this->info('Press Ctrl+C to stop the server');

        // Create and set up the server
        $server = $mcpManager->server();

        // Register with event bus
        Event::dispatch('mcp.server.started', [$server]);

        // Log the server start
        Log::info('MCP Server started on ' . $host . ':' . $port);

        // We need to manually create a Laravel web server which will handle the MCP requests
        $command = [
            PHP_BINARY,
            '-S',
            $host . ':' . $port,
            '-t',
            public_path(),
        ];

        $this->info('Running command: ' . implode(' ', $command));

        // Create and configure the process
        $process = new Process($command);
        $process->setTty(true);
        $process->setTimeout(null);

        try {
            // Start the server and handle the output in real-time
            $process->run(function ($type, $buffer) use ($debug) {
                if ($debug || $type === Process::ERR) {
                    $this->output->write($buffer);
                }
            });

            // Handle server shutdown
            Event::dispatch('mcp.server.stopped', [$server]);
            Log::info('MCP Server stopped');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to start MCP server: ' . $e->getMessage());
            Log::error('MCP Server failed to start: ' . $e->getMessage());

            return 1;
        }
    }
}

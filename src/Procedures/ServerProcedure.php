<?php

namespace ElliottLawson\LaravelMcp\Procedures;

use Illuminate\Support\Facades\Log;

/**
 * Procedure for handling server-related methods.
 */
class ServerProcedure extends BaseProcedure
{
    /**
     * The name of the procedure that will be used for RPC.
     */
    public static string $name = 'server';

    /**
     * Get server information.
     */
    public function info(): array
    {
        return $this->manager->getServerInfo();
    }

    /**
     * Get server capabilities.
     */
    public function capabilities(): array
    {
        return $this->manager->getCapabilities();
    }

    /**
     * Log a message to the server.
     *
     * @param  string  $level  The log level
     * @param  string  $message  The log message
     * @param  array  $context  The log context
     */
    public function log(string $level, string $message, array $context = []): bool
    {
        // Validate log level
        $validLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        if (!in_array($level, $validLevels)) {
            $level = 'info';
        }

        // Log the message
        Log::$level("[MCP] {$message}", $context);

        return true;
    }

    /**
     * Ping the server to check if it's alive.
     */
    public function ping(): array
    {
        return [
            'timestamp' => now()->timestamp,
            'status' => 'ok',
        ];
    }
}

<?php

namespace ElliottLawson\LaravelMcp\Support;

/**
 * Laravel implementation of Server-Sent Events transport.
 */
class LaravelSseTransport
{
    /**
     * Create a new SSE transport instance.
     *
     * @param  int  $heartbeatInterval  The heartbeat interval in seconds
     * @param  int  $maxExecutionTime  The maximum execution time in seconds
     */
    public function __construct(
        protected int $heartbeatInterval = 30,
        protected int $maxExecutionTime = 0,
        protected string $connectionId = ''
    ) {
        if (empty($this->connectionId)) {
            $this->connectionId = uniqid('mcp_', true);
        }
    }

    /**
     * Start the SSE connection.
     */
    public function start(): void
    {
        // Set appropriate headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable buffering for Nginx

        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set time limit if specified
        if ($this->maxExecutionTime > 0) {
            set_time_limit($this->maxExecutionTime);
        }

        // Flush headers
        flush();

        // Send connection ID event
        $this->send([
            'type' => 'connection',
            'id' => $this->connectionId,
            'timestamp' => time(),
        ]);

        // Register shutdown function to send close event
        register_shutdown_function(function () {
            $this->send([
                'type' => 'close',
                'id' => $this->connectionId,
                'timestamp' => time(),
            ]);
        });
    }

    /**
     * Send data as an SSE event.
     *
     * @param  mixed  $data  The data to send
     * @param  string|null  $event  The event name
     * @param  string|null  $id  The event ID
     */
    public function send($data, ?string $event = null, ?string $id = null): void
    {
        // Convert data to JSON if it's an array or object
        $data = is_string($data) ? $data : json_encode($data);

        // Format the event
        $output = '';

        if ($id) {
            $output .= "id: {$id}\n";
        }

        if ($event) {
            $output .= "event: {$event}\n";
        }

        // Split data by newlines to ensure proper formatting
        foreach (explode("\n", $data) as $line) {
            $output .= "data: {$line}\n";
        }

        $output .= "\n";

        // Send the event
        echo $output;

        // Flush output
        flush();
    }

    /**
     * Start the heartbeat loop.
     */
    public function startHeartbeat(): void
    {
        $lastHeartbeat = time();

        while (true) {
            // Check if connection is still alive
            if (connection_aborted()) {
                break;
            }

            // Send heartbeat if interval has passed
            $now = time();
            if ($now - $lastHeartbeat >= $this->heartbeatInterval) {
                $this->send([
                    'type' => 'heartbeat',
                    'timestamp' => $now,
                ], 'heartbeat');
                $lastHeartbeat = $now;
            }

            // Sleep for a short time to avoid CPU hogging
            usleep(100000); // 100ms
        }
    }

    /**
     * Listen for events and send them to the client.
     *
     * @param  callable  $callback  Optional callback to run before starting the loop
     */
    public function listen(?callable $callback = null): void
    {
        // Start the SSE connection
        $this->start();

        // Run the callback if provided
        if ($callback) {
            $callback($this);
        }

        // Start the heartbeat loop
        $this->startHeartbeat();
    }

    /**
     * Get the connection ID.
     */
    public function getConnectionId(): string
    {
        return $this->connectionId;
    }
}

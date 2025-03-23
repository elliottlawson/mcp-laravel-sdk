<?php

namespace ElliottLawson\LaravelMcp\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laravel-native implementation of Server-Sent Events transport.
 * 
 * This implementation properly integrates with Laravel's request lifecycle
 * by using StreamedResponse and Laravel's event system.
 */
class LaravelNativeSseTransport
{
    /**
     * Create a new SSE transport instance.
     */
    public function __construct(
        protected int $heartbeatInterval = 30,
        protected int $maxExecutionTime = 0,
        protected string $connectionId = '',
        protected bool $isActive = false,
        protected array $messageHandlers = []
    ) {
        if (empty($this->connectionId)) {
            $this->connectionId = Str::uuid()->toString();
        }
    }

    /**
     * Create a properly configured StreamedResponse for SSE.
     *
     * @param  callable|null  $callback  Optional callback to run before starting the stream
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function createResponse(?callable $callback = null): StreamedResponse
    {
        return new StreamedResponse(function () use ($callback) {
            $this->startStream($callback);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable buffering for Nginx
        ]);
    }

    /**
     * Start the SSE stream.
     *
     * @param  callable|null  $callback  Optional callback to run before starting the stream
     */
    protected function startStream(?callable $callback = null): void
    {
        // Set time limit if specified
        if ($this->maxExecutionTime > 0) {
            set_time_limit($this->maxExecutionTime);
        }

        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Mark as active
        $this->isActive = true;

        // Dispatch connection event
        Event::dispatch('mcp.sse.started', [
            'connection_id' => $this->connectionId,
            'transport' => $this,
        ]);

        // Send connection ID event
        $this->send([
            'type' => 'connection',
            'id' => $this->connectionId,
            'timestamp' => now()->timestamp,
        ]);

        // Run the callback if provided
        if ($callback) {
            $callback($this);
        }

        // Start the heartbeat loop
        $this->runEventLoop();

        // Mark as inactive when done
        $this->isActive = false;

        // Dispatch disconnection event
        Event::dispatch('mcp.sse.ended', [
            'connection_id' => $this->connectionId,
            'transport' => $this,
        ]);
    }

    /**
     * Register a message handler.
     *
     * @param  string  $event  The event name to listen for
     * @param  callable  $handler  The handler function
     * @return $this
     */
    public function on(string $event, callable $handler): self
    {
        $this->messageHandlers[$event] = $handler;
        return $this;
    }

    /**
     * Send data as an SSE event.
     *
     * @param  mixed  $data  The data to send
     * @param  string|null  $event  The event name
     * @param  string|null  $id  The event ID
     * @return $this
     */
    public function send($data, ?string $event = null, ?string $id = null): self
    {
        if (!$this->isActive) {
            Log::warning('Attempted to send SSE event on inactive transport', [
                'connection_id' => $this->connectionId,
                'event' => $event,
            ]);
            return $this;
        }

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

        // Dispatch message sent event
        Event::dispatch('mcp.sse.message.sent', [
            'connection_id' => $this->connectionId,
            'event' => $event,
            'data' => $data,
            'id' => $id,
        ]);

        return $this;
    }

    /**
     * Run the event loop to handle heartbeats and process messages.
     */
    protected function runEventLoop(): void
    {
        $lastHeartbeat = now()->timestamp;

        while ($this->isActive) {
            // Check if connection is still alive
            if (connection_aborted()) {
                $this->isActive = false;
                break;
            }

            // Send heartbeat if interval has passed
            $now = now()->timestamp;
            if ($now - $lastHeartbeat >= $this->heartbeatInterval) {
                $this->sendHeartbeat();
                $lastHeartbeat = $now;
            }

            // Sleep for a short time to avoid CPU hogging
            usleep(100000); // 100ms
        }
    }

    /**
     * Send a heartbeat message.
     */
    protected function sendHeartbeat(): void
    {
        $this->send([
            'type' => 'heartbeat',
            'timestamp' => now()->timestamp,
        ], 'heartbeat');

        // Dispatch heartbeat event
        Event::dispatch('mcp.sse.heartbeat', [
            'connection_id' => $this->connectionId,
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Stop the SSE stream.
     */
    public function stop(): void
    {
        $this->isActive = false;
    }

    /**
     * Get the connection ID.
     */
    public function getConnectionId(): string
    {
        return $this->connectionId;
    }

    /**
     * Check if the transport is active.
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }
}

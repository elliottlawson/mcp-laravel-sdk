<?php

namespace ElliottLawson\LaravelMcp\Transport;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;

/**
 * Server-Sent Events (SSE) transport implementation optimized for Laravel.
 *
 * This class handles SSE connections for the MCP server within a Laravel context,
 * providing proper integration with Laravel's event system and response handling.
 */
class LaravelSseTransport implements TransportInterface
{
    /**
     * @var callable|null The message handler
     */
    protected $messageHandler = null;

    /**
     * @var bool Whether the transport is running
     */
    protected bool $running = false;

    /**
     * @var StreamedResponse|null The current response
     */
    protected ?StreamedResponse $response = null;

    /**
     * @var callable|null The callback for the response
     */
    protected $responseCallback = null;

    /**
     * @var int Seconds between heartbeat messages
     */
    protected int $heartbeatInterval;

    /**
     * Create a new SSE transport.
     *
     * @param  int  $heartbeatInterval  Seconds between heartbeat messages
     */
    public function __construct(int $heartbeatInterval = 30)
    {
        $this->heartbeatInterval = $heartbeatInterval;
    }

    /**
     * {@inheritdoc}
     */
    public function send(string $message): void
    {
        if (!$this->running) {
            Log::warning('Attempting to send message on inactive SSE transport');

            return;
        }

        // Format the message for SSE (each line needs to be prefixed with "data: ")
        $formatted = 'data: ' . str_replace("\n", "\ndata: ", $message) . "\n\n";

        // Send the message
        echo $formatted;
        ob_flush();
        flush();

        // Log and dispatch event
        Event::dispatch('mcp.transport.message.sent', [$message]);
    }

    /**
     * {@inheritdoc}
     */
    public function setMessageHandler(callable $handler): TransportInterface
    {
        $this->messageHandler = $handler;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        $this->running = true;

        // Initialize if we don't already have a response
        if ($this->response === null) {
            $this->initializeResponse();
        }

        // Dispatch event
        Event::dispatch('mcp.transport.started', [$this]);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->running = false;

        // Dispatch event
        Event::dispatch('mcp.transport.stopped', [$this]);
    }

    /**
     * Check if the transport is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Get the SSE response.
     */
    public function getResponse(): StreamedResponse
    {
        if ($this->response === null) {
            $this->initializeResponse();
        }

        return $this->response;
    }

    /**
     * Initialize the SSE response.
     */
    protected function initializeResponse(): void
    {
        $self = $this;

        $this->responseCallback = function () use ($self) {
            // Send SSE headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // For Nginx

            // Mark the transport as running
            $self->running = true;

            // Send initial connection message
            echo "event: connected\n";
            echo "data: {\"status\":\"connected\"}\n\n";
            ob_flush();
            flush();

            // Event loop
            $lastHeartbeat = time();
            while ($self->running) {
                // Send heartbeat if needed
                $now = time();
                if ($now - $lastHeartbeat >= $self->heartbeatInterval) {
                    echo ": heartbeat\n\n"; // Comment line for heartbeat
                    ob_flush();
                    flush();
                    $lastHeartbeat = $now;
                }

                // Small sleep to prevent CPU hogging
                usleep(100000); // 100ms

                // Check if client disconnected
                if (connection_aborted()) {
                    $self->stop();
                    break;
                }
            }
        };

        $this->response = new StreamedResponse($this->responseCallback);
        $this->response->headers->set('Content-Type', 'text/event-stream');
        $this->response->headers->set('Cache-Control', 'no-cache');
        $this->response->headers->set('Connection', 'keep-alive');
        $this->response->headers->set('X-Accel-Buffering', 'no');
    }

    /**
     * Process an incoming message.
     */
    public function processMessage(string $message): void
    {
        if ($this->messageHandler !== null) {
            ($this->messageHandler)($message);
        }
    }
}

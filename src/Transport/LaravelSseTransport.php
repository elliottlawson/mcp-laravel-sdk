<?php

namespace ElliottLawson\LaravelMcp\Transport;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ElliottLawson\McpPhpSdk\Transport\SseTransport;
use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;
use ReflectionProperty;

/**
 * Server-Sent Events (SSE) transport implementation optimized for Laravel.
 *
 * This class extends the PHP SDK's SseTransport to provide proper integration
 * with Laravel's event system and response handling.
 */
class LaravelSseTransport extends SseTransport
{
    /**
     * @var StreamedResponse|null The current response
     */
    protected ?StreamedResponse $response = null;
    
    /**
     * @var int Seconds between heartbeat messages
     */
    private int $heartbeatIntervalSeconds;
    
    /**
     * @var callable|null The function to handle incoming messages
     */
    private $messageHandlerFunction = null;
    
    /**
     * Create a new Laravel-optimized SSE transport.
     *
     * @param  int  $heartbeatInterval  Seconds between heartbeat messages
     * @param  string  $messagePath  The endpoint path to receive messages from
     */
    public function __construct(int $heartbeatInterval = 30, string $messagePath = '/mcp/message')
    {
        // Store heartbeat interval locally
        $this->heartbeatIntervalSeconds = $heartbeatInterval;
        
        // Call parent constructor
        parent::__construct($messagePath, $heartbeatInterval);
        
        // Set up Laravel-specific callbacks
        $this->configureLaravelCallbacks();
    }
    
    /**
     * Configure callbacks for Laravel environment.
     * 
     * @return void
     */
    protected function configureLaravelCallbacks(): void
    {
        // Set callbacks on the parent class
        $this->setProtectedProperty('outputCallback', function (string $data) {
            echo $data;
            Event::dispatch('mcp.transport.output', [$data]);
        });
        
        $this->setProtectedProperty('headerCallback', function (string $name, string $value) {
            // Handle header setting in the callback
            if ($this->response) {
                $this->response->headers->set($name, $value, true);
            }
        });
        
        $this->setProtectedProperty('flushCallback', function () {
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        });
    }
    
    /**
     * Helper method to set protected properties in the parent class.
     *
     * @param string $propertyName
     * @param mixed $value
     * @return void
     */
    private function setProtectedProperty(string $propertyName, $value): void
    {
        $reflection = new ReflectionProperty(SseTransport::class, $propertyName);
        $reflection->setAccessible(true);
        $reflection->setValue($this, $value);
    }

    /**
     * Set the message store ID.
     *
     * @param string $id
     * @return self
     */
    public function setMessageStoreId(string $id): self
    {
        $this->setProtectedProperty('messageStoreId', $id);
        return $this;
    }
    
    /**
     * Get the message store ID.
     *
     * @return string|null
     */
    public function getMessageStoreId(): ?string
    {
        $reflection = new ReflectionProperty(SseTransport::class, 'messageStoreId');
        $reflection->setAccessible(true);
        return $reflection->getValue($this);
    }
    
    /**
     * Send a message through SSE.
     *
     * @param  string  $message  The message to send
     * @return void
     */
    public function send(string $message): void
    {
        // If handler exists, pass message to it first
        if ($this->messageHandlerFunction) {
            $result = call_user_func($this->messageHandlerFunction, $message);
            
            // If handler returned a string, use that as the message
            if (is_string($result)) {
                $message = $result;
            }
        }
        
        // Log the message
        Log::debug('Sending SSE message: ' . $message);
        
        // Dispatch event before sending
        Event::dispatch('mcp.transport.message.sent', [$message]);
        
        // Call parent implementation
        parent::send($message);
    }
    
    /**
     * Set the handler for incoming messages.
     *
     * @param callable $handler The message handler function
     * @return TransportInterface
     */
    public function setMessageHandler(callable $handler): TransportInterface
    {
        // Store locally for our own use
        $this->messageHandlerFunction = $handler;
        
        // Also set in the parent using our reflection helper
        $this->setProtectedProperty('messageHandler', $handler);
        
        return $this;
    }
    
    /**
     * Register a handler for incoming messages (alias for setMessageHandler).
     *
     * @param callable $handler Function that will process incoming messages
     * @return $this
     */
    public function onMessage(callable $handler): TransportInterface
    {
        return $this->setMessageHandler($handler);
    }
    
    /**
     * Send a heartbeat message.
     *
     * @return void
     */
    public function sendHeartbeat(): void
    {
        // Dispatch event before sending heartbeat
        Event::dispatch('mcp.transport.heartbeat');
        
        // Send a comment as heartbeat
        parent::send(': heartbeat');
    }
    
    /**
     * Get the SSE response.
     *
     * @return StreamedResponse
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
     *
     * @return void
     */
    protected function initializeResponse(): void
    {
        $transport = $this;
        
        $this->response = new StreamedResponse(function () use ($transport) {
            // Start the transport
            $transport->start();
            
            // Send initial comment to establish the connection
            echo ": SSE connection established\n\n";
            flush();
            
            // Keep connection alive with heartbeats
            $lastHeartbeat = time();
            
            // Keep-alive loop
            while ($transport->isRunning()) {
                // Send heartbeat if interval has passed
                if (time() - $lastHeartbeat >= $this->heartbeatIntervalSeconds) {
                    $transport->sendHeartbeat();
                    $lastHeartbeat = time();
                }
                
                // Sleep briefly to prevent CPU hogging
                usleep(200000); // 0.2 seconds
            }
        });
        
        // Set proper headers
        $this->response->headers->set('Content-Type', 'text/event-stream');
        $this->response->headers->set('Cache-Control', 'no-cache');
        $this->response->headers->set('Connection', 'keep-alive');
        $this->response->headers->set('X-Accel-Buffering', 'no');
    }
    
    /**
     * Process an incoming message from a separate HTTP endpoint.
     *
     * @param  string  $message  The message to process
     * @return void
     */
    public function processMessage(string $message): void
    {
        // Log for debugging
        try {
            Log::debug('Processing message: ' . $message);
        } catch (\Exception $e) {
            // Silently continue if Log facade isn't available (e.g. in some test environments)
        }
        
        // Dispatch event - use both the Laravel-specific event name and the parent class event name
        try {
            // This is what the controller expects in Laravel
            Event::dispatch('mcp.message.received', [
                ['connection_id' => $this->getMessageStoreId(), 'message' => $message]
            ]);
            
            // This is what our implementation uses internally
            Event::dispatch('mcp.transport.message.received', [$message]);
        } catch (\Exception $e) {
            // Silently continue if Event facade isn't available (e.g. in some test environments)
        }
        
        // Process with handler function - make sure we actually call the handler
        if ($this->messageHandlerFunction) {
            // Call the local handler
            call_user_func($this->messageHandlerFunction, $message);
        }
        
        // Handle parent class message handler explicitly to ensure compatibility
        $messageHandler = $this->getParentMessageHandler();
        if ($messageHandler !== null && $messageHandler !== $this->messageHandlerFunction) {
            call_user_func($messageHandler, $message);
        }
    }
    
    /**
     * Get the parent class message handler.
     *
     * @return callable|null
     */
    private function getParentMessageHandler(): ?callable
    {
        $reflection = new ReflectionProperty(SseTransport::class, 'messageHandler');
        $reflection->setAccessible(true);
        return $reflection->getValue($this);
    }
}

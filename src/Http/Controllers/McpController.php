<?php

namespace ElliottLawson\LaravelMcp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ElliottLawson\LaravelMcp\McpManager;
use ElliottLawson\McpPhpSdk\Server\McpServer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;
use Illuminate\Support\Facades\Response as ResponseFacade;
use ElliottLawson\LaravelMcp\Transport\LaravelSseTransport;

/**
 * Controller for handling MCP requests and SSE connections.
 */
class McpController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected McpServer $server) {}

    /**
     * Handle MCP requests.
     */
    public function handle(Request $request): Response
    {
        // Get request content
        $content = $request->getContent();

        // Trigger event before processing
        Event::dispatch('mcp.request.received', [$request, $content]);

        // Prepare for capturing the response
        $responseData = null;

        // Create a custom transport that will capture the response
        $transport = new class($responseData) implements TransportInterface
        {
            private $messageHandler;

            private $responseData;

            private $responseRef;

            public function __construct(&$responseRef)
            {
                $this->responseRef = &$responseRef;
            }

            public function send(string $message): void
            {
                $this->responseData = $message;
                $this->responseRef = $message;
            }

            public function setMessageHandler(callable $handler): TransportInterface
            {
                $this->messageHandler = $handler;

                return $this;
            }

            public function start(): void
            {
                // Not needed for HTTP
            }

            public function stop(): void
            {
                // Not needed for HTTP
            }

            public function isRunning(): bool
            {
                return true;
            }

            public function handleMessage(string $message): void
            {
                if ($this->messageHandler) {
                    ($this->messageHandler)($message);
                }
            }
        };

        // Connect the server to our transport
        $this->server->connect($transport);

        try {
            // Process the message through the transport's message handler
            $transport->handleMessage($content);

            // If we have a response
            if ($responseData) {
                // Trigger event after processing
                Event::dispatch('mcp.response.sent', [$responseData]);

                // The response is JSON, so we'll return it as JSON
                return ResponseFacade::make($responseData)
                    ->header('Content-Type', 'application/json')
                    ->setStatusCode(200);
            }

            // If no response was captured, return a generic error
            return ResponseFacade::make(json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                ],
                'id' => null,
            ]))->header('Content-Type', 'application/json')->setStatusCode(500);
        } catch (\Exception $e) {
            // Log the error
            Log::error('MCP Error: ' . $e->getMessage(), ['exception' => $e]);

            // Return a JSON-RPC 2.0 error response
            return ResponseFacade::make(json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32000,
                    'message' => 'Server error: ' . $e->getMessage(),
                ],
                'id' => null,
            ]))->header('Content-Type', 'application/json')->setStatusCode(500);
        }
    }

    /**
     * Handle SSE connections.
     * 
     * This implements the server-to-client streaming part of the MCP transport.
     */
    public function sse(Request $request, McpManager $mcpManager): StreamedResponse
    {
        // Generate a unique connection ID for this SSE connection
        $connectionId = Str::uuid()->toString();
        
        // Create the appropriate cache key for this connection
        $cacheKey = "mcp.sse.connection.{$connectionId}";
        
        // Configure heartbeat interval from config
        $heartbeatInterval = config('mcp.sse.heartbeat_interval', 30);
        
        // Create and configure the transport
        $transport = new LaravelSseTransport($heartbeatInterval);
        
        // Set the message store ID (connection ID) to enable message routing
        $transport->setMessageStoreId($connectionId);
        
        // Set up message handler to process incoming messages
        $transport->setMessageHandler(function (string $message) use ($connectionId) {
            // Process incoming message
            Log::debug("Processing SSE message for connection {$connectionId}", [
                'message_length' => strlen($message)
            ]);
            
            // Dispatch event for incoming message
            Event::dispatch('mcp.message.processed', [
                'connection_id' => $connectionId,
                'message' => $message,
            ]);
        });
        
        // Connect the server to our transport
        $mcpManager->server()->connect($transport);
        
        // Store the connection ID in the session for future message endpoints
        $request->session()->put('mcp_connection_id', $connectionId);
        
        // Store the transport instance in cache for message processing
        Cache::put("mcp.sse.transport.{$connectionId}", $transport, now()->addHours(1));
        
        // Dispatch event for new SSE connection
        Event::dispatch('mcp.sse.connected', [
            'connection_id' => $connectionId,
            'request' => $request,
            'transport' => $transport
        ]);
        
        // Store connection details in cache (needed for message endpoint)
        Cache::put($cacheKey, [
            'created_at' => now(),
            'last_active' => now(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ], now()->addHours(1));

        // Get the SSE response
        $response = $transport->getResponse();
        
        // Explicitly ensure Content-Type is set properly
        $response->headers->set('Content-Type', 'text/event-stream', true);
        $response->headers->set('Cache-Control', 'no-cache', true);
        $response->headers->set('Connection', 'keep-alive', true);
        $response->headers->set('X-Accel-Buffering', 'no', true);
        
        // Return the SSE response
        return $response;
    }
    
    /**
     * Handle client-to-server messages for an established SSE connection.
     * 
     * This endpoint receives messages from the client and routes them to the 
     * appropriate SSE connection via the cache.
     */
    public function message(Request $request): Response
    {
        // Get the connection ID either from the request or session
        $connectionId = $request->input('connection_id', $request->session()->get('mcp_connection_id'));
        
        if (!$connectionId) {
            return ResponseFacade::make(json_encode([
                'error' => 'No active connection found',
            ]))->header('Content-Type', 'application/json')->setStatusCode(400);
        }
        
        // Get connection details from cache
        $cacheKey = "mcp.sse.connection.{$connectionId}";
        if (!Cache::has($cacheKey)) {
            return ResponseFacade::make(json_encode([
                'error' => 'Connection expired or not found',
            ]))->header('Content-Type', 'application/json')->setStatusCode(404);
        }
        
        // Update last_active timestamp
        $connectionData = Cache::get($cacheKey);
        $connectionData['last_active'] = now();
        Cache::put($cacheKey, $connectionData, now()->addHours(1));
        
        // Get the message content
        $message = $request->getContent();
        
        // Try to get transport from cache to process the message directly
        $transport = Cache::get("mcp.sse.transport.{$connectionId}");
        if ($transport instanceof LaravelSseTransport) {
            // Process message using transport's processMessage method
            $transport->processMessage($message);
        } else {
            // Fall back to the cache-based approach if transport is not available
            $messageKey = "mcp.sse.message.{$connectionId}." . Str::uuid()->toString();
            Cache::put($messageKey, $message, now()->addMinutes(10));
        }
        
        // Dispatch event
        Event::dispatch('mcp.message.received', [
            'connection_id' => $connectionId,
            'message' => $message,
        ]);
        
        // Return success response
        return ResponseFacade::make(json_encode([
            'success' => true,
            'connection_id' => $connectionId,
        ]))->header('Content-Type', 'application/json')->setStatusCode(200);
    }
}

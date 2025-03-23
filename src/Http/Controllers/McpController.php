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
use Sajya\Server\Http\Request as SajyaRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Response as ResponseFacade;
use ElliottLawson\LaravelMcp\Support\LaravelSseTransport;

/**
 * Controller for handling MCP requests and SSE connections.
 */
class McpController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected McpManager $manager) {}

    /**
     * Handle MCP requests.
     */
    public function handle(Request $request): Response
    {
        // Get request content
        $content = $request->getContent();

        // Trigger event before processing
        Event::dispatch('mcp.request.received', [$request, $content]);

        try {
            // Use Sajya to handle the JSON-RPC request
            $sajyaRequest = SajyaRequest::fromRequest($request);
            
            // Get the registered procedures from the manager
            $procedures = $this->manager->getProcedures();
            
            // Process the request
            $response = $sajyaRequest->handle($procedures);
            
            // Trigger event after processing
            Event::dispatch('mcp.response.sent', [$response]);

            // Return the JSON-RPC response
            return ResponseFacade::make($response)
                ->header('Content-Type', 'application/json')
                ->setStatusCode(200);
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
    public function sse(Request $request): StreamedResponse
    {
        // Generate a unique connection ID for this SSE connection
        $connectionId = Str::uuid()->toString();
        
        // Create the appropriate cache key for this connection
        $cacheKey = "mcp.sse.connection.{$connectionId}";
        
        // Configure heartbeat interval from config
        $heartbeatInterval = config('mcp.sse.heartbeat_interval', 30);
        $maxExecutionTime = config('mcp.sse.max_execution_time', 0);
        
        // Store connection details in cache
        Cache::put($cacheKey, [
            'created_at' => now(),
            'last_active' => now(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ], now()->addHours(1));
        
        // Dispatch event for new SSE connection
        Event::dispatch('mcp.sse.connected', [
            'connection_id' => $connectionId,
            'request' => $request,
        ]);
        
        // Create a streamed response
        return new StreamedResponse(function () use ($connectionId, $heartbeatInterval, $maxExecutionTime) {
            // Create and configure the transport
            $transport = new LaravelSseTransport($heartbeatInterval, $maxExecutionTime);
            
            // Listen for events and send them to the client
            $transport->listen(function ($transport) use ($connectionId) {
                // Dispatch connection ready event
                Event::dispatch('mcp.sse.ready', [
                    'connection_id' => $connectionId,
                    'transport' => $transport,
                ]);
                
                // Set up event listener for sending messages through this transport
                Event::listen('mcp.sse.send', function ($event) use ($transport, $connectionId) {
                    // Only process events for this connection
                    if (isset($event['connection_id']) && $event['connection_id'] === $connectionId) {
                        $data = $event['data'] ?? null;
                        $eventName = $event['event'] ?? null;
                        $id = $event['id'] ?? null;
                        
                        if ($data) {
                            $transport->send($data, $eventName, $id);
                        }
                    }
                });
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

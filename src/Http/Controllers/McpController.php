<?php

namespace ElliottLawson\LaravelMcp\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use ElliottLawson\LaravelMcp\McpManager;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ElliottLawson\LaravelMcp\Services\MethodProcessor;
use ElliottLawson\LaravelMcp\Support\LaravelSseTransport;

/**
 * Controller for handling MCP requests and SSE connections.
 */
class McpController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected McpManager $manager,
        protected MethodProcessor $methodProcessor,
    ) {}

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming request
     * @return \Illuminate\Http\Response The response
     */
    public function handle(Request $request)
    {
        // Extract the JSON-RPC request
        $input = $request->input();

        // Check if this is a batch request
        $isBatch = is_array($input) && array_is_list($input);

        if ($isBatch) {
            // Handle batch requests
            $responses = [];
            foreach ($input as $singleRequest) {
                $responses[] = $this->processSingleRequest($singleRequest);
            }

            return response()->json($responses);
        } else {
            // Handle single request
            $response = $this->processSingleRequest($input);

            return response()->json($response);
        }
    }

    /**
     * Process a single JSON-RPC request.
     *
     * @param  array  $request  The JSON-RPC request
     * @return array The JSON-RPC response
     */
    protected function processSingleRequest(array $request)
    {
        // Extract the JSON-RPC request components
        $jsonrpc = $request['jsonrpc'] ?? null;
        $method = $request['method'] ?? null;
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;

        // Validate JSON-RPC version
        if ($jsonrpc !== '2.0') {
            return [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request: JSON-RPC version must be 2.0',
                ],
                'id' => $id,
            ];
        }

        try {
            // Process the method
            $result = $this->processMethod($method, $params);

            // Return the result
            return [
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $id,
            ];
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error processing JSON-RPC request', [
                'exception' => $e,
                'request' => $request,
            ]);

            // Return the error
            return [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => $e->getCode() ?: -32000,
                    'message' => 'Server error: ' . $e->getMessage(),
                ],
                'id' => $id,
            ];
        }
    }

    /**
     * Process a JSON-RPC method call.
     *
     * @param  string|null  $method  The method to call
     * @param  array  $params  The parameters for the method
     * @return mixed The result of the method call
     *
     * @throws \Exception If the method is not found or cannot be processed
     */
    protected function processMethod(?string $method, array $params)
    {
        return $this->methodProcessor->process($method, $params);
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

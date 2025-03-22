<?php

namespace ElliottLawson\LaravelMcp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
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
     */
    public function sse(Request $request, McpManager $mcpManager): StreamedResponse
    {
        // Create a new SSE transport with the heartbeat interval from config
        $heartbeatInterval = config('mcp.sse.heartbeat_interval', 30);
        $transport = new LaravelSseTransport($heartbeatInterval);

        // Connect the server to our transport
        $mcpManager->server()->connect($transport);

        // Dispatch event for new SSE connection
        Event::dispatch('mcp.sse.connected', [$request, $transport]);

        // Start the transport
        $transport->start();

        // Return the SSE response
        return $transport->getResponse();
    }
}

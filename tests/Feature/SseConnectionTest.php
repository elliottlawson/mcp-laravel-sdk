<?php

namespace Tests\Feature;

use ElliottLawson\LaravelMcp\Transport\LaravelSseTransport;
use ElliottLawson\LaravelMcp\McpManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SseConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Cache::flush();
    }

    /** @test */
    public function it_establishes_sse_connection()
    {
        // Make request to SSE endpoint
        $response = $this->get(route('mcp.sse'));

        // Assert response has correct status and headers
        $response->assertStatus(200);
        $this->assertEquals('text/event-stream; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $response->headers->get('Cache-Control'));
        $this->assertEquals('keep-alive', $response->headers->get('Connection'));
        
        // Verify that the SSE connection event was dispatched
        Event::assertDispatched('mcp.sse.connected');
    }

    /** @test */
    public function it_handles_client_to_server_messages()
    {
        // Fake events
        Event::fake();

        // First establish a connection and get the connection ID
        $response = $this->get(route('mcp.sse'));
        $connectionId = null;
        
        Event::assertDispatched('mcp.sse.connected', function ($eventName, $payload) use (&$connectionId) {
            // Check if payload is an array with connection_id at first level
            if (isset($payload['connection_id'])) {
                $connectionId = $payload['connection_id'];
                return true;
            }
            // Or if it's nested in the first element
            elseif (is_array($payload) && isset($payload[0]) && isset($payload[0]['connection_id'])) {
                $connectionId = $payload[0]['connection_id'];
                return true;
            }
            return false;
        });
        
        // Connection ID should be available
        $this->assertNotNull($connectionId);
        
        // Send a message to the connection
        $messageBody = json_encode(['type' => 'test', 'content' => 'Hello from client']);
        $response = $this->post(route('mcp.message'), [
            'connection_id' => $connectionId
        ], [
            'Content-Type' => 'application/json'
        ], $messageBody);
        
        // Assert response is successful
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        // Check for either the transport.message.received or message.received event
        $eventDispatched = false;
        try {
            Event::assertDispatched('mcp.message.received');
            $eventDispatched = true;
        } catch (\Exception $e) {
            try {
                Event::assertDispatched('mcp.transport.message.received');
                $eventDispatched = true;
            } catch (\Exception $e2) {
                // Both failed, let the assertion below fail
            }
        }
        
        $this->assertTrue($eventDispatched, 'Expected either mcp.message.received or mcp.transport.message.received to be dispatched');
    }

    /** @test */
    public function it_processes_messages_through_transport()
    {
        // Fake events
        Event::fake();

        // Get an instance of the transport
        $transport = app(LaravelSseTransport::class);
        
        // Register a message handler
        $result = null;
        $transport->onMessage(function($message) use (&$result) {
            $result = $message;
            return $message; // Return the message to ensure proper handler execution
        });
        
        // Process a message
        $message = json_encode(['type' => 'test', 'content' => 'Processing test']);
        $transport->processMessage($message);
        
        // Check that our handler received the message
        $this->assertEquals($message, $result);
        
        // Check the event was dispatched (use the correct event name that the transport dispatches)
        Event::assertDispatched('mcp.transport.message.received', function($eventName, $payload) use ($message) {
            return $payload[0] === $message;
        });
    }
    
    /** @test */
    public function it_returns_error_for_invalid_connection()
    {
        // Try to send message to non-existent connection
        $response = $this->post(route('mcp.message'), [
            'connection_id' => 'fake-connection-id'
        ], [
            'Content-Type' => 'application/json'
        ], json_encode(['test' => 'message']));
        
        // Assert error response
        $response->assertStatus(400);
        $response->assertJson(['error' => true]);
    }
}

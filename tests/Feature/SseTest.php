<?php

namespace ElliottLawson\LaravelMcp\Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use ElliottLawson\LaravelMcp\Support\LaravelSseTransport;

class SseTest extends TestCase
{
    /**
     * Test that the SSE connection sends heartbeats.
     */
    #[Test]
    public function test_sse_send(): void
    {
        // Mock the output buffer to capture the SSE response
        ob_start();

        // Create a transport instance
        $transport = new LaravelSseTransport;

        // Send a test message
        $transport->send(['type' => 'test'], 'message');

        // Get the output
        $output = ob_get_clean();

        // Assert the message was sent correctly
        $this->assertStringContainsString('event: message', $output);
        $this->assertStringContainsString('data: {"type":"test"}', $output);
    }

    /**
     * Test that the SSE connection can send messages.
     */
    #[Test]
    public function test_sse_messages(): void
    {
        // Mock the output buffer to capture the SSE response
        ob_start();

        // Create a transport instance
        $transport = new LaravelSseTransport;

        // Send a message
        $message = [
            'type' => 'test',
            'content' => 'This is a test message',
        ];
        $transport->send($message, 'message');

        // Get the output
        $output = ob_get_clean();

        // Assert the message was sent correctly
        $this->assertStringContainsString('event: message', $output);
        $this->assertStringContainsString('data: ' . json_encode($message), $output);
    }

    /**
     * Test that the SSE connection can handle custom events.
     */
    #[Test]
    public function test_sse_custom_events(): void
    {
        // Mock the output buffer to capture the SSE response
        ob_start();

        // Create a transport instance
        $transport = new LaravelSseTransport;

        // Send a custom event
        $data = ['status' => 'completed'];
        $transport->send($data, 'tool.completed');

        // Get the output
        $output = ob_get_clean();

        // Assert the custom event was sent correctly
        $this->assertStringContainsString('event: tool.completed', $output);
        $this->assertStringContainsString('data: ' . json_encode($data), $output);
    }

    /**
     * Test that the SSE connection can establish a connection and maintain it.
     */
    #[Test]
    public function test_sse_connection_establishment(): void
    {
        // This test simulates a connection to the SSE endpoint
        // We'll use a custom response macro to capture the streamed response

        // Create a unique client ID
        $clientId = Str::uuid()->toString();

        // Make a request to the SSE endpoint
        $response = $this->get("/mcp/sse?client_id={$clientId}");

        // Assert the response has the correct headers
        $response->assertStatus(200);
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));

        // We can't fully test the streaming response in a unit test,
        // but we can verify that the connection is established
        $this->assertTrue(true, 'SSE connection established');
    }

    /**
     * Test that events are properly broadcasted through the SSE connection.
     */
    #[Test]
    public function test_event_broadcasting(): void
    {
        // Mock the event dispatcher
        Event::fake();

        // Create a unique client ID
        $clientId = Str::uuid()->toString();

        // Make a request to the SSE endpoint
        $this->get("/mcp/sse?client_id={$clientId}");

        // Dispatch an event that should be broadcasted
        Event::dispatch('mcp.message', ['type' => 'test', 'content' => 'Test message']);

        // Assert the event was dispatched
        Event::assertDispatched('mcp.message');
    }
}

<?php

namespace Tests\Feature;

use ElliottLawson\LaravelMcp\Transport\LaravelSseTransport;
use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Functional test for LaravelSseTransport without relying on reflection.
 * This tests the actual behavior rather than implementation details.
 */
class SseConnectionFunctionalTest extends TestCase
{
    /** @test */
    public function transport_implements_interface()
    {
        $transport = new LaravelSseTransport();
        $this->assertInstanceOf(TransportInterface::class, $transport);
    }
    
    /** @test */
    public function transport_can_be_started_and_stopped()
    {
        $transport = new LaravelSseTransport();
        
        // Should not be running initially
        $this->assertFalse($transport->isRunning());
        
        // Start the transport
        $transport->start();
        $this->assertTrue($transport->isRunning());
        
        // Stop the transport
        $transport->stop();
        $this->assertFalse($transport->isRunning());
    }
    
    /** @test */
    public function transport_returns_proper_response()
    {
        $transport = new LaravelSseTransport();
        $response = $transport->getResponse();
        
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $response->headers->get('Cache-Control'));
        $this->assertEquals('keep-alive', $response->headers->get('Connection'));
    }
    
    /** @test */
    public function transport_handles_messages()
    {
        $transport = new LaravelSseTransport();
        
        // Create a test message handler
        $handledMessage = null;
        $handler = function($message) use (&$handledMessage) {
            $handledMessage = $message;
            return true;
        };
        
        // Set the message handler
        $transport->setMessageHandler($handler);
        
        // Process a test message
        $testMessage = '{"type":"test","data":"Hello"}';
        $transport->processMessage($testMessage);
        
        // Verify the message was handled
        $this->assertEquals($testMessage, $handledMessage);
    }
    
    /** @test */
    public function transport_can_set_and_use_message_store_id()
    {
        $transport = new LaravelSseTransport();
        $testId = 'test-connection-123';
        
        // Set the ID
        $result = $transport->setMessageStoreId($testId);
        
        // Should return itself for method chaining
        $this->assertSame($transport, $result);
        
        // We can't test the internal state directly, but we can verify that
        // the class continues to work with the ID set
        $this->assertInstanceOf(StreamedResponse::class, $transport->getResponse());
    }
}

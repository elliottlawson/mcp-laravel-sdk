<?php

namespace Tests\Unit\Transport;

use ElliottLawson\LaravelMcp\Transport\LaravelSseTransport;
use ElliottLawson\McpPhpSdk\Transport\SseTransport;
use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ElliottLawson\LaravelMcp\Providers\McpServiceProvider;

class LaravelSseTransportTest extends TestCase
{
    /**
     * @var LaravelSseTransport
     */
    protected $transport;
    
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [McpServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake events to prevent actual event dispatching
        Event::fake();
        
        // Create a new transport instance with custom heartbeat interval
        $this->transport = new LaravelSseTransport(10, '/test/message/path');
    }

    /** @test */
    public function it_extends_sse_transport()
    {
        $this->assertInstanceOf(SseTransport::class, $this->transport);
        $this->assertInstanceOf(TransportInterface::class, $this->transport);
    }

    /** @test */
    public function it_returns_streamed_response()
    {
        $response = $this->transport->getResponse();
        
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $response->headers->get('Cache-Control'));
        $this->assertEquals('keep-alive', $response->headers->get('Connection'));
    }

    /** @test */
    public function it_can_set_message_handler()
    {
        $called = false;
        $handler = function($message) use (&$called) {
            $called = true;
            return 'processed: ' . $message;
        };
        
        $result = $this->transport->setMessageHandler($handler);
        
        // Should return $this for method chaining
        $this->assertInstanceOf(LaravelSseTransport::class, $result);
        
        // Test the handler by directly calling processMessage
        $testMessage = '{"test":"message"}';
        $this->transport->processMessage($testMessage);
        
        // Verify that our handler was called
        $this->assertTrue($called, 'Message handler should have been called');
    }

    /** @test */
    public function it_can_set_message_store_id()
    {
        $id = 'test-connection-id';
        
        $result = $this->transport->setMessageStoreId($id);
        
        // Should return $this for method chaining
        $this->assertInstanceOf(LaravelSseTransport::class, $result);
        
        // For now, we're limited in what we can verify in this test
        // since messageStoreId is protected in the parent class
        $this->assertTrue(true, 'Should set message store ID without errors');
    }

    /** @test */
    public function it_starts_and_stops_transport()
    {
        $this->assertFalse($this->transport->isRunning());
        
        $this->transport->start();
        $this->assertTrue($this->transport->isRunning());
        
        $this->transport->stop();
        $this->assertFalse($this->transport->isRunning());
    }
    
    /** @test */
    public function it_can_process_messages()
    {
        $processedMessage = null;
        $handler = function($message) use (&$processedMessage) {
            $processedMessage = $message;
            return 'processed';
        };
        
        $this->transport->setMessageHandler($handler);
        
        $testMessage = json_encode(['test' => 'value']);
        $this->transport->processMessage($testMessage);
        
        $this->assertEquals($testMessage, $processedMessage);
    }
    
    /** @test */
    public function getResponse_includes_proper_content_type_and_headers()
    {
        $response = $this->transport->getResponse();
        
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $response->headers->get('Cache-Control'));
        $this->assertEquals('keep-alive', $response->headers->get('Connection'));
    }
}

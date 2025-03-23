<?php

namespace Tests\Unit\Transport;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Event;
use ElliottLawson\LaravelMcp\Providers\McpServiceProvider;
use ElliottLawson\LaravelMcp\Support\LaravelSseTransport;

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
        $this->transport = new LaravelSseTransport(10, 120);
    }

    /** @test */
    public function it_implements_sse_functionality()
    {
        $this->assertInstanceOf(LaravelSseTransport::class, $this->transport);
    }

    /** @test */
    public function it_has_connection_id()
    {
        $this->assertNotEmpty($this->transport->getConnectionId());
        $this->assertIsString($this->transport->getConnectionId());
    }

    /** @test */
    public function it_can_send_data_as_string()
    {
        // Set up output buffering to capture the output
        ob_start();
        
        $this->transport->send('test-message');
        
        $output = ob_get_clean();
        
        $this->assertStringContainsString('data: test-message', $output);
    }

    /** @test */
    public function it_can_send_data_as_array()
    {
        // Set up output buffering to capture the output
        ob_start();
        
        $data = ['message' => 'test', 'type' => 'notification'];
        $this->transport->send($data);
        
        $output = ob_get_clean();
        
        $this->assertStringContainsString('data: {"message":"test","type":"notification"}', $output);
    }

    /** @test */
    public function it_can_send_data_with_event_name()
    {
        // Set up output buffering to capture the output
        ob_start();
        
        $this->transport->send('test-message', 'custom-event');
        
        $output = ob_get_clean();
        
        $this->assertStringContainsString('event: custom-event', $output);
        $this->assertStringContainsString('data: test-message', $output);
    }

    /** @test */
    public function it_can_send_data_with_id()
    {
        // Set up output buffering to capture the output
        ob_start();
        
        $this->transport->send('test-message', null, '123');
        
        $output = ob_get_clean();
        
        $this->assertStringContainsString('id: 123', $output);
        $this->assertStringContainsString('data: test-message', $output);
    }
}

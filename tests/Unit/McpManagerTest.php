<?php

namespace ElliottLawson\LaravelMcp\Tests\Unit;

use Orchestra\Testbench\TestCase;
use ElliottLawson\LaravelMcp\McpManager;
use ElliottLawson\McpPhpSdk\Server\McpServer;
use ElliottLawson\LaravelMcp\Providers\McpServiceProvider;

class McpManagerTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [McpServiceProvider::class];
    }

    public function test_mcp_manager_can_be_resolved()
    {
        $manager = app(McpManager::class);

        $this->assertInstanceOf(McpManager::class, $manager);
    }

    public function test_server_can_be_created()
    {
        $manager = app(McpManager::class);
        $server = $manager->server();

        $this->assertInstanceOf(McpServer::class, $server);
    }

    public function test_server_is_singleton()
    {
        $manager = app(McpManager::class);
        $server1 = $manager->server();
        $server2 = $manager->server();

        $this->assertSame($server1, $server2);
    }
}

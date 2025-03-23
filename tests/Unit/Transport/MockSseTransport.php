<?php

namespace Tests\Unit\Transport;

use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;

/**
 * A mock version of SseTransport for testing LaravelSseTransport without reflection issues.
 */
class MockSseTransport implements TransportInterface
{
    public $outputCallback;

    public $headerCallback;

    public $flushCallback;

    public $messageHandler;

    public $messageStoreId;

    public $heartbeatInterval;

    public $running = false;

    public function __construct(string $messagePath = '/mcp/message', int $heartbeatInterval = 30)
    {
        $this->heartbeatInterval = $heartbeatInterval;

        // Set default callbacks
        $this->outputCallback = function (string $data) {
            echo $data;
        };

        $this->headerCallback = function (string $name, string $value) {
            header("$name: $value");
        };

        $this->flushCallback = function () {
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        };
    }

    public function send(string $message): void
    {
        if ($this->outputCallback) {
            ($this->outputCallback)("data: $message\n\n");
        }

        if ($this->flushCallback) {
            ($this->flushCallback)();
        }
    }

    public function setMessageHandler(callable $handler): TransportInterface
    {
        $this->messageHandler = $handler;

        return $this;
    }

    public function start(): void
    {
        $this->running = true;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
}

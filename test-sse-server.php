<?php

require __DIR__ . '/vendor/autoload.php';

use ElliottLawson\LaravelMcp\Transport\LaravelSseTransport;
use Symfony\Component\HttpFoundation\StreamedResponse;

// This script tests the LaravelSseTransport class functionality directly
// Run with: php test-sse-server.php

echo "Testing LaravelSseTransport implementation...\n";

// Create the transport with a short heartbeat interval for testing
$transport = new LaravelSseTransport(3);

echo "✓ Created LaravelSseTransport instance\n";

// Test message handler
$messageHandled = false;
$transport->setMessageHandler(function($message) use (&$messageHandled) {
    echo "\n✓ Message handler received: " . $message . "\n";
    $messageHandled = true;
});

echo "✓ Set message handler\n";

// Test setting message store ID
$testConnectionId = 'test-connection-' . uniqid();
$transport->setMessageStoreId($testConnectionId);

echo "✓ Set message store ID: " . $testConnectionId . "\n";

// Test start/stop functionality
$transport->start();
if ($transport->isRunning()) {
    echo "✓ Transport started successfully\n";
} else {
    echo "✗ Failed to start transport\n";
    exit(1);
}

$transport->stop();
if (!$transport->isRunning()) {
    echo "✓ Transport stopped successfully\n";
} else {
    echo "✗ Failed to stop transport\n";
    exit(1);
}

// Test message processing
$testMessage = json_encode([
    'jsonrpc' => '2.0',
    'method' => 'test_method',
    'params' => ['hello' => 'world'],
    'id' => 12345
]);

echo "Testing processMessage...\n";
$transport->processMessage($testMessage);

if ($messageHandled) {
    echo "✓ Message was processed by handler\n";
} else {
    echo "✗ Message was not processed by handler\n";
    exit(1);
}

// Test creating a response
$response = $transport->getResponse();
if ($response instanceof StreamedResponse) {
    echo "✓ Got StreamedResponse with headers:\n";
    foreach (['Content-Type', 'Cache-Control', 'Connection'] as $header) {
        echo "  - $header: " . $response->headers->get($header) . "\n";
    }
} else {
    echo "✗ Failed to get StreamedResponse\n";
    exit(1);
}

echo "\nAll tests passed! ✓\n";
echo "The LaravelSseTransport is working correctly.\n";
echo "\nTo test in a browser, you can use the routes in routes/test-sse.php\n";
echo "and visit http://your-laravel-app.test/test-sse-client\n";

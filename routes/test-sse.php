<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use ElliottLawson\LaravelMcp\Transport\LaravelSseTransport;

/*
|--------------------------------------------------------------------------
| SSE Testing Routes
|--------------------------------------------------------------------------
|
| These routes are for testing the SSE implementation. They provide a
| simple way to verify that the SSE transport works correctly.
|
*/

Route::get('/test-sse', function () {
    // Create the transport with a 10-second heartbeat
    $transport = new LaravelSseTransport(10);

    // Set up message handler
    $transport->setMessageHandler(function ($message) {
        \Log::info("Received message: {$message}");
    });

    // Store connection info
    $connectionId = \Illuminate\Support\Str::uuid()->toString();
    session(['test_sse_connection_id' => $connectionId]);

    // Send a test message after connection
    $transport->setMessageStoreId($connectionId);

    // Store transport in cache for message endpoint
    \Illuminate\Support\Facades\Cache::put(
        "test.sse.transport.{$connectionId}",
        $transport,
        now()->addHour()
    );

    // Get the SSE response
    $response = $transport->getResponse();

    // Output initial message after 2 seconds
    $originalCallback = $response->getCallback();
    $response->setCallback(function () use ($originalCallback, $transport) {
        // Execute original callback in a separate thread
        $pid = pcntl_fork();
        if ($pid == 0) {
            // Child process - run the event loop
            $originalCallback();
            exit(0);
        } else {
            // Parent process - send welcome message after 2 seconds
            sleep(2);
            $transport->send(json_encode([
                'type' => 'welcome',
                'message' => 'SSE connection established successfully!',
                'time' => now()->toIso8601String(),
            ]));

            // Send another message after 5 seconds
            sleep(3);
            $transport->send(json_encode([
                'type' => 'info',
                'message' => 'This is a test message from the server',
                'time' => now()->toIso8601String(),
            ]));

            // Wait for the child process to finish
            pcntl_waitpid($pid, $status);
        }
    });

    return $response;
});

// Endpoint to send messages to the SSE connection
Route::post('/test-sse/message', function (Request $request) {
    // Get connection ID from session
    $connectionId = session('test_sse_connection_id');
    if (!$connectionId) {
        return response()->json(['error' => 'No active connection'], 400);
    }

    // Get transport from cache
    $transport = \Illuminate\Support\Facades\Cache::get("test.sse.transport.{$connectionId}");
    if (!$transport instanceof LaravelSseTransport) {
        return response()->json(['error' => 'Transport not found'], 404);
    }

    // Get message from request
    $message = $request->getContent();

    // Process message
    $transport->processMessage($message);

    return response()->json([
        'success' => true,
        'message' => 'Message sent to SSE connection',
    ]);
});

// HTML page for testing
Route::get('/test-sse-client', function () {
    return view('test-sse');
});

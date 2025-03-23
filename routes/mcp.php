<?php

use Illuminate\Support\Facades\Route;
use ElliottLawson\LaravelMcp\Http\Controllers\McpController;
use ElliottLawson\LaravelMcp\Http\Middleware\McpSseMiddleware;
use ElliottLawson\LaravelMcp\Http\Middleware\McpCorsMiddleware;
use ElliottLawson\LaravelMcp\Http\Middleware\McpJsonRpcMiddleware;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| Here is where you can register MCP routes for your application.
|
*/

// Apply CORS middleware to all MCP routes
Route::middleware([McpCorsMiddleware::class])->group(function () {
    // JSON-RPC endpoint
    Route::post('/json-rpc', [McpController::class, 'handle'])
        ->middleware([McpJsonRpcMiddleware::class])
        ->name('mcp.jsonrpc');

    // SSE endpoint
    Route::get('/sse', [McpController::class, 'sse'])
        ->middleware([McpSseMiddleware::class])
        ->name('mcp.sse');
});

<?php

use Illuminate\Support\Facades\Route;
use ElliottLawson\LaravelMcp\Http\Middleware\McpMiddleware;
use ElliottLawson\LaravelMcp\Http\Controllers\McpController;

// Define the routes with appropriate names
Route::middleware([McpMiddleware::class])->group(function () {
    // Handle MCP requests
    Route::post('/mcp', [McpController::class, 'handle'])->name('mcp.handle');

    // Handle SSE connections
    Route::get('/mcp/events', [McpController::class, 'sse'])->name('mcp.sse');
});

<?php

use Illuminate\Support\Facades\Route;
use ElliottLawson\LaravelMcp\Http\Middleware\McpMiddleware;
use ElliottLawson\LaravelMcp\Http\Controllers\McpController;

// Define the routes with appropriate names
Route::middleware([McpMiddleware::class])->group(function () {
    // Handle MCP requests
    Route::post('/', [McpController::class, 'handle'])->name('mcp.handle');

    // Handle SSE connections
    Route::get('/events', [McpController::class, 'sse'])->name('mcp.sse');
    
    // Handle client-to-server messages for SSE connections
    Route::post('/message', [McpController::class, 'message'])->name('mcp.message');
});

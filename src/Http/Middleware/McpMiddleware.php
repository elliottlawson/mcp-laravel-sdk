<?php

namespace ElliottLawson\LaravelMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\Event;
use ElliottLawson\LaravelMcp\Facades\Mcp;

class McpMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // If this is an SSE connection request
        if ($request->route()->getName() === 'mcp.sse') {
            return $next($request);
        }

        // Check if this is an MCP request based on the route
        if ($request->route()->getName() === 'mcp.handle') {
            // The actual handling is done in the controller
            return $next($request);
        }

        // Not an MCP request, continue
        return $next($request);
    }

    /**
     * Handle events after the response has been sent.
     *
     * @return void
     */
    public function terminate(Request $request, SymfonyResponse $response)
    {
        // If this was an MCP request, we can do any cleanup here
        if ($request->route() && in_array($request->route()->getName(), ['mcp.handle', 'mcp.sse'])) {
            Event::dispatch('mcp.request.terminated', [$request, $response]);
        }
    }
}

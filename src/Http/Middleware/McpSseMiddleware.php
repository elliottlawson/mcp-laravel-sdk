<?php

namespace ElliottLawson\LaravelMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware for handling SSE connections.
 */
class McpSseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only attempt to disable output buffering if headers haven't been sent yet
        if (!headers_sent() && ob_get_level()) {
            ob_end_clean();
        }

        // Only set INI options if headers haven't been sent yet
        if (!headers_sent()) {
            // Prevent Laravel from buffering the response
            ini_set('output_buffering', 'off');
            ini_set('zlib.output_compression', false);
        }

        // Disable the session cookie
        config(['session.driver' => 'array']);

        // Set time limit based on config
        $maxExecutionTime = config('mcp.sse.max_execution_time', 0);
        if ($maxExecutionTime > 0) {
            set_time_limit($maxExecutionTime);
        } else {
            set_time_limit(0); // No time limit
        }

        // Prevent client disconnections from triggering PHP errors
        ignore_user_abort(true);

        return $next($request);
    }
}

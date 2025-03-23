<?php

namespace ElliottLawson\LaravelMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Middleware for handling CORS for MCP requests.
 */
class McpCorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get CORS configuration from config
        $allowedOrigins = config('mcp.http.cors.allowed_origins', ['*']);
        $allowedMethods = config('mcp.http.cors.allowed_methods', ['GET', 'POST', 'OPTIONS']);
        $allowedHeaders = config('mcp.http.cors.allowed_headers', ['Content-Type', 'X-Requested-With', 'Authorization']);
        $maxAge = config('mcp.http.cors.max_age', 86400);
        
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            $response = new Response('', 204);
        } else {
            $response = $next($request);
        }
        
        // Determine the origin
        $origin = $request->header('Origin');
        
        // If the origin is allowed or we allow all origins
        if ($origin && (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins))) {
            $response->header('Access-Control-Allow-Origin', $origin);
        } elseif (in_array('*', $allowedOrigins)) {
            $response->header('Access-Control-Allow-Origin', '*');
        }
        
        // Add other CORS headers
        $response->header('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
        $response->header('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Control-Max-Age', $maxAge);
        
        return $response;
    }
}

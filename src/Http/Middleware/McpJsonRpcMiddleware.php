<?php

namespace ElliottLawson\LaravelMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Middleware for validating JSON-RPC requests.
 */
class McpJsonRpcMiddleware
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
        // Only validate POST requests
        if ($request->isMethod('POST')) {
            // Check Content-Type header
            $contentType = $request->header('Content-Type');
            if (!$contentType || !str_contains($contentType, 'application/json')) {
                return new Response(json_encode([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error: Invalid Content-Type, expected application/json',
                    ],
                    'id' => null,
                ]), 415, ['Content-Type' => 'application/json']);
            }

            // Get request content
            $content = $request->getContent();
            
            // Check if content is valid JSON
            $json = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new Response(json_encode([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error: ' . json_last_error_msg(),
                    ],
                    'id' => null,
                ]), 400, ['Content-Type' => 'application/json']);
            }
            
            // Validate JSON-RPC 2.0 structure
            if (!$this->validateJsonRpc($json)) {
                return new Response(json_encode([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32600,
                        'message' => 'Invalid Request: Not a valid JSON-RPC 2.0 request',
                    ],
                    'id' => isset($json['id']) ? $json['id'] : null,
                ]), 400, ['Content-Type' => 'application/json']);
            }
        }
        
        return $next($request);
    }
    
    /**
     * Validate JSON-RPC 2.0 request structure.
     *
     * @param  array|null  $json
     * @return bool
     */
    protected function validateJsonRpc($json): bool
    {
        // If it's a batch request (array of requests)
        if (is_array($json) && !isset($json['jsonrpc'])) {
            if (empty($json)) {
                return false;
            }
            
            foreach ($json as $request) {
                if (!$this->validateSingleJsonRpc($request)) {
                    return false;
                }
            }
            
            return true;
        }
        
        // Single request
        return $this->validateSingleJsonRpc($json);
    }
    
    /**
     * Validate a single JSON-RPC 2.0 request.
     *
     * @param  array|null  $json
     * @return bool
     */
    protected function validateSingleJsonRpc($json): bool
    {
        // Must be an object
        if (!is_array($json) || empty($json)) {
            return false;
        }
        
        // Must have jsonrpc property with value "2.0"
        if (!isset($json['jsonrpc']) || $json['jsonrpc'] !== '2.0') {
            return false;
        }
        
        // Must have method property
        if (!isset($json['method']) || !is_string($json['method'])) {
            return false;
        }
        
        // If params is present, it must be an object or array
        if (isset($json['params']) && !is_array($json['params'])) {
            return false;
        }
        
        // id can be string, number, null, or not present (notification)
        if (isset($json['id']) && !is_string($json['id']) && !is_int($json['id']) && !is_null($json['id'])) {
            return false;
        }
        
        return true;
    }
}

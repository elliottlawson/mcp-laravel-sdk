<?php

namespace ElliottLawson\LaravelMcp\Tools;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Tool implementation for making HTTP requests.
 */
class HttpTool extends BaseTool
{
    /**
     * The default request options.
     */
    protected array $options = [];

    /**
     * Create a new HTTP tool instance.
     *
     * @param  string  $name  The tool name
     * @param  array  $options  Default request options
     * @param  array  $metadata  Additional metadata for the tool
     */
    public function __construct(string $name, array $options = [], array $metadata = [])
    {
        // Define the JSON schema for the tool parameters
        $schema = [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'The URL to request',
                ],
                'method' => [
                    'type' => 'string',
                    'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                    'description' => 'The HTTP method to use',
                    'default' => 'GET',
                ],
                'headers' => [
                    'type' => 'object',
                    'description' => 'The HTTP headers to send',
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'The data to send with the request',
                ],
                'query' => [
                    'type' => 'object',
                    'description' => 'The query parameters to append to the URL',
                ],
                'timeout' => [
                    'type' => 'integer',
                    'description' => 'The request timeout in seconds',
                ],
            ],
        ];

        parent::__construct($name, $schema, array_merge([
            'description' => 'Makes HTTP requests to external APIs',
        ], $metadata));

        $this->options = $options;
    }

    /**
     * Execute the tool with the given parameters.
     *
     * @param  array  $params  The parameters for the tool execution
     * @return mixed The result of the tool execution
     */
    public function execute(array $params = [])
    {
        // Validate parameters
        if (!$this->validateParameters($params)) {
            throw new \InvalidArgumentException('Invalid parameters for HTTP tool');
        }

        // Get the URL
        $url = $params['url'];

        // Get the HTTP method
        $method = strtoupper($params['method'] ?? 'GET');

        // Merge default options with provided parameters
        $options = array_merge($this->options, $params);

        // Create the HTTP request
        $request = Http::withOptions([
            'timeout' => $options['timeout'] ?? 30,
        ]);

        // Add headers if provided
        if (isset($options['headers']) && is_array($options['headers'])) {
            $request = $request->withHeaders($options['headers']);
        }

        try {
            // Make the request based on the method
            $response = match ($method) {
                'GET' => $request->get($url, $options['query'] ?? []),
                'POST' => $request->post($url, $options['data'] ?? []),
                'PUT' => $request->put($url, $options['data'] ?? []),
                'PATCH' => $request->patch($url, $options['data'] ?? []),
                'DELETE' => $request->delete($url, $options['data'] ?? []),
                'HEAD' => $request->head($url, $options['query'] ?? []),
                'OPTIONS' => $request->send('OPTIONS', $url, $options),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            // Format the response
            return [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $this->parseResponseBody($response),
                'successful' => $response->successful(),
            ];
        } catch (\Exception $e) {
            Log::error("HTTP tool error: {$e->getMessage()}", [
                'exception' => $e,
                'url' => $url,
                'method' => $method,
            ]);

            return [
                'status' => 0,
                'error' => $e->getMessage(),
                'successful' => false,
            ];
        }
    }

    /**
     * Parse the response body based on content type.
     *
     * @param  \Illuminate\Http\Client\Response  $response  The HTTP response
     * @return mixed The parsed response body
     */
    protected function parseResponseBody($response)
    {
        // If the response is JSON, return it as an array
        if ($response->header('Content-Type') && str_contains($response->header('Content-Type'), 'application/json')) {
            return $response->json();
        }

        // Otherwise, return it as a string
        return $response->body();
    }
}

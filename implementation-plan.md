# Laravel MCP SDK Implementation Plan

## Overview

This document outlines the development of a Laravel-native Model Context Protocol (MCP) SDK. The SDK will provide a clean, developer-friendly API for implementing MCP servers in Laravel applications while handling all the complexities of the protocol behind the scenes.

## Laravel Compatibility

This package is designed to be compatible with Laravel 10.0 and higher, with specific optimizations for Laravel 11 and 12. We will leverage the latest Laravel features including:

- Laravel 11/12's improved dependency injection
- Laravel 12's enhanced event broadcasting system
- Laravel's streamlined HTTP client for server-to-server communication
- Modern PHP 8.1+ features (attributes, enums, etc.)
- Improved performance with Laravel 12's optimized request handling

The package architecture will ensure forward compatibility while taking advantage of the latest Laravel improvements.

## Core API Design

```php
// Register a resource
Mcp::resource('users', function($params) {
    // Return a user resource
    return User::find($params['id']);
});

// Register a tool with JSON Schema
Mcp::tool('file_search', [
    'description' => 'Search for files in the application',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'query' => ['type' => 'string', 'description' => 'Search query'],
            'path' => ['type' => 'string', 'description' => 'Path to search in'],
        ],
        'required' => ['query']
    ]
], function($params) {
    // Handle tool execution
    return Storage::files($params['path'] ?? '/', $params['query']);
});

// Register a prompt
Mcp::prompt('greeting', 'Hello, I am your Laravel assistant. How can I help you today?');

// Register Eloquent models as resources
Mcp::eloquentResource(User::class);
```

## Implementation Components

### 1. Package Structure

```
laravel-mcp/
├── config/
│   └── mcp.php
├── database/
│   └── migrations/
├── resources/
│   └── views/
├── routes/
│   └── mcp.php
├── src/
│   ├── Console/
│   │   └── Commands/
│   ├── Contracts/
│   │   ├── ResourceInterface.php
│   │   ├── ToolInterface.php
│   │   └── PromptInterface.php
│   ├── Events/
│   │   ├── McpConnectionEstablished.php
│   │   ├── McpMessageReceived.php
│   │   └── McpMessageSent.php
│   ├── Exceptions/
│   │   └── McpException.php
│   ├── Facades/
│   │   └── Mcp.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── McpController.php
│   │   └── Middleware/
│   │       └── McpMiddleware.php
│   ├── JsonRpc/
│   │   ├── McpProcedure.php
│   │   └── McpServer.php
│   ├── Models/
│   │   ├── McpResource.php
│   │   ├── McpTool.php
│   │   └── McpPrompt.php
│   ├── Providers/
│   │   └── McpServiceProvider.php
│   ├── Resources/
│   │   └── EloquentResource.php
│   ├── Support/
│   │   ├── McpManager.php
│   │   └── SchemaValidator.php
│   ├── Tools/
│   │   └── ToolExecutor.php
│   └── Transport/
│       └── SseTransport.php
├── tests/
│   ├── Feature/
│   └── Unit/
├── composer.json
└── README.md
```

### 2. Dependencies

- **Sajya/Server**: For JSON-RPC 2.0 implementation
- **Laravel Framework**: 10.0 or higher (optimized for Laravel 11 and 12)
- **JSON Schema Validator**: For validating tool parameters

## Implementation Plan

### Phase 1: Core Infrastructure

#### 1.1. Package Setup

- Create package structure
- Set up composer.json with dependencies
- Create service provider and facade

#### 1.2. Configuration

```php
// config/mcp.php
return [
    // Server configuration
    'server' => [
        'name' => 'Laravel MCP Server',
        'version' => '1.0.0',
        'heartbeat_interval' => 30, // seconds
    ],
    
    // Capabilities
    'capabilities' => [
        'resources' => true,
        'tools' => true,
        'prompts' => true,
    ],
    
    // Default routes
    'routes' => [
        'prefix' => 'mcp',
        'middleware' => ['api'],
    ],
    
    // Default prompts
    'prompts' => [
        'system' => 'You are a helpful assistant integrated with Laravel.',
    ],
];
```

#### 1.3. Service Provider

The service provider will register the MCP manager, routes, and configuration.

#### 1.4. Facade

Create a clean facade for the MCP manager to provide a simple API.

### Phase 2: JSON-RPC Integration

#### 2.1. McpManager Implementation

The McpManager will be the core class that handles registration of resources, tools, and prompts.

#### 2.2. JSON-RPC Consistency Improvements

To address the previous issues with JSON-RPC 2.0 consistency:

1. **Strict Message Format Validation**:
   - Validate all incoming and outgoing messages against the JSON-RPC 2.0 specification
   - Ensure proper error responses for malformed requests
   - Maintain consistent message structure throughout the application

2. **Proper Request/Response Handling**:
   - Implement proper request batching and response correlation
   - Handle notifications correctly (no response required)
   - Ensure IDs are properly maintained between requests and responses

3. **Error Standardization**:
   - Use standard JSON-RPC error codes and messages
   - Provide detailed error information in the data field
   - Implement proper exception-to-error conversion

4. **Sajya Integration**:
   - Leverage Sajya's JSON-RPC implementation while ensuring MCP compliance
   - Extend Sajya's functionality where needed for MCP-specific features
   - Ensure proper serialization/deserialization of messages

#### 2.3. McpProcedure Implementation

Implement the JSON-RPC procedures for handling MCP protocol methods.

### Phase 3: SSE Transport Implementation

#### 3.1. SseTransport Class

Implement a proper Server-Sent Events transport that handles:
- Content type headers
- Connection management
- Heartbeats
- Message formatting

#### 3.2. Laravel-Specific SSE Optimizations

To address the previous issues with headers and content types in Laravel:

1. **Direct Output Control**: 
   - Use direct output control with proper flushing techniques
   - Ensure headers are sent correctly before any content
   - Disable output buffering at the appropriate times

2. **Header Management**:
   - Set SSE-specific headers directly in the response
   - Include `X-Accel-Buffering: no` to prevent proxy buffering
   - Ensure proper CORS headers for cross-origin requests

3. **Connection Handling**:
   - Detect connection closure properly
   - Handle reconnection attempts gracefully
   - Implement proper event IDs for resuming connections

4. **Laravel Integration**:
   - Work with Laravel's response lifecycle instead of fighting against it
   - Use Laravel's streaming response with proper callback handling
   - Integrate with Laravel's event system for message broadcasting

#### 3.3. Controller Implementation

Create controllers for handling:
- JSON-RPC requests
- SSE connections
- Message posting

### Phase 4: Resource Implementation

#### 4.1. EloquentResource Class

Create a class for automatically converting Eloquent models to MCP resources.

#### 4.2. Resource Registration

Implement the API for registering custom resources and Eloquent models.

### Phase 5: Tool Implementation

#### 5.1. ToolExecutor Class

Create a class for executing tools with proper parameter validation.

#### 5.2. Tool Registration

Implement the API for registering tools with JSON Schema validation.

### Phase 6: Prompt Implementation

#### 6.1. Prompt Class

Create a class for managing prompts and prompt templates.

#### 6.2. Prompt Registration

Implement the API for registering prompts and prompt templates.

### Phase 7: Testing and Documentation

#### 7.1. Unit Tests

Write comprehensive unit tests for all components.

#### 7.2. Feature Tests

Write feature tests for the complete MCP server implementation.

#### 7.3. Documentation

Create detailed documentation for:
- Installation
- Configuration
- API usage
- Custom implementations
- Examples

## Key Features

1. **Simple API**: Clean, fluent interface for registering components
2. **Laravel Integration**: Seamless integration with Eloquent models and Laravel patterns
3. **Automatic Routing**: Routes are registered automatically
4. **Event System**: Events are dispatched for all MCP actions
5. **Validation**: Tool parameters are validated against JSON Schema
6. **Error Handling**: Proper error handling and reporting
7. **Customization**: All components can be customized or extended

## Benefits

1. **Developer Experience**: Easy to use with minimal boilerplate
2. **Performance**: Optimized for Laravel's request lifecycle
3. **Reliability**: Proper handling of connections and messages
4. **Maintainability**: Clean, well-structured code with comprehensive tests
5. **Extensibility**: Easy to extend or customize for specific needs

## Conclusion: Advantages Over Previous Implementation

This Laravel-native MCP implementation offers significant advantages over the previous approach that extended the PHP SDK:

1. **No Reflection Magic**: The previous implementation relied heavily on reflection to access protected properties in the parent class, which was fragile and could break with PHP updates. The new implementation uses direct Laravel patterns without reflection.

2. **Proper Header Handling**: By working with Laravel's response lifecycle instead of fighting against it, we avoid the header and content type issues that plagued the previous implementation.

3. **JSON-RPC Consistency**: With strict validation and proper message handling, we ensure consistent JSON-RPC 2.0 compliance throughout the application.

4. **Simplified Architecture**: No complex inheritance chains or dual implementation patterns. The new approach is simpler, more maintainable, and easier to understand.

5. **Laravel-Optimized**: Built specifically for Laravel 10+ with optimizations for Laravel 11 and 12, taking advantage of modern Laravel features instead of trying to adapt a framework-agnostic implementation.

6. **Developer-Friendly API**: The new API is designed from the ground up to be intuitive for Laravel developers, requiring minimal code to implement MCP functionality.

7. **Better Testing**: The simpler architecture and Laravel-native approach make testing much easier and more reliable.

By rebuilding from the ground up with Laravel's patterns and best practices, we create a more robust, maintainable, and developer-friendly MCP implementation that "just works" without the headaches of the previous approach.

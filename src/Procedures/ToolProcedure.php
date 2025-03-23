<?php

namespace ElliottLawson\LaravelMcp\Procedures;

use Illuminate\Support\Facades\Log;
use ElliottLawson\LaravelMcp\Exceptions\ToolNotFoundException;
use ElliottLawson\LaravelMcp\Exceptions\InvalidToolParametersException;

/**
 * Procedure for handling tool-related methods.
 */
class ToolProcedure extends BaseProcedure
{
    /**
     * The name of the procedure that will be used for RPC.
     */
    public static string $name = 'tool';

    /**
     * List all available tools.
     */
    public function list(): array
    {
        $tools = $this->manager->getTools();
        $result = [];

        foreach ($tools as $name => $tool) {
            $result[$name] = [
                'name' => $name,
                'schema' => $tool['schema'] ?? null,
                'metadata' => $tool['handler'] instanceof \ElliottLawson\LaravelMcp\Contracts\ToolContract
                    ? $tool['handler']->getMetadata()
                    : [],
            ];
        }

        return $result;
    }

    /**
     * Execute a tool by name.
     *
     * @param  string  $name  The tool name
     * @param  array  $params  The parameters for the tool execution
     * @return mixed
     *
     * @throws ToolNotFoundException
     * @throws InvalidToolParametersException
     */
    public function execute(string $name, array $params = [])
    {
        $tools = $this->manager->getTools();

        if (!isset($tools[$name])) {
            throw new ToolNotFoundException("Tool '{$name}' not found");
        }

        $tool = $tools[$name];
        $handler = $tool['handler'];
        $schema = $tool['schema'] ?? null;

        // Validate parameters against schema if available
        if ($schema && !$this->validateParameters($params, $schema)) {
            throw new InvalidToolParametersException("Invalid parameters for tool '{$name}'");
        }

        try {
            // If the handler is a ToolContract
            if ($handler instanceof \ElliottLawson\LaravelMcp\Contracts\ToolContract) {
                return $handler->execute($params);
            }

            // If the handler is a callable
            if (is_callable($handler)) {
                return $handler($params);
            }

            // If we got here, we don't know how to handle this tool
            throw new \InvalidArgumentException("Invalid tool handler for '{$name}'");
        } catch (\Exception $e) {
            Log::error("Error executing tool '{$name}': " . $e->getMessage(), [
                'exception' => $e,
                'params' => $params,
            ]);

            throw $e;
        }
    }

    /**
     * Get the schema for a tool.
     *
     * @param  string  $name  The tool name
     *
     * @throws ToolNotFoundException
     */
    public function schema(string $name): ?array
    {
        $tools = $this->manager->getTools();

        if (!isset($tools[$name])) {
            throw new ToolNotFoundException("Tool '{$name}' not found");
        }

        return $tools[$name]['schema'] ?? null;
    }

    /**
     * Validate parameters against a JSON schema.
     *
     * @param  array  $params  The parameters to validate
     * @param  array  $schema  The JSON schema
     */
    protected function validateParameters(array $params, array $schema): bool
    {
        // If no schema, assume valid
        if (empty($schema)) {
            return true;
        }

        // Check required properties
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $required) {
                if (!isset($params[$required])) {
                    return false;
                }
            }
        }

        // Check property types
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($params as $key => $value) {
                if (isset($schema['properties'][$key])) {
                    $type = $schema['properties'][$key]['type'] ?? null;
                    if ($type && !$this->validateType($value, $type)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Validate a value against a JSON schema type.
     *
     * @param  mixed  $value  The value to validate
     * @param  string  $type  The JSON schema type
     */
    protected function validateType($value, string $type): bool
    {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'number':
                return is_numeric($value);
            case 'integer':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'boolean':
                return is_bool($value);
            case 'array':
                return is_array($value);
            case 'object':
                return is_array($value) && array_keys($value) !== range(0, count($value) - 1);
            case 'null':
                return is_null($value);
            default:
                return true;
        }
    }
}

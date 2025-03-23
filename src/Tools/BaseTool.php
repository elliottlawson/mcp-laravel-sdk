<?php

namespace ElliottLawson\LaravelMcp\Tools;

use ElliottLawson\LaravelMcp\Contracts\ToolContract;

/**
 * Base class for MCP tools.
 */
abstract class BaseTool implements ToolContract
{
    /**
     * The tool name.
     */
    protected string $name;

    /**
     * The tool schema.
     */
    protected array $schema = [];

    /**
     * The tool metadata.
     */
    protected array $metadata = [];

    /**
     * Create a new tool instance.
     *
     * @param  string  $name  The tool name
     * @param  array  $schema  The JSON schema for the tool parameters
     * @param  array  $metadata  Additional metadata for the tool
     */
    public function __construct(string $name, array $schema = [], array $metadata = [])
    {
        $this->name = $name;
        $this->schema = $schema;
        $this->metadata = array_merge([
            'name' => $name,
            'description' => '',
        ], $metadata);
    }

    /**
     * Execute the tool with the given parameters.
     *
     * @param  array  $params  The parameters for the tool execution
     * @return mixed The result of the tool execution
     */
    abstract public function execute(array $params = []);

    /**
     * Get the tool schema.
     *
     * @return array The JSON schema for the tool parameters
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * Get the tool metadata.
     *
     * @return array The metadata for the tool
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set the tool schema.
     *
     * @param  array  $schema  The JSON schema for the tool parameters
     * @return $this
     */
    public function setSchema(array $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * Set the tool metadata.
     *
     * @param  array  $metadata  The metadata for the tool
     * @return $this
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Set a specific metadata value.
     *
     * @param  string  $key  The metadata key
     * @param  mixed  $value  The metadata value
     * @return $this
     */
    public function setMetadataValue(string $key, $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Validate parameters against the schema.
     *
     * @param  array  $params  The parameters to validate
     * @return bool Whether the parameters are valid
     */
    protected function validateParameters(array $params): bool
    {
        // If no schema, assume valid
        if (empty($this->schema)) {
            return true;
        }

        // Check required properties
        if (isset($this->schema['required']) && is_array($this->schema['required'])) {
            foreach ($this->schema['required'] as $required) {
                if (!isset($params[$required])) {
                    return false;
                }
            }
        }

        // Check property types
        if (isset($this->schema['properties']) && is_array($this->schema['properties'])) {
            foreach ($params as $key => $value) {
                if (isset($this->schema['properties'][$key])) {
                    $type = $this->schema['properties'][$key]['type'] ?? null;
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
     * @return bool Whether the value is valid
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

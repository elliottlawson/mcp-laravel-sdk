<?php

namespace ElliottLawson\LaravelMcp\Contracts;

/**
 * Contract for MCP tools.
 */
interface ToolContract
{
    /**
     * Execute the tool with the given parameters.
     *
     * @param  array  $params  The parameters for the tool execution
     * @return mixed The result of the tool execution
     */
    public function execute(array $params = []);

    /**
     * Get the tool schema.
     *
     * @return array The JSON schema for the tool parameters
     */
    public function getSchema(): array;

    /**
     * Get the tool metadata.
     *
     * @return array The metadata for the tool
     */
    public function getMetadata(): array;
}

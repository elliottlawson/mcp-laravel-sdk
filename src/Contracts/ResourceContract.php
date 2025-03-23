<?php

namespace ElliottLawson\LaravelMcp\Contracts;

/**
 * Contract for MCP resources.
 */
interface ResourceContract
{
    /**
     * Get the resource data.
     *
     * @param array $params The parameters for the resource request
     * @return mixed The resource data
     */
    public function getData(array $params = []);
    
    /**
     * Get the resource schema.
     *
     * @return array|null The JSON schema for the resource
     */
    public function getSchema(): ?array;
    
    /**
     * Get the resource metadata.
     *
     * @return array The metadata for the resource
     */
    public function getMetadata(): array;
}

<?php

namespace ElliottLawson\LaravelMcp\Resources;

use ElliottLawson\LaravelMcp\Contracts\ResourceContract;

/**
 * Base class for MCP resources.
 */
abstract class BaseResource implements ResourceContract
{
    /**
     * The resource name.
     */
    protected string $name;

    /**
     * The resource schema.
     */
    protected ?array $schema = null;

    /**
     * The resource metadata.
     */
    protected array $metadata = [];

    /**
     * Create a new resource instance.
     *
     * @param  string  $name  The resource name
     * @param  array  $metadata  Additional metadata for the resource
     */
    public function __construct(string $name, array $metadata = [])
    {
        $this->name = $name;
        $this->metadata = array_merge([
            'name' => $name,
            'description' => '',
        ], $metadata);
    }

    /**
     * Get the resource data.
     *
     * @param  array  $params  The parameters for the resource request
     * @return mixed The resource data
     */
    abstract public function getData(array $params = []);

    /**
     * Get the resource schema.
     *
     * @return array|null The JSON schema for the resource
     */
    public function getSchema(): ?array
    {
        return $this->schema;
    }

    /**
     * Get the resource metadata.
     *
     * @return array The metadata for the resource
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set the resource schema.
     *
     * @param  array|null  $schema  The JSON schema for the resource
     * @return $this
     */
    public function setSchema(?array $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * Set the resource metadata.
     *
     * @param  array  $metadata  The metadata for the resource
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
}

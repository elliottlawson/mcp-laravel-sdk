<?php

namespace ElliottLawson\LaravelMcp\Prompts;

use ElliottLawson\LaravelMcp\Contracts\PromptContract;

/**
 * Base class for MCP prompts.
 */
abstract class BasePrompt implements PromptContract
{
    /**
     * The prompt name.
     */
    protected string $name;

    /**
     * The prompt content.
     */
    protected string $content;

    /**
     * The prompt metadata.
     */
    protected array $metadata = [];

    /**
     * Create a new prompt instance.
     *
     * @param string $name The prompt name
     * @param string $content The prompt content
     * @param array $metadata Additional metadata for the prompt
     */
    public function __construct(string $name, string $content, array $metadata = [])
    {
        $this->name = $name;
        $this->content = $content;
        $this->metadata = array_merge([
            'name' => $name,
            'description' => '',
        ], $metadata);
    }

    /**
     * Get the prompt content.
     *
     * @return string The prompt content
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Process the prompt with the given variables.
     *
     * @param array $variables The variables to interpolate into the prompt
     * @return string The processed prompt
     */
    public function process(array $variables = []): string
    {
        // Replace variables in the format {{variable_name}}
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($variables) {
            $key = trim($matches[1]);
            return $variables[$key] ?? $matches[0];
        }, $this->content);
    }

    /**
     * Get the prompt metadata.
     *
     * @return array The metadata for the prompt
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set the prompt content.
     *
     * @param string $content The prompt content
     * @return $this
     */
    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set the prompt metadata.
     *
     * @param array $metadata The metadata for the prompt
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
     * @param string $key The metadata key
     * @param mixed $value The metadata value
     * @return $this
     */
    public function setMetadataValue(string $key, $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }
}

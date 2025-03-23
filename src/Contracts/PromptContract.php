<?php

namespace ElliottLawson\LaravelMcp\Contracts;

/**
 * Contract for MCP prompts.
 */
interface PromptContract
{
    /**
     * Get the prompt content.
     *
     * @return string The prompt content
     */
    public function getContent(): string;

    /**
     * Process the prompt with the given variables.
     *
     * @param  array  $variables  The variables to interpolate into the prompt
     * @return string The processed prompt
     */
    public function process(array $variables = []): string;

    /**
     * Get the prompt metadata.
     *
     * @return array The metadata for the prompt
     */
    public function getMetadata(): array;
}

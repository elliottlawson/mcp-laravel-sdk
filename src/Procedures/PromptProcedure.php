<?php

namespace ElliottLawson\LaravelMcp\Procedures;

use Illuminate\Support\Facades\Log;
use ElliottLawson\LaravelMcp\Exceptions\PromptNotFoundException;

/**
 * Procedure for handling prompt-related methods.
 */
class PromptProcedure extends BaseProcedure
{
    /**
     * The name of the procedure that will be used for RPC.
     */
    public static string $name = 'prompt';

    /**
     * List all available prompts.
     */
    public function list(): array
    {
        $prompts = $this->manager->getPrompts();
        $result = [];

        foreach ($prompts as $name => $prompt) {
            $result[$name] = [
                'name' => $name,
                'metadata' => $prompt['handler'] instanceof \ElliottLawson\LaravelMcp\Contracts\PromptContract
                    ? $prompt['handler']->getMetadata()
                    : [],
            ];
        }

        return $result;
    }

    /**
     * Get a prompt by name.
     *
     * @param  string  $name  The prompt name
     * @param  array  $variables  The variables to interpolate into the prompt
     *
     * @throws PromptNotFoundException
     */
    public function get(string $name, array $variables = []): string
    {
        $prompts = $this->manager->getPrompts();

        if (!isset($prompts[$name])) {
            throw new PromptNotFoundException("Prompt '{$name}' not found");
        }

        $prompt = $prompts[$name];
        $content = $prompt['content'];
        $handler = $prompt['handler'] ?? null;

        try {
            // If the handler is a PromptContract
            if ($handler instanceof \ElliottLawson\LaravelMcp\Contracts\PromptContract) {
                return $handler->process($variables);
            }

            // If the handler is a callable
            if (is_callable($handler)) {
                return $handler($content, $variables);
            }

            // If we have a string content, process it with variables
            if (is_string($content)) {
                return $this->processPromptString($content, $variables);
            }

            // If we got here, we don't know how to handle this prompt
            throw new \InvalidArgumentException("Invalid prompt content for '{$name}'");
        } catch (\Exception $e) {
            Log::error("Error getting prompt '{$name}': " . $e->getMessage(), [
                'exception' => $e,
                'variables' => $variables,
            ]);

            throw $e;
        }
    }

    /**
     * Process a prompt string with variables.
     *
     * @param  string  $content  The prompt content
     * @param  array  $variables  The variables to interpolate
     */
    protected function processPromptString(string $content, array $variables = []): string
    {
        // Replace variables in the format {{variable_name}}
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($variables) {
            $key = trim($matches[1]);

            return $variables[$key] ?? $matches[0];
        }, $content);
    }
}

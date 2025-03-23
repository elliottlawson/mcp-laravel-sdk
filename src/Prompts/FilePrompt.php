<?php

namespace ElliottLawson\LaravelMcp\Prompts;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Prompt implementation that loads content from a file.
 */
class FilePrompt extends BasePrompt
{
    /**
     * The file path.
     */
    protected string $filePath;

    /**
     * Create a new file prompt instance.
     *
     * @param string $name The prompt name
     * @param string $filePath The path to the prompt file
     * @param array $metadata Additional metadata for the prompt
     */
    public function __construct(string $name, string $filePath, array $metadata = [])
    {
        $this->filePath = $filePath;
        
        // Load content from file
        $content = $this->loadContent();
        
        parent::__construct($name, $content, $metadata);
    }

    /**
     * Load content from the file.
     *
     * @return string The prompt content
     */
    protected function loadContent(): string
    {
        try {
            if (!File::exists($this->filePath)) {
                Log::warning("Prompt file not found: {$this->filePath}");
                return '';
            }
            
            return File::get($this->filePath);
        } catch (\Exception $e) {
            Log::error("Error loading prompt file: {$this->filePath}", [
                'exception' => $e,
            ]);
            
            return '';
        }
    }

    /**
     * Reload content from the file.
     *
     * @return $this
     */
    public function reload(): self
    {
        $this->content = $this->loadContent();
        
        return $this;
    }

    /**
     * Get the file path.
     *
     * @return string The file path
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }
}

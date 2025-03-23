<?php

namespace ElliottLawson\LaravelMcp\Procedures;

use Sajya\Server\Procedure;
use ElliottLawson\LaravelMcp\McpManager;

/**
 * Base procedure for all MCP procedures.
 */
abstract class BaseProcedure extends Procedure
{
    /**
     * The MCP manager instance.
     */
    protected McpManager $manager;

    /**
     * Create a new procedure instance.
     */
    public function __construct(McpManager $manager)
    {
        $this->manager = $manager;
    }
}

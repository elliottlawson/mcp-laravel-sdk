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
     * Create a new procedure instance.
     */
    public function __construct(
        protected McpManager $manager
    ) {
    }
}

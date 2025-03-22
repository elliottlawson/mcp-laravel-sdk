<?php

namespace ElliottLawson\LaravelMcp\Facades;

use Illuminate\Support\Facades\Facade;

class Mcp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'mcp.manager';
    }
}

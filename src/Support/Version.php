<?php

namespace ElliottLawson\LaravelMcp\Support;

class Version
{
    const VERSION = '1.0.0';

    /**
     * Get the version of the package.
     */
    public static function get(): string
    {
        return self::VERSION;
    }
}

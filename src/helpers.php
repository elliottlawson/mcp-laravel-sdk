<?php

namespace ElliottLawson\LaravelMcp;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

/**
 * Helper functions for the Laravel MCP Server package
 */
if (!function_exists('ElliottLawson\LaravelMcp\app')) {
    /**
     * Get the available container instance.
     *
     * @param  string|null  $abstract
     * @return mixed|\Illuminate\Contracts\Foundation\Application
     */
    function app($abstract = null, array $parameters = [])
    {
        return App::make($abstract, $parameters);
    }
}

if (!function_exists('ElliottLawson\LaravelMcp\config')) {
    /**
     * Get / set the specified configuration value.
     *
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return mixed|\Illuminate\Config\Repository
     */
    function config($key = null, $default = null)
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('ElliottLawson\LaravelMcp\class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     *
     * @param  string|object  $class
     * @return string
     */
    function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return Str::afterLast($class, '\\');
    }
}

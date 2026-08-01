<?php

declare(strict_types=1);

namespace Zana\Configuration\Loaders;

use Zana\Configuration\Contracts\ConfigurationInterface;

/**
 * Loads configuration from environment variables.
 * 
 * Supports:
 * - Direct environment variable mapping
 * - Prefix-based filtering (e.g., APP_*, DB_*)
 * - Nested key conversion using double underscore (e.g., DB__CONNECTION → db.connection)
 */
class EnvironmentLoader
{
    /**
     * Load environment variables into the configuration repository.
     *
     * @param ConfigurationInterface $config The configuration repository
     * @param string|null $prefix Optional prefix to filter environment variables (e.g., 'APP_')
     * @param bool $convertNaming Convert naming conventions (e.g., DB__HOST → db.host)
     */
    public function load(
        ConfigurationInterface $config,
        ?string $prefix = null,
        bool $convertNaming = true
    ): void {
        $vars = getenv();

        if ($prefix !== null) {
            $vars = $this->filterByPrefix($vars, $prefix);
        }

        $data = [];

        foreach ($vars as $key => $value) {
            if ($convertNaming) {
                $key = $this->convertKey($key, $prefix);
            }

            // Attempt to parse JSON for complex values
            $parsed = $this->parseValue($value);

            $data[$key] = $parsed;
        }

        $config->merge($data);
    }

    /**
     * Get a single environment variable as a configuration value.
     *
     * @param string $key The environment variable name
     * @param mixed $default Default value if not set
     * @return mixed The parsed value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        return $this->parseValue($value);
    }

    /**
     * Filter environment variables by prefix.
     *
     * @param array<string, string> $vars All environment variables
     * @param string $prefix The prefix to filter by
     * @return array<string, string> Filtered variables
     */
    private function filterByPrefix(array $vars, string $prefix): array
    {
        $filtered = [];
        $prefixLen = strlen($prefix);

        foreach ($vars as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Convert environment variable name to dot-notation key.
     * 
     * Examples:
     * - APP_NAME → app.name
     * - DB_HOST → db.host
     * - DB__CONNECTION__MYSQL_HOST → db.connection.mysql.host
     *
     * @param string $key The environment variable name
     * @param string|null $prefix Optional prefix to strip
     * @return string The converted key
     */
    private function convertKey(string $key, ?string $prefix = null): string
    {
        // Strip prefix if present
        if ($prefix !== null && str_starts_with($key, $prefix)) {
            $key = substr($key, strlen($prefix));
        }

        // Split by double underscore for nested keys
        $parts = explode('__', $key);

        // Convert each part to lowercase
        $parts = array_map(function (string $part): string {
            return strtolower(
                preg_replace('/[^a-zA-Z0-9_]/', '', $part) ?? $part
            );
        }, $parts);

        return implode('.', $parts);
    }

    /**
     * Parse environment variable value.
     * 
     * Handles:
     * - Boolean strings (true/false)
     * - Null strings
     * - Numeric strings
     * - JSON arrays/objects
     *
     * @param string $value The raw environment variable value
     * @return mixed The parsed value
     */
    private function parseValue(string $value): mixed
    {
        $lower = strtolower($value);

        // Boolean values
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }

        // Null
        if ($lower === 'null') {
            return null;
        }

        // Numeric
        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
            return (int) $value;
        }

        // Try JSON
        $json = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        // String
        return $value;
    }
}


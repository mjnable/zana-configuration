<?php

declare(strict_types=1);

namespace Zana\Configuration\Contracts;

/**
 * Configuration interface providing dot-notation key access.
 * 
 * Supports hierarchical configuration from multiple sources
 * (files, environment variables, arrays) with predictable override behavior.
 */
interface ConfigurationInterface
{
    /**
     * Get a configuration value using dot notation.
     * 
     * Example: $config->get('database.default.host')
     *
     * @param string $key Dot-notation key path
     * @param mixed $default Default value if key not found
     * @return mixed The configuration value
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a configuration value using dot notation.
     *
     * @param string $key Dot-notation key path
     * @param mixed $value The value to set
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a configuration key exists.
     *
     * @param string $key Dot-notation key path
     * @return bool True if the key exists
     */
    public function has(string $key): bool;

    /**
     * Get all configuration values as a flat array.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Merge configuration values from multiple sources.
     * Later sources override earlier ones.
     *
     * @param array<string, mixed> $values Configuration values to merge
     */
    public function merge(array $values): void;
}


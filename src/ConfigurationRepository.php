<?php

declare(strict_types=1);

namespace Zana\Configuration;

use Zana\Configuration\Contracts\ConfigurationInterface;

/**
 * Configuration repository supporting dot-notation key access.
 * 
 * Manages hierarchical configuration values with merge semantics.
 * Later values override earlier ones, allowing environment-specific overrides.
 */
class ConfigurationRepository implements ConfigurationInterface
{
    /**
     * @var array<string, mixed> The raw nested configuration data
     */
    private array $items = [];

    /**
     * @param array<string, mixed> $items Initial configuration items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get a configuration value using dot notation.
     *
     * Example:
     * ```php
     * $config->get('database.connections.mysql.host', 'localhost');
     * ```
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $default;
        }

        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value using dot notation.
     * 
     * Creates intermediate keys as needed.
     */
    public function set(string $key, mixed $value): void
    {
        if ($key === '') {
            return;
        }

        $segments = explode('.', $key);
        $target = &$this->items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $target[$segment] = $value;
            } else {
                if (!isset($target[$segment]) || !is_array($target[$segment])) {
                    $target[$segment] = [];
                }
                $target = &$target[$segment];
            }
        }

        unset($target);
    }

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * Get all configuration values as a nested array.
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Merge configuration values from multiple sources.
     * Later sources override earlier ones using array_replace_recursive.
     *
     * @param array<string, mixed> $values Configuration values to merge
     */
    public function merge(array $values): void
    {
        $this->items = array_replace_recursive($this->items, $values);
    }

    /**
     * Load configuration from an array of key-value pairs (flat or nested).
     *
     * @param array<string, mixed> $values
     */
    public function load(array $values): void
    {
        $this->merge($values);
    }
}


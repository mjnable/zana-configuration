<?php

declare(strict_types=1);

namespace Zana\Configuration\Loaders;

use Zana\Configuration\Contracts\ConfigurationInterface;

/**
 * Loads configuration from PHP arrays.
 * 
 * Useful for default configurations, hardcoded settings,
 * and programmatic configuration definitions.
 */
class ArrayLoader
{
    /**
     * Load configuration from an array into the repository.
     *
     * @param ConfigurationInterface $config The configuration repository
     * @param array<string, mixed> $data The configuration data to load
     * @param string|null $prefix Optional dot-notation prefix (namespace)
     */
    public function load(ConfigurationInterface $config, array $data, ?string $prefix = null): void
    {
        if ($prefix !== null) {
            $prefixed = [];
            foreach ($data as $key => $value) {
                $prefixed["{$prefix}.{$key}"] = $value;
            }
            $config->merge($prefixed);
        } else {
            $config->merge($data);
        }
    }
}


<?php

declare(strict_types=1);

namespace Zana\Configuration\Loaders;

use Zana\Configuration\Contracts\ConfigurationInterface;
use Zana\Configuration\Exceptions\ConfigurationException;

/**
 * Loads configuration from PHP files that return arrays.
 * 
 * Supports loading from a directory of configuration files,
 * where each filename (without .php extension) becomes a top-level key.
 * 
 * Example:
 * ```php
 * // config/database.php returns ['default' => 'mysql', 'connections' => [...]]
 * // Results in config key: database.default, database.connections.mysql.host, etc.
 * ```
 */
class FileLoader
{
    /**
     * @var bool Whether to use caching for loaded configurations
     */
    private bool $useCache = false;

    /**
     * @var array<string, array> Cache of loaded configuration files
     */
    private array $cache = [];

    /**
     * Enable or disable caching of loaded configuration files.
     */
    public function setUseCache(bool $useCache): void
    {
        $this->useCache = $useCache;
    }

    /**
     * Load configuration from a directory of PHP config files.
     *
     * @param ConfigurationInterface $config The configuration repository
     * @param string $directory Path to the configuration directory
     * @throws ConfigurationException If the directory does not exist
     */
    public function loadDirectory(ConfigurationInterface $config, string $directory): void
    {
        if (!is_dir($directory)) {
            throw new ConfigurationException("Configuration directory not found: {$directory}");
        }

        $files = glob($directory . '/*.php');

        if ($files === false || $files === []) {
            throw new ConfigurationException("No configuration files found in: {$directory}");
        }

        // Sort files for deterministic loading order
        sort($files);

        foreach ($files as $file) {
            $this->loadFile($config, $file);
        }
    }

    /**
     * Load configuration from a single PHP file.
     *
     * @param ConfigurationInterface $config The configuration repository
     * @param string $file Path to the configuration file
     * @throws ConfigurationException If the file does not exist or does not return an array
     */
    public function loadFile(ConfigurationInterface $config, string $file): void
    {
        if (!file_exists($file)) {
            throw new ConfigurationException("Configuration file not found: {$file}");
        }

        // Check cache
        if ($this->useCache && isset($this->cache[$file])) {
            $config->merge($this->cache[$file]);
            return;
        }

        $data = require $file;

        if (!is_array($data)) {
            throw new ConfigurationException(
                "Configuration file '{$file}' must return an array."
            );
        }

        // Use filename (without .php) as the top-level key
        $basename = pathinfo($file, PATHINFO_FILENAME);

        $prefixed = [$basename => $data];

        if ($this->useCache) {
            $this->cache[$file] = $prefixed;
        }

        $config->merge($prefixed);
    }
}


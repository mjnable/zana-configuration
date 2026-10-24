<?php

declare(strict_types=1);

namespace Zana\Configuration;

/**
 * Read-only access to a configuration tree, addressed with dot notation.
 */
interface ConfigurationInterface
{
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Whether the key exists, even with a null value.
     */
    public function has(string $key): bool;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * @throws ConfigurationException If missing without default, or not a string
     */
    public function string(string $key, ?string $default = null): string;

    /**
     * Accepts numeric strings such as "8080".
     *
     * @throws ConfigurationException If missing without default, or not an int
     */
    public function int(string $key, ?int $default = null): int;

    /**
     * Accepts ints and numeric strings.
     *
     * @throws ConfigurationException If missing without default, or not a number
     */
    public function float(string $key, ?float $default = null): float;

    /**
     * @throws ConfigurationException If missing without default, or not a bool
     */
    public function bool(string $key, ?bool $default = null): bool;

    /**
     * @param array<array-key, mixed>|null $default
     * @return array<array-key, mixed>
     * @throws ConfigurationException If missing without default, or not an array
     */
    public function array(string $key, ?array $default = null): array;

    /**
     * The configuration under a key, or an empty one if the key is missing.
     */
    public function section(string $prefix): ConfigurationInterface;
}

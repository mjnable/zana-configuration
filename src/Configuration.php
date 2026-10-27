<?php

declare(strict_types=1);

namespace Zana\Configuration;

/**
 * An immutable configuration tree. Build one with ConfigurationBuilder.
 */
final readonly class Configuration implements ConfigurationInterface
{
    private const MISSING = "\0missing";

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private array $values = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->lookup($key);

        return $value === self::MISSING ? $default : $value;
    }

    public function has(string $key): bool
    {
        return $this->lookup($key) !== self::MISSING;
    }

    public function all(): array
    {
        return $this->values;
    }

    /**
     * A string, or an int as text (an unquoted APP_* value made of digits).
     * A float is refused: its text may have lost digits.
     */
    public function string(string $key, ?string $default = null): string
    {
        $value = $this->required($key, $default);

        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            default => throw ConfigurationException::type($key, 'a string', $value),
        };
    }

    public function int(string $key, ?int $default = null): int
    {
        $value = $this->required($key, $default);

        return match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => throw ConfigurationException::type($key, 'an int', $value),
        };
    }

    public function float(string $key, ?float $default = null): float
    {
        $value = $this->required($key, $default);

        return match (true) {
            is_float($value), is_int($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => throw ConfigurationException::type($key, 'a float', $value),
        };
    }

    /**
     * A bool, or the text "true", "false", "1" or "0" (any case): a quoted
     * APP_* value stays text (FE-42).
     */
    public function bool(string $key, ?bool $default = null): bool
    {
        $value = $this->required($key, $default);

        return match (true) {
            is_bool($value) => $value,
            is_string($value) && in_array(strtolower($value), ['true', '1'], true) => true,
            is_string($value) && in_array(strtolower($value), ['false', '0'], true) => false,
            default => throw ConfigurationException::type($key, 'a bool', $value),
        };
    }

    public function array(string $key, ?array $default = null): array
    {
        $value = $this->required($key, $default);

        return is_array($value) ? $value : throw ConfigurationException::type($key, 'an array', $value);
    }

    public function section(string $prefix): ConfigurationInterface
    {
        $value = $this->get($prefix);

        /** @var array<string, mixed> $section */
        $section = is_array($value) ? $value : [];

        return new self($section);
    }

    private function required(string $key, mixed $default): mixed
    {
        $value = $this->lookup($key);
        if ($value !== self::MISSING) {
            return $value;
        }

        return $default ?? throw ConfigurationException::missing($key);
    }

    private function lookup(string $key): mixed
    {
        $current = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return self::MISSING;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}

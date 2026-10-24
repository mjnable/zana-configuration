<?php

declare(strict_types=1);

namespace Zana\Configuration;

/**
 * Thrown for missing or mistyped configuration values, invalid files and malformed .env lines.
 */
final class ConfigurationException extends \RuntimeException
{
    public static function missing(string $key): self
    {
        return new self("Configuration key \"{$key}\" is not set.");
    }

    public static function type(string $key, string $expected, mixed $actual): self
    {
        return new self(sprintf('Configuration key "%s" should be %s, got %s.', $key, $expected, get_debug_type($actual)));
    }

    public static function invalidFile(string $path, string $reason): self
    {
        return new self("Configuration file {$path} {$reason}.");
    }

    public static function dotEnvSyntax(string $path, int $line, string $reason): self
    {
        return new self("Invalid .env syntax in {$path} on line {$line}: {$reason}.");
    }
}

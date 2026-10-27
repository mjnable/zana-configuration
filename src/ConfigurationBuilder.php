<?php

declare(strict_types=1);

namespace Zana\Configuration;

/**
 * Merges configuration sources in the order they are added; later sources win.
 *
 * Associative arrays merge recursively; lists and scalars are replaced whole.
 */
final class ConfigurationBuilder
{
    /** @var list<array<string, mixed>> */
    private array $sources = [];

    /**
     * @param array<array-key, mixed> $values
     */
    public function addArray(array $values, ?string $prefix = null): self
    {
        $this->sources[] = $prefix === null || $prefix === '' ? self::stringKeys($values) : self::nest($prefix, $values);

        return $this;
    }

    /**
     * Add a PHP file returning an array, under the file name unless another prefix is given.
     *
     * @throws ConfigurationException If the file is missing or does not return an array
     */
    public function addFile(string $path, ?string $prefix = null): self
    {
        if (!is_file($path)) {
            throw ConfigurationException::invalidFile($path, 'does not exist');
        }

        $values = (static fn (string $file): mixed => require $file)($path);
        if (!is_array($values)) {
            throw ConfigurationException::invalidFile($path, 'must return an array, got ' . get_debug_type($values));
        }

        return $this->addArray($values, $prefix ?? pathinfo($path, PATHINFO_FILENAME));
    }

    /**
     * Add every *.php file of the directory (sorted), each under its file name.
     * A missing directory adds nothing.
     *
     * @param list<string> $except File names (without extension) to skip
     */
    public function addDirectory(string $directory, array $except = []): self
    {
        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            if (!in_array(pathinfo($file, PATHINFO_FILENAME), $except, true)) {
                $this->addFile($file);
            }
        }

        return $this;
    }

    /**
     * Add environment variables starting with the prefix: APP_DATABASE__HOST
     * becomes database.host. Values are converted (see convert()), except
     * those DotEnv loaded from a quoted value, which stay strings (FE-42).
     */
    public function addEnvironment(string $prefix = 'APP_', string $separator = '__'): self
    {
        $variables = array_merge(getenv(), array_filter($_ENV, 'is_string'));

        $values = [];
        foreach ($variables as $name => $raw) {
            if (!is_string($name) || !str_starts_with($name, $prefix) || strlen($name) === strlen($prefix)) {
                continue;
            }

            $path = strtolower(str_replace($separator, '.', substr($name, strlen($prefix))));
            $raw = (string) $raw;
            $values = self::merge($values, self::nest($path, DotEnv::isQuoted($name, $raw) ? $raw : self::convert($raw)));
        }

        $this->sources[] = $values;

        return $this;
    }

    public function build(): Configuration
    {
        $merged = [];
        foreach ($this->sources as $source) {
            $merged = self::merge($merged, $source);
        }

        return new Configuration($merged);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $existing = $base[$key] ?? null;
            $bothMaps = is_array($existing) && is_array($value)
                && !array_is_list($existing) && !array_is_list($value);

            /** @var array<string, mixed> $existing */
            /** @var array<string, mixed> $value */
            $base[$key] = $bothMaps ? self::merge($existing, $value) : $value;
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private static function nest(string $path, mixed $value): array
    {
        foreach (array_reverse(explode('.', $path)) as $segment) {
            $value = [$segment => $value];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private static function stringKeys(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * An unquoted environment value: "true", "false" and "null" (any case)
     * become their constants, integers and decimals become numbers. Values
     * that are text even when made of digits stay strings (FE-42): a leading
     * "+" (+243812345678), a leading zero (007, 0812), and integers longer
     * than 15 digits, which a float would round.
     */
    private static function convert(string $raw): mixed
    {
        return match (true) {
            strcasecmp($raw, 'true') === 0 => true,
            strcasecmp($raw, 'false') === 0 => false,
            strcasecmp($raw, 'null') === 0 => null,
            str_starts_with($raw, '+'),
            preg_match('/^-?0\d/', $raw) === 1,
            preg_match('/^-?\d{16,}$/', $raw) === 1,
            preg_match('/^-?\d+(\.\d+)?([eE][-+]?\d+)?$/', $raw) !== 1 => $raw,
            preg_match('/^-?\d+$/', $raw) === 1 => (int) $raw,
            default => (float) $raw,
        };
    }
}

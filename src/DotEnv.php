<?php

declare(strict_types=1);

namespace Zana\Configuration;

/**
 * Loads KEY=VALUE files into the environment ($_ENV, $_SERVER and putenv()).
 *
 * Supports comments, blank lines, an "export " prefix, single-quoted values
 * (literal), double-quoted values (escapes and ${VAR} interpolation) and
 * unquoted values (${VAR} interpolation, " #" starts a comment).
 *
 * A quoted value is text (FE-42): the variables set from quoted values are
 * remembered, and ConfigurationBuilder::addEnvironment() keeps them strings
 * instead of converting "+243812345678" or "123456" to numbers.
 */
final class DotEnv
{
    private const NAME = '[A-Za-z_][A-Za-z0-9_.]*';

    /** @var array<string, string> Variables this process set from quoted values, with those values */
    private static array $quoted = [];

    /**
     * Load a file. Existing variables are kept unless $override is true.
     *
     * @return array<string, string> The variables the file defines
     * @throws ConfigurationException On a syntax error, or a missing file when $required
     */
    public static function load(string $path, bool $override = false, bool $required = false): array
    {
        if (!is_file($path)) {
            if ($required) {
                throw ConfigurationException::invalidFile($path, 'does not exist');
            }

            return [];
        }

        $quoted = [];
        $variables = self::parseLines((string) file_get_contents($path), $path, $quoted);

        foreach ($variables as $name => $value) {
            if (!$override && (getenv($name) !== false || array_key_exists($name, $_ENV))) {
                continue;
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            if (isset($quoted[$name])) {
                self::$quoted[$name] = $value;
            } else {
                unset(self::$quoted[$name]);
            }
        }

        return $variables;
    }

    /**
     * Whether a variable holds the value this process loaded for it from a quoted
     * .env value: text, never converted to a number, bool or null.
     */
    public static function isQuoted(string $name, string $value): bool
    {
        return (self::$quoted[$name] ?? null) === $value;
    }

    /**
     * @return array<string, string>
     * @throws ConfigurationException On a syntax error
     */
    public static function parse(string $contents, string $source = '.env'): array
    {
        $quoted = [];

        return self::parseLines($contents, $source, $quoted);
    }

    /**
     * @param array<string, true> $quoted Receives the names of the quoted values
     * @return array<string, string>
     * @throws ConfigurationException On a syntax error
     */
    private static function parseLines(string $contents, string $source, array &$quoted): array
    {
        $variables = [];
        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            if (preg_match('/^(' . self::NAME . ')\s*=\s*(.*)$/', $line, $match) !== 1) {
                throw ConfigurationException::dotEnvSyntax($source, $number, 'expected NAME=value');
            }

            $variables[$match[1]] = self::value($match[2], $variables, $source, $number);
            if ($match[2] !== '' && ($match[2][0] === "'" || $match[2][0] === '"')) {
                $quoted[$match[1]] = true;
            } else {
                unset($quoted[$match[1]]);
            }
        }

        return $variables;
    }

    /**
     * @param array<string, string> $defined Variables defined earlier in the same file
     */
    private static function value(string $raw, array $defined, string $source, int $line): string
    {
        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];

        if ($quote === "'") {
            $end = strpos($raw, "'", 1);
            if ($end === false) {
                throw ConfigurationException::dotEnvSyntax($source, $line, 'unclosed single quote');
            }
            self::assertOnlyComment(substr($raw, $end + 1), $source, $line);

            return substr($raw, 1, $end - 1);
        }

        if ($quote === '"') {
            $value = '';
            $length = strlen($raw);
            for ($i = 1; $i < $length; $i++) {
                $char = $raw[$i];
                if ($char === '\\' && $i + 1 < $length) {
                    $next = $raw[++$i];
                    $value .= match ($next) {
                        'n' => "\n",
                        't' => "\t",
                        'r' => "\r",
                        '"' => '"',
                        '\\' => '\\',
                        '$' => "\0dollar",
                        default => '\\' . $next,
                    };
                    continue;
                }
                if ($char === '"') {
                    self::assertOnlyComment(substr($raw, $i + 1), $source, $line);

                    return str_replace("\0dollar", '$', self::interpolate($value, $defined));
                }
                $value .= $char;
            }

            throw ConfigurationException::dotEnvSyntax($source, $line, 'unclosed double quote');
        }

        $comment = strpos($raw, ' #');
        if ($comment !== false) {
            $raw = substr($raw, 0, $comment);
        }

        return self::interpolate(rtrim($raw), $defined);
    }

    /**
     * @param array<string, string> $defined
     */
    private static function interpolate(string $value, array $defined): string
    {
        return (string) preg_replace_callback(
            '/\$\{(' . self::NAME . ')\}/',
            static function (array $match) use ($defined): string {
                $name = $match[1];
                if (array_key_exists($name, $defined)) {
                    return $defined[$name];
                }
                $env = $_ENV[$name] ?? getenv($name);

                return is_string($env) ? $env : '';
            },
            $value
        );
    }

    private static function assertOnlyComment(string $rest, string $source, int $line): void
    {
        $rest = trim($rest);
        if ($rest !== '' && !str_starts_with($rest, '#')) {
            throw ConfigurationException::dotEnvSyntax($source, $line, 'unexpected characters after the closing quote');
        }
    }
}

<?php

declare(strict_types=1);

namespace Zana\Configuration;

use Zana\Container\Contracts\ParameterValueProviderInterface;
use Zana\Container\ResolutionContext;

/**
 * Supplies #[Config] parameters and properties from a configuration.
 *
 * A missing key falls back to the declared default, then to null for a
 * nullable target. The container converts int and string values for backed enums.
 */
final readonly class ConfigValueProvider implements ParameterValueProviderInterface
{
    public function __construct(private ConfigurationInterface $configuration)
    {
    }

    public function supports(\ReflectionParameter|\ReflectionProperty $target): bool
    {
        return $target->getAttributes(Config::class) !== [];
    }

    public function provide(\ReflectionParameter|\ReflectionProperty $target, ResolutionContext $context): mixed
    {
        $key = $target->getAttributes(Config::class)[0]->newInstance()->key;

        if ($this->configuration->has($key)) {
            return $this->typed($key, $target->getType());
        }

        if ($target instanceof \ReflectionParameter && $target->isDefaultValueAvailable()) {
            return $target->getDefaultValue();
        }

        if ($target instanceof \ReflectionProperty && $target->hasDefaultValue()) {
            return $target->getDefaultValue();
        }

        if ($target->getType()?->allowsNull() ?? true) {
            return null;
        }

        throw ConfigurationException::missing($key);
    }

    /**
     * The value through the typed reader of a scalar target type, so a quoted
     * APP_* value (text) fills an int, float or bool parameter, and an unquoted
     * number fills a string one (FE-42). Other types get the value as stored.
     */
    private function typed(string $key, ?\ReflectionType $type): mixed
    {
        $value = $this->configuration->get($key);
        if (!$type instanceof \ReflectionNamedType || !$type->isBuiltin() || ($value === null && $type->allowsNull())) {
            return $value;
        }

        return match ($type->getName()) {
            'string' => $this->configuration->string($key),
            'int' => $this->configuration->int($key),
            'float' => $this->configuration->float($key),
            'bool' => $this->configuration->bool($key),
            default => $value,
        };
    }
}

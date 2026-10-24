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
            return $this->configuration->get($key);
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
}

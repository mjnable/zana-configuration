<?php

declare(strict_types=1);

namespace Zana\Configuration;

use Attribute;
use Zana\Container\Attribute\ValueAttribute;

/**
 * Inject a configuration value. Needs ConfigValueProvider to be registered.
 *
 *     public function __construct(#[Config('database.default')] private string $connection) {}
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Config implements ValueAttribute
{
    public function __construct(public readonly string $key)
    {
    }
}

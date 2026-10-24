<?php

declare(strict_types=1);

namespace Zana\Configuration\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Configuration\Config;
use Zana\Configuration\Configuration;
use Zana\Configuration\ConfigurationException;
use Zana\Configuration\ConfigValueProvider;
use Zana\Container\Exception\ContainerBuildException;
use Zana\Container\ServiceCollection;

enum Driver: string
{
    case Sqlite = 'sqlite';
    case Mysql = 'mysql';
}

final class DatabaseSettings
{
    #[Config('database.timeout')]
    public int $timeout = 5;

    public function __construct(
        #[Config('database.host')] public readonly string $host,
        #[Config('database.driver')] public readonly Driver $driver,
        #[Config('database.port')] public readonly int $port = 3306,
        #[Config('database.socket')] public readonly ?string $socket = null,
    ) {
    }
}

final class NeedsMissingSetting
{
    public function __construct(#[Config('database.password')] public readonly string $password)
    {
    }
}

final class ConfigInjectionTest extends TestCase
{
    public function testValuesDefaultsNullablesAndEnumsAreInjected(): void
    {
        $settings = $this->container(['database' => ['host' => 'db', 'driver' => 'mysql', 'timeout' => 30]])
            ->get(DatabaseSettings::class);

        $this->assertSame(['db', Driver::Mysql, 3306, null, 30], [$settings->host, $settings->driver, $settings->port, $settings->socket, $settings->timeout]);
    }

    public function testPropertyDefaultsAreKeptWhenTheKeyIsMissing(): void
    {
        $settings = $this->container(['database' => ['host' => 'db', 'driver' => 'sqlite']])->get(DatabaseSettings::class);

        $this->assertSame(5, $settings->timeout);
    }

    public function testMissingRequiredValuesAreReported(): void
    {
        $container = (new ServiceCollection())
            ->addParameterProvider(new ConfigValueProvider(new Configuration()))
            ->transient(NeedsMissingSetting::class)
            ->build();

        try {
            $container->get(NeedsMissingSetting::class);
            $this->fail('A missing required value must be reported.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ConfigurationException::class, $e->getPrevious());
            $this->assertStringContainsString('Configuration key "database.password" is not set.', $e->getMessage());
        }
    }

    public function testConfigNeedsTheProvider(): void
    {
        $this->expectException(ContainerBuildException::class);
        $this->expectExceptionMessage('has #[Config] but no registered parameter value provider supports it');

        (new ServiceCollection())->transient(DatabaseSettings::class)->build();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function container(array $values): \Zana\Container\Contracts\ContainerInterface
    {
        return (new ServiceCollection())
            ->addParameterProvider(new ConfigValueProvider(new Configuration($values)))
            ->transient(DatabaseSettings::class)
            ->build();
    }
}

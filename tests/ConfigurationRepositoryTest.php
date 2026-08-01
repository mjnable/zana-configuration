<?php

declare(strict_types=1);

namespace Zana\Configuration\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Configuration\ConfigurationRepository;
use Zana\Configuration\Contracts\ConfigurationInterface;

class ConfigurationRepositoryTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $config = new ConfigurationRepository();
        $this->assertInstanceOf(ConfigurationInterface::class, $config);
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $config = new ConfigurationRepository();
        $this->assertNull($config->get('missing'));
        $this->assertEquals('fallback', $config->get('missing', 'fallback'));
    }

    public function testGetWithNestedKeys(): void
    {
        $config = new ConfigurationRepository([
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => ['host' => '127.0.0.1', 'port' => 3306],
                ],
            ],
        ]);

        $this->assertEquals('mysql', $config->get('database.default'));
        $this->assertEquals('127.0.0.1', $config->get('database.connections.mysql.host'));
        $this->assertEquals(3306, $config->get('database.connections.mysql.port'));
    }

    public function testSetWithDotNotation(): void
    {
        $config = new ConfigurationRepository();
        $config->set('app.name', 'Zana');
        $config->set('app.debug', true);

        $this->assertEquals('Zana', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
        $this->assertTrue($config->has('app.name'));
        $this->assertTrue($config->has('app.debug'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $config = new ConfigurationRepository(['app' => ['name' => 'Old']]);
        $config->set('app.name', 'New');

        $this->assertEquals('New', $config->get('app.name'));
    }

    public function testHas(): void
    {
        $config = new ConfigurationRepository(['foo' => 'bar', 'nested' => ['deep' => 42]]);

        $this->assertTrue($config->has('foo'));
        $this->assertTrue($config->has('nested.deep'));
        $this->assertFalse($config->has('missing'));
        $this->assertFalse($config->has('nested.missing'));
    }

    public function testAll(): void
    {
        $config = new ConfigurationRepository(['foo' => 'bar', 'nested' => ['deep' => 42]]);

        $all = $config->all();
        $this->assertEquals('bar', $all['foo']);
        $this->assertEquals(['deep' => 42], $all['nested']);
    }

    public function testMerge(): void
    {
        $config = new ConfigurationRepository([
            'app' => ['name' => 'Zana', 'debug' => false],
        ]);

        $config->merge([
            'app' => ['debug' => true, 'version' => '2.0'],
        ]);

        $this->assertEquals('Zana', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
        $this->assertEquals('2.0', $config->get('app.version'));
    }

    public function testEmptyKeyReturnsDefault(): void
    {
        $config = new ConfigurationRepository(['foo' => 'bar']);
        $this->assertEquals('default', $config->get('', 'default'));
    }

    public function testSetEmptyKeyIsNoOp(): void
    {
        $config = new ConfigurationRepository(['foo' => 'bar']);
        $config->set('', 'value');

        $this->assertEquals(['foo' => 'bar'], $config->all());
    }
}


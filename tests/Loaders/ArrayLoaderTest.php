<?php

declare(strict_types=1);

namespace Zana\Configuration\Tests\Loaders;

use PHPUnit\Framework\TestCase;
use Zana\Configuration\ConfigurationRepository;
use Zana\Configuration\Loaders\ArrayLoader;

class ArrayLoaderTest extends TestCase
{
    private ConfigurationRepository $config;
    private ArrayLoader $loader;

    protected function setUp(): void
    {
        $this->config = new ConfigurationRepository();
        $this->loader = new ArrayLoader();
    }

    public function testLoadWithoutPrefix(): void
    {
        $this->loader->load($this->config, [
            'app' => ['name' => 'Zana'],
            'debug' => true,
        ]);

        $this->assertEquals('Zana', $this->config->get('app.name'));
        $this->assertTrue($this->config->get('debug'));
    }

    public function testLoadWithPrefix(): void
    {
        $this->loader->load($this->config, [
            'name' => 'Zana',
            'debug' => true,
        ], 'app');

        $this->assertEquals('Zana', $this->config->get('app.name'));
        $this->assertTrue($this->config->get('app.debug'));
        $this->assertFalse($this->config->has('name'));
    }

    public function testLoadWithNestedValuesAndPrefix(): void
    {
        $this->loader->load($this->config, [
            'connections' => ['mysql' => ['host' => 'localhost']],
        ], 'database');

        $this->assertEquals('localhost', $this->config->get('database.connections.mysql.host'));
    }
}


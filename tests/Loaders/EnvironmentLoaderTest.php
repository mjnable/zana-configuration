<?php

declare(strict_types=1);

namespace Zana\Configuration\Tests\Loaders;

use PHPUnit\Framework\TestCase;
use Zana\Configuration\ConfigurationRepository;
use Zana\Configuration\Loaders\EnvironmentLoader;

class EnvironmentLoaderTest extends TestCase
{
    private ConfigurationRepository $config;
    private EnvironmentLoader $loader;

    protected function setUp(): void
    {
        $this->config = new ConfigurationRepository();
        $this->loader = new EnvironmentLoader();
    }

    public function testGetReturnsParsedValue(): void
    {
        putenv('ZANA_TEST_STRING=hello');
        putenv('ZANA_TEST_INT=42');
        putenv('ZANA_TEST_FLOAT=3.14');
        putenv('ZANA_TEST_BOOL_TRUE=true');
        putenv('ZANA_TEST_BOOL_FALSE=false');
        putenv('ZANA_TEST_NULL=null');

        $this->assertEquals('hello', $this->loader->get('ZANA_TEST_STRING'));
        $this->assertEquals(42, $this->loader->get('ZANA_TEST_INT'));
        $this->assertEquals(3.14, $this->loader->get('ZANA_TEST_FLOAT'));
        $this->assertTrue($this->loader->get('ZANA_TEST_BOOL_TRUE'));
        $this->assertFalse($this->loader->get('ZANA_TEST_BOOL_FALSE'));
        $this->assertNull($this->loader->get('ZANA_TEST_NULL'));

        // Clean up
        putenv('ZANA_TEST_STRING');
        putenv('ZANA_TEST_INT');
        putenv('ZANA_TEST_FLOAT');
        putenv('ZANA_TEST_BOOL_TRUE');
        putenv('ZANA_TEST_BOOL_FALSE');
        putenv('ZANA_TEST_NULL');
    }

    public function testGetReturnsDefaultWhenNotSet(): void
    {
        $this->assertEquals('default', $this->loader->get('ZANA_DOES_NOT_EXIST', 'default'));
    }

    public function testLoadWithoutPrefix(): void
    {
        putenv('ZANA_LOAD_TEST_ONE=value1');
        putenv('ZANA_LOAD_TEST_TWO=2');

        $this->loader->load($this->config, null, false);

        // Verify the environment variables are now in config
        $this->assertEquals('value1', $this->config->get('zana_load_test_one'));
        $this->assertEquals(2, $this->config->get('zana_load_test_two'));

        putenv('ZANA_LOAD_TEST_ONE');
        putenv('ZANA_LOAD_TEST_TWO');
    }

    public function testLoadWithPrefixFilter(): void
    {
        putenv('ZANA_PREFIXED_HOST=localhost');
        putenv('ZANA_PREFIXED_PORT=3306');
        putenv('ZANA_UNRELATED=value');

        $this->loader->load($this->config, 'ZANA_PREFIXED_');

        $this->assertEquals('localhost', $this->config->get('host'));
        $this->assertEquals(3306, $this->config->get('port'));
        $this->assertFalse($this->config->has('unrelated'));

        putenv('ZANA_PREFIXED_HOST');
        putenv('ZANA_PREFIXED_PORT');
        putenv('ZANA_UNRELATED');
    }

    public function testConvertKeyWithDoubleUnderscore(): void
    {
        putenv('ZANA_DB__CONNECTION__MYSQL_HOST=localhost');

        $this->loader->load($this->config, 'ZANA_');

        $this->assertEquals('localhost', $this->config->get('db.connection.mysql.host'));

        putenv('ZANA_DB__CONNECTION__MYSQL_HOST');
    }
}


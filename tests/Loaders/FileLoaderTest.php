<?php

declare(strict_types=1);

namespace Zana\Configuration\Tests\Loaders;

use PHPUnit\Framework\TestCase;
use Zana\Configuration\ConfigurationRepository;
use Zana\Configuration\Loaders\FileLoader;
use Zana\Configuration\Exceptions\ConfigurationException;

class FileLoaderTest extends TestCase
{
    private string $tmpDir;
    private ConfigurationRepository $config;
    private FileLoader $loader;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/zana_config_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        $this->config = new ConfigurationRepository();
        $this->loader = new FileLoader();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    private function writeConfigFile(string $filename, array $data): string
    {
        $path = $this->tmpDir . '/' . $filename;
        $content = '<?php return ' . var_export($data, true) . ';';
        file_put_contents($path, $content);
        return $path;
    }

    public function testLoadFile(): void
    {
        $file = $this->writeConfigFile('app.php', ['name' => 'Zana', 'debug' => true]);

        $this->loader->loadFile($this->config, $file);

        $this->assertEquals('Zana', $this->config->get('app.name'));
        $this->assertTrue($this->config->get('app.debug'));
    }

    public function testLoadFileThrowsWhenFileDoesNotExist(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->loader->loadFile($this->config, $this->tmpDir . '/missing.php');
    }

    public function testLoadFileThrowsWhenFileDoesNotReturnArray(): void
    {
        $path = $this->tmpDir . '/bad.php';
        file_put_contents($path, '<?php return "not an array";');

        $this->expectException(ConfigurationException::class);

        $this->loader->loadFile($this->config, $path);
    }

    public function testLoadDirectory(): void
    {
        $this->writeConfigFile('app.php', ['name' => 'Zana']);
        $this->writeConfigFile('database.php', ['default' => 'mysql']);

        $this->loader->loadDirectory($this->config, $this->tmpDir);

        $this->assertEquals('Zana', $this->config->get('app.name'));
        $this->assertEquals('mysql', $this->config->get('database.default'));
    }

    public function testLoadDirectoryThrowsWhenDirectoryDoesNotExist(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->loader->loadDirectory($this->config, $this->tmpDir . '/nonexistent');
    }

    public function testLoadDirectoryThrowsWhenNoPhpFiles(): void
    {
        $emptyDir = $this->tmpDir . '/empty';
        mkdir($emptyDir);

        $this->expectException(ConfigurationException::class);

        $this->loader->loadDirectory($this->config, $emptyDir);

        rmdir($emptyDir);
    }

    public function testCaching(): void
    {
        $file = $this->writeConfigFile('app.php', ['name' => 'Zana']);

        $this->loader->setUseCache(true);
        $this->loader->loadFile($this->config, $file);

        // Modify the file content
        $this->writeConfigFile('app.php', ['name' => 'Changed']);

        // Cache should return the original value
        $config2 = new ConfigurationRepository();
        $this->loader->loadFile($config2, $file);

        $this->assertEquals('Zana', $config2->get('app.name'));
    }
}


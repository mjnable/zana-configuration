<?php

declare(strict_types=1);

namespace Zana\Configuration\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Zana\Configuration\Configuration;
use Zana\Configuration\ConfigurationBuilder;
use Zana\Configuration\ConfigurationCache;
use Zana\Configuration\ConfigurationException;

final class ConfigurationTest extends TestCase
{
    private ?TempDirectory $temp = null;

    #[After]
    public function cleanUp(): void
    {
        $this->temp?->remove();
        foreach (['APP_DATABASE__HOST', 'APP_DATABASE__PORT', 'APP_DEBUG', 'APP_RATIO', 'APP_MISSING', 'APP_', 'OTHER_VALUE'] as $name) {
            putenv($name);
            unset($_ENV[$name]);
        }
    }

    // ── access ─────────────────────────────────────────────────────────────

    public function testDotNotationReadsNestedValues(): void
    {
        $config = new Configuration(['database' => ['connections' => ['main' => ['host' => 'db', 'port' => null]]]]);

        $this->assertSame('db', $config->get('database.connections.main.host'));
        $this->assertSame('fallback', $config->get('database.connections.other.host', 'fallback'));
        $this->assertTrue($config->has('database.connections.main.port'), 'null values exist');
        $this->assertFalse($config->has('database.connections.main.host.deeper'));
        $this->assertNull($config->get('database.connections.main.port', 'default'), 'a stored null is returned');
    }

    public function testTypedGetters(): void
    {
        $config = new Configuration(['s' => 'text', 'i' => 8, 'is' => '8080', 'f' => 1.5, 'fi' => 3, 'fs' => '2.5', 'b' => false, 'a' => [1]]);

        $this->assertSame('text', $config->string('s'));
        $this->assertSame(8, $config->int('i'));
        $this->assertSame(8080, $config->int('is'));
        $this->assertSame(1.5, $config->float('f'));
        $this->assertSame(3.0, $config->float('fi'));
        $this->assertSame(2.5, $config->float('fs'));
        $this->assertFalse($config->bool('b'));
        $this->assertSame([1], $config->array('a'));
        $this->assertSame('dflt', $config->string('missing', 'dflt'));
        $this->assertSame(1, $config->int('missing', 1));
        $this->assertTrue($config->bool('missing', true));
    }

    public function testTypedGettersRejectMissingKeysAndWrongTypes(): void
    {
        $config = new Configuration(['s' => 'text', 'i' => 8, 'f' => 1.5]);

        foreach ([
            [static fn () => $config->string('missing'), 'Configuration key "missing" is not set.'],
            [static fn () => $config->int('s'), 'Configuration key "s" should be an int, got string.'],
            [static fn () => $config->string('f'), 'should be a string, got float'],
            [static fn () => $config->bool('i'), 'should be a bool'],
            [static fn () => $config->array('i'), 'should be an array'],
            [static fn () => $config->float('s'), 'should be a float'],
        ] as [$call, $message]) {
            try {
                $call();
                $this->fail("Expected: {$message}");
            } catch (ConfigurationException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testSections(): void
    {
        $config = new Configuration(['database' => ['host' => 'db'], 'name' => 'app']);

        $this->assertSame('db', $config->section('database')->get('host'));
        $this->assertSame([], $config->section('missing')->all());
        $this->assertSame([], $config->section('name')->all(), 'a scalar is not a section');
    }

    // ── building ───────────────────────────────────────────────────────────

    public function testLaterSourcesWinAndMapsMergeRecursively(): void
    {
        $config = (new ConfigurationBuilder())
            ->addArray(['database' => ['host' => 'localhost', 'port' => 3306, 'options' => ['a', 'b']], 'debug' => true])
            ->addArray(['database' => ['host' => 'db', 'options' => ['c']]])
            ->addArray(['strict' => true], 'database.mode')
            ->build();

        $this->assertSame([
            'database' => ['host' => 'db', 'port' => 3306, 'options' => ['c'], 'mode' => ['strict' => true]],
            'debug' => true,
        ], $config->all());
    }

    public function testFilesAndDirectories(): void
    {
        $this->temp = new TempDirectory();
        $this->temp->file('config/app.php', "<?php return ['name' => 'Zana', 'debug' => false];");
        $this->temp->file('config/database.php', "<?php return ['default' => 'sqlite'];");
        $this->temp->file('config/modules.php', "<?php return ['Skipped'];");
        $extra = $this->temp->file('extra.php', "<?php return ['key' => 'value'];");

        $config = (new ConfigurationBuilder())
            ->addDirectory($this->temp->path . '/config', except: ['modules'])
            ->addDirectory($this->temp->path . '/missing')
            ->addFile($extra, 'custom')
            ->build();

        $this->assertSame(['app', 'database', 'custom'], array_keys($config->all()));
        $this->assertSame('Zana', $config->get('app.name'));
        $this->assertSame('value', $config->get('custom.key'));
    }

    public function testInvalidFilesAreReported(): void
    {
        $this->temp = new TempDirectory();
        $bad = $this->temp->file('bad.php', '<?php return "nope";');

        try {
            (new ConfigurationBuilder())->addFile($bad);
            $this->fail('A file that does not return an array must be rejected.');
        } catch (ConfigurationException $e) {
            $this->assertStringContainsString('must return an array, got string', $e->getMessage());
        }

        $this->expectExceptionMessage('does not exist');
        (new ConfigurationBuilder())->addFile($this->temp->path . '/none.php');
    }

    public function testEnvironmentVariablesAreMappedAndConverted(): void
    {
        putenv('APP_DATABASE__HOST=db.internal');
        $_ENV['APP_DATABASE__PORT'] = '5432';
        putenv('APP_DEBUG=TRUE');
        $_ENV['APP_RATIO'] = '0.25';
        putenv('APP_MISSING=null');
        putenv('OTHER_VALUE=ignored');

        $config = (new ConfigurationBuilder())
            ->addArray(['database' => ['host' => 'localhost', 'driver' => 'pgsql']])
            ->addEnvironment()
            ->build();

        $this->assertSame(['host' => 'db.internal', 'driver' => 'pgsql', 'port' => 5432], $config->get('database'));
        $this->assertTrue($config->get('debug'));
        $this->assertSame(0.25, $config->get('ratio'));
        $this->assertTrue($config->has('missing'));
        $this->assertNull($config->get('missing'));
        $this->assertFalse($config->has('other_value'));
    }

    public function testEnvironmentWinsOverProcessVariables(): void
    {
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'true';

        $this->assertTrue((new ConfigurationBuilder())->addEnvironment()->build()->get('debug'));
    }

    // ── cache ──────────────────────────────────────────────────────────────

    public function testCacheRoundTrip(): void
    {
        $this->temp = new TempDirectory();
        $path = $this->temp->path . '/cache/config.php';
        $config = new Configuration(['app' => ['name' => 'Zana', 'ratio' => 1.5, 'tags' => ['a']], 'debug' => null]);

        $this->assertNull(ConfigurationCache::load($path));

        ConfigurationCache::write($path, $config);

        $this->assertEquals($config, ConfigurationCache::load($path));
        $this->assertSame([], glob($this->temp->path . '/cache/*.tmp') ?: [], 'no temporary file is left');
    }

    public function testCacheRejectsForeignFiles(): void
    {
        $this->temp = new TempDirectory();

        $this->expectException(ConfigurationException::class);
        ConfigurationCache::load($this->temp->file('cache.php', '<?php return 42;'));
    }

    // ── signed cache (2.36) ────────────────────────────────────────────────

    private const string KEY = 'a-key-for-the-configuration-cache-0123456789';

    public function testASignedCacheRoundTrips(): void
    {
        $this->temp = new TempDirectory();
        $path = $this->temp->path . '/cache/config.php';
        $config = new Configuration(['app' => ['name' => 'Zana', 'tags' => ['a']], 'debug' => null]);

        ConfigurationCache::write($path, $config, self::KEY);

        $this->assertMatchesRegularExpression('/\A<\?php\n\/\/ zana-signature: [0-9a-f]{64}\n/', (string) file_get_contents($path));
        $this->assertEquals($config, ConfigurationCache::load($path, self::KEY));
    }

    public function testCodeAddedToASignedCacheDoesNotRun(): void
    {
        $path = $this->signedCache();
        file_put_contents($path, preg_replace('/^(return )/m', "\$GLOBALS['zana_planted'] = true;\n\$1", (string) file_get_contents($path), 1));
        unset($GLOBALS['zana_planted']);

        try {
            ConfigurationCache::load($path, self::KEY);
            $this->fail('loaded');
        } catch (ConfigurationException $e) {
            $this->assertStringContainsString('does not carry the signature of this key', $e->getMessage());
        }
        $this->assertArrayNotHasKey('zana_planted', $GLOBALS);
    }

    public function testAnUnsignedCacheIsNotLoadedWithAKey(): void
    {
        $this->temp = new TempDirectory();
        $path = $this->temp->path . '/config.php';
        ConfigurationCache::write($path, new Configuration(['a' => 1]));
        file_put_contents($path, preg_replace('/^(return )/m', "\$GLOBALS['zana_planted'] = true;\n\$1", (string) file_get_contents($path), 1));
        unset($GLOBALS['zana_planted']);

        $this->assertNull(ConfigurationCache::load($path, self::KEY), 'built and written again by the caller');
        $this->assertArrayNotHasKey('zana_planted', $GLOBALS);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forgeries(): iterable
    {
        yield 'another key' => ['key'];
        yield 'one character of the signature changed' => ['changed'];
        yield 'the file cut inside the signature line' => ['cut'];
        yield 'the signature in capitals' => ['upper'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forgeries')]
    public function testForgedSignaturesAreRefused(string $forgery): void
    {
        $path = $this->signedCache();
        $source = (string) file_get_contents($path);
        if (preg_match('/\A<\?php\n\/\/ zana-signature: ([0-9a-f]{64})\n/', $source, $m) !== 1) {
            $this->fail('not signed');
        }
        $mac = $m[1];
        $key = self::KEY;
        match ($forgery) {
            'key' => $key = 'another-key-for-the-configuration-cache-0123',
            'changed' => file_put_contents($path, str_replace($mac, ($mac[0] === 'a' ? 'b' : 'a') . substr($mac, 1), $source)),
            'cut' => file_put_contents($path, "<?php\n// zana-signature: " . $mac),
            default => file_put_contents($path, str_replace($mac, strtoupper($mac), $source)),
        };

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('does not carry the signature of this key');
        ConfigurationCache::load($path, $key);
    }

    public function testTheBytesCheckedAreTheBytesThatRun(): void
    {
        $this->temp = new TempDirectory();
        $path = $this->temp->path . '/config.php';
        $body = "\nreturn ['file' => __FILE__];\n";
        file_put_contents($path, "<?php\n// zana-signature: " . hash_hmac('sha256', 'zana-configuration-cache|' . $body, self::KEY) . "\n" . $body);

        $config = ConfigurationCache::load($path, self::KEY);

        $this->assertNotNull($config);
        $this->assertStringContainsString("eval()'d code", $config->string('file'), 'evaluated, not included after the check');
    }

    public function testAnEmptyKeyIsRefused(): void
    {
        $this->temp = new TempDirectory();

        $this->expectException(\InvalidArgumentException::class);
        ConfigurationCache::write($this->temp->path . '/config.php', new Configuration([]), '');
    }

    private function signedCache(): string
    {
        $this->temp = new TempDirectory();
        $path = $this->temp->path . '/config.php';
        ConfigurationCache::write($path, new Configuration(['app' => ['name' => 'Zana']]), self::KEY);

        return $path;
    }
}

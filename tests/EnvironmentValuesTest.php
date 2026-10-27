<?php

declare(strict_types=1);

namespace Zana\Configuration\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zana\Configuration\Config;
use Zana\Configuration\Configuration;
use Zana\Configuration\ConfigurationBuilder;
use Zana\Configuration\ConfigValueProvider;
use Zana\Configuration\DotEnv;
use Zana\Container\ServiceCollection;

final class ContactSettings
{
    public function __construct(
        #[Config('fe42.whatsapp')] public readonly string $whatsapp,
        #[Config('fe42.port')] public readonly int $port,
        #[Config('fe42.debug')] public readonly bool $debug,
        #[Config('fe42.pin')] public readonly string $pin,
    ) {
    }
}

/**
 * FE-42: a quoted .env value is text; unquoted values convert, except those
 * that are text even when made of digits.
 */
final class EnvironmentValuesTest extends TestCase
{
    private ?TempDirectory $temp = null;

    /** @var list<string> */
    private array $names = [];

    #[After]
    public function cleanUp(): void
    {
        $this->temp?->remove();
        foreach ($this->names as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    public function testQuotedValuesStayText(): void
    {
        $this->load(<<<'ENV'
            APP_FE42__WHATSAPP="+243812345678"
            APP_FE42__PASSWORD='123456'
            APP_FE42__FLAG="true"
            APP_FE42__NOTHING='null'
            APP_FE42__ACCOUNT="12345678901234567890"
            ENV);

        $config = (new ConfigurationBuilder())->addEnvironment()->build();

        $this->assertSame('+243812345678', $config->get('fe42.whatsapp'));
        $this->assertSame('123456', $config->get('fe42.password'));
        $this->assertSame('true', $config->get('fe42.flag'));
        $this->assertSame('null', $config->get('fe42.nothing'));
        $this->assertSame('12345678901234567890', $config->get('fe42.account'));
        $this->assertSame('+243812345678', $config->string('fe42.whatsapp'), 'reading it no longer stops the application');
        $this->assertTrue($config->bool('fe42.flag'), 'a quoted bool still reads as a bool');
        $this->assertSame(123456, $config->int('fe42.password'), 'a quoted number still reads as an int');
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function unquotedValues(): iterable
    {
        yield 'true' => ['TRUE', true];
        yield 'false' => ['false', false];
        yield 'null' => ['Null', null];
        yield 'integer' => ['8080', 8080];
        yield 'negative integer' => ['-42', -42];
        yield 'zero' => ['0', 0];
        yield 'decimal' => ['0.5', 0.5];
        yield 'exponent' => ['2e3', 2000.0];
        yield 'phone number' => ['+243812345678', '+243812345678'];
        yield 'leading zero' => ['007', '007'];
        yield 'local phone number' => ['0812345678', '0812345678'];
        yield '15 digits' => ['123456789012345', 123456789012345];
        yield '16 digits' => ['1234567890123456', '1234567890123456'];
        yield 'text' => ['db.internal', 'db.internal'];
        yield 'digits and letters' => ['123abc', '123abc'];
    }

    #[DataProvider('unquotedValues')]
    public function testUnquotedValuesConvertUnlessTheyAreText(string $raw, mixed $expected): void
    {
        $this->load("APP_FE42__VALUE={$raw}");

        $this->assertSame($expected, (new ConfigurationBuilder())->addEnvironment()->build()->get('fe42.value'));
    }

    public function testServerVariablesAreNeverTakenForQuotedOnes(): void
    {
        $this->load('APP_FE42__PORT="8080"');
        $this->assertSame('8080', (new ConfigurationBuilder())->addEnvironment()->build()->get('fe42.port'));

        putenv('APP_FE42__PORT=9090');   // changed by the server, not by the quoted line
        $_ENV['APP_FE42__PORT'] = '9090';
        $this->assertSame(9090, (new ConfigurationBuilder())->addEnvironment()->build()->get('fe42.port'));
    }

    public function testStringAcceptsIntsButNotFloats(): void
    {
        $config = new Configuration(['pin' => 1234, 'ratio' => 0.5]);

        $this->assertSame('1234', $config->string('pin'));
        $this->expectExceptionMessage('should be a string, got float');
        $config->string('ratio');
    }

    public function testInjectionConvertsToTheDeclaredType(): void
    {
        $this->load(<<<'ENV'
            APP_FE42__WHATSAPP="+243812345678"
            APP_FE42__PORT="3306"
            APP_FE42__DEBUG="false"
            APP_FE42__PIN=1234
            ENV);

        $settings = (new ServiceCollection())
            ->addParameterProvider(new ConfigValueProvider((new ConfigurationBuilder())->addEnvironment()->build()))
            ->transient(ContactSettings::class)
            ->build()
            ->get(ContactSettings::class);
        assert($settings instanceof ContactSettings);

        $this->assertSame(['+243812345678', 3306, false, '1234'], [$settings->whatsapp, $settings->port, $settings->debug, $settings->pin]);
    }

    private function load(string $contents): void
    {
        $this->temp ??= new TempDirectory();
        $file = $this->temp->file('.env', $contents);
        foreach (array_keys(DotEnv::parse($contents)) as $name) {
            $this->names[] = $name;
        }
        DotEnv::load($file, override: true);
    }
}

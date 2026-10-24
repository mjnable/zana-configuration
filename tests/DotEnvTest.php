<?php

declare(strict_types=1);

namespace Zana\Configuration\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Zana\Configuration\ConfigurationException;
use Zana\Configuration\DotEnv;

final class DotEnvTest extends TestCase
{
    private const NAMES = ['ZANA_DOTENV_A', 'ZANA_DOTENV_B', 'ZANA_DOTENV_EXISTING', 'ZANA_DOTENV_HOME'];

    private ?TempDirectory $temp = null;

    #[After]
    public function cleanUp(): void
    {
        $this->temp?->remove();
        foreach (self::NAMES as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    public function testGrammar(): void
    {
        $_ENV['ZANA_DOTENV_HOME'] = '/home/ada';

        $variables = DotEnv::parse(<<<'ENV'
            # comment line

            PLAIN=value
            SPACED = trimmed value   # inline comment
            HASH=a#b
            EMPTY=
            export EXPORTED=yes
            SINGLE='literal ${PLAIN} \n'   # comment
            DOUBLE="line\nbreak \"quoted\" \\ ${PLAIN} \$PLAIN"
            REF=${PLAIN}-${ZANA_DOTENV_HOME}-${UNDEFINED}
            dotted.name=1
            ENV);

        $this->assertSame([
            'PLAIN' => 'value',
            'SPACED' => 'trimmed value',
            'HASH' => 'a#b',
            'EMPTY' => '',
            'EXPORTED' => 'yes',
            'SINGLE' => 'literal ${PLAIN} \n',
            'DOUBLE' => "line\nbreak \"quoted\" \\ value \$PLAIN",
            'REF' => 'value-/home/ada-',
            'dotted.name' => '1',
        ], $variables);
    }

    public function testSyntaxErrorsReportTheLine(): void
    {
        foreach ([
            "OK=1\nnot a line" => 'on line 2: expected NAME=value',
            "A='open" => 'unclosed single quote',
            'A="open' => 'unclosed double quote',
            'A="closed" trailing' => 'unexpected characters after the closing quote',
            '1ABC=x' => 'expected NAME=value',
        ] as $contents => $message) {
            try {
                DotEnv::parse($contents, 'test.env');
                $this->fail("Expected: {$message}");
            } catch (ConfigurationException $e) {
                $this->assertStringContainsString('Invalid .env syntax in test.env', $e->getMessage());
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testLoadSetsVariablesWithoutOverridingExistingOnes(): void
    {
        $this->temp = new TempDirectory();
        $file = $this->temp->file('.env', "ZANA_DOTENV_A=from-file\nZANA_DOTENV_EXISTING=from-file\n");
        putenv('ZANA_DOTENV_EXISTING=real');

        $loaded = DotEnv::load($file);

        $this->assertSame(['ZANA_DOTENV_A' => 'from-file', 'ZANA_DOTENV_EXISTING' => 'from-file'], $loaded);
        $this->assertSame('from-file', getenv('ZANA_DOTENV_A'));
        $this->assertSame('from-file', $_ENV['ZANA_DOTENV_A']);
        $this->assertSame('from-file', $_SERVER['ZANA_DOTENV_A']);
        $this->assertSame('real', getenv('ZANA_DOTENV_EXISTING'));
    }

    public function testOverrideReplacesExistingVariables(): void
    {
        $this->temp = new TempDirectory();
        $file = $this->temp->file('.env', 'ZANA_DOTENV_EXISTING=from-file');
        $_ENV['ZANA_DOTENV_EXISTING'] = 'real';

        DotEnv::load($file, override: true);

        $this->assertSame('from-file', $_ENV['ZANA_DOTENV_EXISTING']);
    }

    public function testMissingFiles(): void
    {
        $this->assertSame([], DotEnv::load('/definitely/missing/.env'));

        $this->expectException(ConfigurationException::class);
        DotEnv::load('/definitely/missing/.env', required: true);
    }
}

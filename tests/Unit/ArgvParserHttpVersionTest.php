<?php

declare(strict_types=1);

namespace Eleload\Tests\Unit;

use Eleload\Cli\ArgvParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for --http-version option parsing in ArgvParser::parseRun().
 */
final class ArgvParserHttpVersionTest extends TestCase
{
    private ArgvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ArgvParser();
    }

    public function testDefaultHttpVersionIs20(): void
    {
        $options = $this->parser->parseRun(['https://example.com']);
        self::assertSame('2.0', $options->httpVersion);
    }

    /** @return list<array{string}> */
    public static function validVersionProvider(): array
    {
        return [['1.0'], ['1.1'], ['2.0'], ['3.0']];
    }

    #[DataProvider('validVersionProvider')]
    public function testValidHttpVersionAccepted(string $version): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--http-version', $version]);
        self::assertSame($version, $options->httpVersion);
    }

    public function testInvalidHttpVersionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/--http-version/');
        $this->parser->parseRun(['https://example.com', '--http-version', '2.5']);
    }

    public function testHttpVersionEqualsSyntax(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--http-version=1.1']);
        self::assertSame('1.1', $options->httpVersion);
    }
}

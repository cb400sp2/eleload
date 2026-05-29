<?php

declare(strict_types=1);

namespace Eleload\Tests\Unit;

use Eleload\Cli\ArgvParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for --dns-cache-ttl option parsing in ArgvParser::parseRun().
 */
final class ArgvParserDnsCacheTtlTest extends TestCase
{
    private ArgvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ArgvParser();
    }

    public function testDefaultDnsCacheTtlIsNegativeOne(): void
    {
        $options = $this->parser->parseRun(['https://example.com']);
        self::assertSame(-1, $options->dnsCacheTtl);
    }

    public function testDnsCacheTtlZeroDisablesCache(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--dns-cache-ttl', '0']);
        self::assertSame(0, $options->dnsCacheTtl);
    }

    public function testDnsCacheTtlPositiveValue(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--dns-cache-ttl', '60']);
        self::assertSame(60, $options->dnsCacheTtl);
    }

    public function testDnsCacheTtlEqualsSyntax(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--dns-cache-ttl=30']);
        self::assertSame(30, $options->dnsCacheTtl);
    }

    /** @return list<array{string}> */
    public static function invalidValueProvider(): array
    {
        return [['-1'], ['-5'], ['abc'], ['1.5']];
    }

    #[DataProvider('invalidValueProvider')]
    public function testInvalidDnsCacheTtlThrows(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/--dns-cache-ttl/');
        $this->parser->parseRun(['https://example.com', '--dns-cache-ttl', $value]);
    }
}

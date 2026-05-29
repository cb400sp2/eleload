<?php

declare(strict_types=1);

namespace Eleload\Tests\Unit;

use Eleload\Cli\ArgvParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for --max-connections and --tcp-keepalive option parsing.
 */
final class ArgvParserConnectionPoolTest extends TestCase
{
    private ArgvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ArgvParser();
    }

    public function testDefaultMaxConnectionsIsZero(): void
    {
        $options = $this->parser->parseRun(['https://example.com']);
        self::assertSame(0, $options->maxConnections);
    }

    public function testDefaultTcpKeepaliveSecIs60(): void
    {
        $options = $this->parser->parseRun(['https://example.com']);
        self::assertSame(60, $options->tcpKeepaliveSec);
    }

    public function testMaxConnectionsCanBeSet(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--max-connections=10']);
        self::assertSame(10, $options->maxConnections);
    }

    public function testMaxConnectionsCanBeZero(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--max-connections=0']);
        self::assertSame(0, $options->maxConnections);
    }

    public function testTcpKeepaliveSecCanBeSet(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--tcp-keepalive=30']);
        self::assertSame(30, $options->tcpKeepaliveSec);
    }

    public function testTcpKeepaliveSecCanBeZeroToDisable(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--tcp-keepalive=0']);
        self::assertSame(0, $options->tcpKeepaliveSec);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidMaxConnectionsProvider(): array
    {
        return [
            'negative'  => ['--max-connections=-1', 'max-connections'],
            'non-digit' => ['--max-connections=abc', 'max-connections'],
            'float'     => ['--max-connections=1.5', 'max-connections'],
            'empty'     => ['--max-connections=', 'max-connections'],
        ];
    }

    #[DataProvider('invalidMaxConnectionsProvider')]
    public function testInvalidMaxConnectionsThrows(string $flag, string $expectedName): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/max-connections/');
        $this->parser->parseRun(['https://example.com', $flag]);
    }

    /** @return array<string, array{string}> */
    public static function invalidTcpKeepaliveProvider(): array
    {
        return [
            'negative'  => ['--tcp-keepalive=-1'],
            'non-digit' => ['--tcp-keepalive=abc'],
            'float'     => ['--tcp-keepalive=1.5'],
            'empty'     => ['--tcp-keepalive='],
        ];
    }

    #[DataProvider('invalidTcpKeepaliveProvider')]
    public function testInvalidTcpKeepaliveThrows(string $flag): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tcp-keepalive/');
        $this->parser->parseRun(['https://example.com', $flag]);
    }
}

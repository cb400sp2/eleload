<?php

declare(strict_types=1);

namespace Eleload\Tests\Unit;

use Eleload\Cli\ArgvParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for --accept-encoding and --no-decompress option parsing.
 */
final class ArgvParserEncodingTest extends TestCase
{
    private ArgvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ArgvParser();
    }

    public function testDefaultAcceptEncodingIsGzip(): void
    {
        $options = $this->parser->parseRun(['https://example.com']);
        self::assertSame('gzip', $options->acceptEncoding);
    }

    public function testDefaultNoDecompressIsFalse(): void
    {
        $options = $this->parser->parseRun(['https://example.com']);
        self::assertFalse($options->noDecompress);
    }

    /** @return list<array{string}> */
    public static function validEncodingProvider(): array
    {
        return [['none'], ['gzip'], ['br'], ['deflate']];
    }

    #[DataProvider('validEncodingProvider')]
    public function testValidAcceptEncodingAccepted(string $encoding): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--accept-encoding', $encoding]);
        self::assertSame($encoding, $options->acceptEncoding);
    }

    public function testAcceptEncodingEqualsSyntax(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--accept-encoding=br']);
        self::assertSame('br', $options->acceptEncoding);
    }

    public function testInvalidAcceptEncodingThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/--accept-encoding/');
        $this->parser->parseRun(['https://example.com', '--accept-encoding', 'zstd']);
    }

    public function testNoDecompressFlagSetsTrue(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--no-decompress']);
        self::assertTrue($options->noDecompress);
    }

    public function testNoDecompressAndAcceptEncodingCanCoexist(): void
    {
        $options = $this->parser->parseRun(['https://example.com', '--no-decompress', '--accept-encoding', 'br']);
        self::assertTrue($options->noDecompress);
        self::assertSame('br', $options->acceptEncoding);
    }
}

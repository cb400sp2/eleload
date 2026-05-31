<?php

declare(strict_types=1);

use Eleload\Cli\ArgvParser;
use Eleload\LoadTesting\GrpcFramer;

test('GrpcFramer encodes and decodes framed payloads', function (): void {
    $payload = "\x0a\x03abc";
    $framed = GrpcFramer::encode($payload);

    assertSame($payload, GrpcFramer::decode($framed));
    assertFalse(GrpcFramer::isCompressed($framed));
});



test('GrpcFramer rejects short frames', function (): void {
    assertThrows(
        fn () => GrpcFramer::decode("\x00\x00\x00"),
        InvalidArgumentException::class
    );
});

test('ArgvParser parses --grpc=package.Service/Method', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseRun([
        'https://example.com',
        '--grpc=helloworld.Greeter/SayHello',
    ]);

    assertSame('helloworld.Greeter/SayHello', $options->grpcMethod);
});

test('ArgvParser rejects invalid --grpc syntax', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun([
            'https://example.com',
            '--grpc=bad-format',
        ]),
        InvalidArgumentException::class
    );
});

// End of gRPC tests.

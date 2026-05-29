<?php

declare(strict_types=1);

use Eleload\LoadTesting\RequestOptions;

test('RequestOptions resolveHeaders adds bearer authorization when not provided', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 1,
        concurrency: 1,
        method: 'GET',
        timeout: 10,
        headers: ['Accept: application/json'],
        bearerToken: 'token-123'
    );

    assertSame(
        ['Accept: application/json', 'Authorization: Bearer token-123'],
        $options->resolveHeaders()
    );
});

test('RequestOptions resolveHeaders does not duplicate authorization header', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 1,
        concurrency: 1,
        method: 'GET',
        timeout: 10,
        headers: ['Authorization: Basic xyz', 'Accept: application/json'],
        bearerToken: 'token-123'
    );

    assertSame(
        ['Authorization: Basic xyz', 'Accept: application/json'],
        $options->resolveHeaders()
    );
});

test('RequestOptions resolveHeaders returns raw headers when bearer token is absent', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 1,
        concurrency: 1,
        method: 'GET',
        timeout: 10,
        headers: ['Accept: application/json']
    );

    assertSame(['Accept: application/json'], $options->resolveHeaders());
});

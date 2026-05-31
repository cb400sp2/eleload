<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

/**
 * Encodes and decodes gRPC length-prefixed message frames.
 *
 * Wire format (per gRPC spec):
 *   - 1 byte:  compressed flag (0x00 = not compressed, 0x01 = compressed)
 *   - 4 bytes: unsigned big-endian message length
 *   - N bytes: message payload (protobuf bytes)
 *
 * @see https://grpc.io/docs/what-is-grpc/core-concepts/#protocol-buffers
 */
final class GrpcFramer
{
    private const COMPRESSED_FLAG = 0x01;
    private const FRAME_HEADER_BYTES = 5;

    /**
     * Wrap raw protobuf bytes in a gRPC length-prefixed frame (uncompressed).
     */
    public static function encode(string $bytes): string
    {
        return pack('CN', 0, strlen($bytes)) . $bytes;
    }

    /**
     * Strip the 5-byte gRPC frame header and return the message payload.
     *
     * @throws \InvalidArgumentException if the frame is too short or declares compression.
     */
    public static function decode(string $framed): string
    {
        if (strlen($framed) < self::FRAME_HEADER_BYTES) {
            throw new \InvalidArgumentException(
                sprintf(
                    'gRPC frame too short: expected at least %d bytes, got %d.',
                    self::FRAME_HEADER_BYTES,
                    strlen($framed)
                )
            );
        }

        /** @var array{flag: int, len: int} $header */
        $header = unpack('Cflag/Nlen', $framed);

        $messageLength = $header['len'];
        $available = strlen($framed) - self::FRAME_HEADER_BYTES;

        if ($available < $messageLength) {
            throw new \InvalidArgumentException(
                sprintf(
                    'gRPC frame body truncated: declared length %d, available %d bytes.',
                    $messageLength,
                    $available
                )
            );
        }

        return substr($framed, self::FRAME_HEADER_BYTES, $messageLength);
    }

    /**
     * Return true when the compressed flag byte is set in the given frame.
     */
    public static function isCompressed(string $framed): bool
    {
        if (strlen($framed) < 1) {
            return false;
        }
        return (ord($framed[0]) & self::COMPRESSED_FLAG) !== 0;
    }
}

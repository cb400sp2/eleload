<?php

declare(strict_types=1);

namespace Eleload\Cli;

final class CompareOptions
{
    public function __construct(
        public readonly string $beforeJsonPath,
        public readonly string $afterJsonPath,
        public readonly ?string $htmlPath,
        public readonly ?string $markdownPath
    ) {
    }
}


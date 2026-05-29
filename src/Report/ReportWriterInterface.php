<?php

declare(strict_types=1);

namespace Eleload\Report;

interface ReportWriterInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function write(array $data, string $path): void;
}

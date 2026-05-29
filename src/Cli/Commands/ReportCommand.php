<?php

declare(strict_types=1);

namespace Eleload\Cli\Commands;

use Eleload\Cli\ArgvParser;
use Eleload\Cli\ConsoleOutput;
use Eleload\Cli\Support\JsonFileReader;
use Eleload\Report\HtmlReporter;

final class ReportCommand
{
    /**
     * @param list<string> $args
     */
    public function execute(array $args, ConsoleOutput $output): int
    {
        $parser = new ArgvParser();
        $options = $parser->parseReport($args);
        $htmlReporter = new HtmlReporter(__DIR__ . '/../../../templates/report.php');

        $report = JsonFileReader::readObject($options->jsonPath);

        $htmlReporter->write($report, $options->htmlPath);
        $output->writeln('HTML report: ' . $options->htmlPath);

        return 0;
    }
}

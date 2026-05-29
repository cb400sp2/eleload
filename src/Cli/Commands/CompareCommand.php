<?php

declare(strict_types=1);

namespace Eleload\Cli\Commands;

use Eleload\Cli\ArgvParser;
use Eleload\Cli\ConsoleOutput;
use Eleload\Cli\Support\JsonFileReader;
use Eleload\Compare\ReportComparator;
use Eleload\Report\CompareMarkdownReporter;
use Eleload\Report\HtmlReporter;

final class CompareCommand
{
    /**
     * @param list<string> $args
     */
    public function execute(array $args, ConsoleOutput $output): int
    {
        $parser = new ArgvParser();
        $options = $parser->parseCompare($args);

        $beforeReport = JsonFileReader::readObject($options->beforeJsonPath);
        $afterReport = JsonFileReader::readObject($options->afterJsonPath);

        $comparison = (new ReportComparator())->compare($beforeReport, $afterReport);

        if ($options->htmlPath !== null) {
            $htmlReporter = new HtmlReporter(__DIR__ . '/../../../templates/compare.php');
            $htmlReporter->write($comparison, $options->htmlPath);
            $output->writeln('HTML comparison report: ' . $options->htmlPath);
        }

        if ($options->markdownPath !== null) {
            $markdownReporter = new CompareMarkdownReporter();
            $markdownReporter->write($comparison, $options->markdownPath);
            $output->writeln('Markdown comparison report: ' . $options->markdownPath);
        }

        return 0;
    }
}

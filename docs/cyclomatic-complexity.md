# Cyclomatic Complexity Report

Measured with [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)
`Generic.Metrics.CyclomaticComplexity` sniff.

## Thresholds

| CC range | Meaning | CI status |
|---|---|---|
| 1–10 | Acceptable | — |
| 11–20 | Warning – consider refactoring | warning |
| > 20 | Error – refactoring strongly recommended | error (non-blocking) |

Run locally:

```bash
composer complexity
```

## Current Hotspots (as of 2026-05-31)

| File | Method | CC | Priority |
|---|---|---|---|
| `src/Cli/ArgvParser.php` | `parseRun()` | 88 | 🔴 High |
| `src/LoadTesting/ScenarioRunner.php` | `run()` | 46 | 🔴 High |
| `src/Cli/Commands/RunCommand.php` | `execute()` | 46 | 🔴 High |
| `src/Report/ConsoleReporter.php` | `render()` | 39 | 🔴 High |
| `src/LoadTesting/ScenarioLoader.php` | `parseDefinition()` | 35 | 🔴 High |
| `src/Metrics/StatisticsCalculator.php` | `summarize()` | 29 | 🟠 Medium |
| `src/Metrics/PrometheusPusher.php` | `buildBody()` | 24 | 🟠 Medium |
| `src/Cli/ArgvParser.php` | `parseScenario()` | 21 | 🟠 Medium |
| `src/LoadTesting/AgentRunner.php` | `run()` | 21 | 🟠 Medium |
| `src/LoadTesting/CurlMultiRunner.php` | `run()` | 21 | 🟠 Medium |
| `src/Report/MarkdownReporter.php` | `render()` | 19 | 🟡 Low |
| `src/Report/JUnitReporter.php` | `buildXml()` | 16 | 🟡 Low |
| `src/Metrics/StreamingPercentileEstimator.php` | `add()` | 14 | 🟡 Low |
| `src/LoadTesting/ScenarioLoader.php` | `parseExtract()` | 14 | 🟡 Low |

## Refactoring Guidelines

### General Principles

1. **Extract Method** – Break large methods into smaller, single-purpose helpers.
2. **Replace Conditional with Polymorphism** – Replace long `if/elseif` chains
   with strategy or command objects.
3. **Introduce Parameter Object** – Replace loosely typed `array $args` /
   `array $options` with typed value objects.
4. **Extract Class** – When a method does multiple conceptual tasks, move
   related groups of branches into dedicated classes.

### File-Specific Plans

#### `ArgvParser::parseRun()` (CC 88)

The entire `ArgvParser` class is a sequential `--flag → value` scanner.

- Extract a `CliOption` value object and a `CliOptionRegistry`.
- Move each flag's parsing logic into a dedicated `OptionParser` (one per flag
  group: concurrency, duration, rate-limit, threshold, etc.).
- `parseRun()` becomes a thin dispatcher that calls the appropriate parser.

#### `ScenarioRunner::run()` / `CurlMultiRunner::run()` (CC 46 / 21)

Both methods combine the event loop, request dispatch, response handling,
result aggregation and progress reporting.

- Extract `IterationCompleter` (handles VU iteration bookkeeping).
- Extract `ResponseHandler` (decodes curl result → `RequestResult`).
- Extract `ProgressReporter` (tracks and fires `$onProgress` callbacks).
- The main `run()` loop reduces to: tick → dispatch → handle → check-done.

#### `RunCommand::execute()` (CC 46)

The command mixes option validation, runner selection, output formatting,
baseline handling, comparison and exit-code calculation.

- Extract `RunPipeline` that receives validated `RunOptions` and returns a
  `RunReport`.
- Move baseline / comparison logic into `BaselineManager`.
- Move exit-code determination into `ExitCodeResolver`.

#### `ConsoleReporter::render()` / `MarkdownReporter::render()` (CC 39 / 19)

Both renderers contain many optional-section conditionals.

- Extract section-renderer helpers:
  `renderThroughput()`, `renderLatency()`, `renderThresholds()`, …
- Consider a `Section[]` list pattern where each section decides `canRender()`
  and `render()`.

#### `StatisticsCalculator::summarize()` (CC 29)

- Extract `LatencyStats`, `ThroughputStats`, `ErrorStats` sub-calculators.
- `summarize()` orchestrates them and merges the results.

#### `ScenarioLoader::parseDefinition()` (CC 35)

- Extract `ScenarioStepParser`, `ScenarioVariantParser`, `ScenarioHeaderParser`.
- Each parser validates and constructs one concept from the raw array.

## Tracking Progress

Re-run `composer complexity` after each refactoring.  
The CI `Cyclomatic Complexity check` step (non-blocking) shows the current
violation count in every PR. The goal is to reduce all 🔴 items to ≤ 20 and
all 🟠 items to ≤ 10.

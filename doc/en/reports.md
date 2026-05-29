# Reports

> **Also available in:** [日本語](../ja/reports.md)

`eleload` can produce reports in multiple formats after a `run` or `scenario` test.

## Output Options

### Timestamped directory (`--output-dir`)

The most convenient option for regular use. Creates three files at once:

```bash
eleload run https://example.com \
  --requests=1000 --concurrency=20 \
  --name="smoke test" \
  --output-dir=reports/
```

Output files (example):

```text
reports/eleload-20260528-165619.json
reports/eleload-20260528-165619.html
reports/eleload-20260528-165619.md
```

### Explicit file paths

```bash
eleload run https://example.com \
  --report-json=reports/run.json \
  --report-html=reports/run.html \
  --report-md=reports/run.md \
  --report-csv=reports/run.csv
```

### Console output (default)

Without any report flags, `eleload` prints a summary table to standard output.

Use `--silent` to suppress it (useful in CI when only file reports matter).

## Console Output

```text
Requests:     100
Success:      100
Failed:       0
Duration:     2.41s
RPS:          41.5
TPS:          41.5
Error rate:   0.00%
Latency min:  22ms
Latency avg:  240ms
Latency p50:  235ms
Latency p95:  480ms
Latency p99:  510ms
Latency max:  540ms
```

## HTML Report

Includes a latency distribution chart, time-series RPS graph, and a summary table.
Open the generated `.html` file in any browser.

## Markdown Report

A text-based summary suitable for pasting into GitHub issues, pull requests, or wikis.

Example structure:

```markdown
# eleload Report — smoke test

**Date**: 2026-05-28T16:56:19+00:00

## Summary

| Metric | Value |
|--------|-------|
| Requests | 1000 |
| Success | 998 |
...
```

## JSON Report

The machine-readable format. Used as input for `report` and `compare` commands.

See [json-schema.md](json-schema.md) for the full schema.

## CSV Report

A flat CSV suitable for import into spreadsheets or time-series databases.
Columns: `timestamp`, `latency_ms`, `status_code`, `success`.

## Regenerating Reports

If you saved a JSON report earlier, you can regenerate the HTML at any time:

```bash
eleload report reports/eleload-20260528-165619.json \
  --html=reports/regenerated.html
```

## Metric Definitions

| Metric | Description |
|--------|-------------|
| `RPS` | Total HTTP requests per second |
| `TPS` | Successful transactions per second |
| `TPS/RPS Rate` | `TPS / RPS × 100` |
| `RPS Achievement` | `RPS / target_rps × 100` (when `--target-rps` is set) |
| `TPS Achievement` | `TPS / target_tps × 100` (when `--target-tps` is set) |

In single-URL mode, 1 request = 1 transaction.

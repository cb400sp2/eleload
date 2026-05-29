# Compare Two Runs

> **Also available in:** [日本語](../ja/compare.md)

The `compare` command highlights metric regressions and improvements between two saved JSON reports.

## Usage

```bash
eleload compare reports/before.json reports/after.json \
  --html=reports/compare.html \
  --md=reports/compare.md
```

Both input files must be valid eleload JSON reports (see [json-schema.md](json-schema.md)).

## Options

| Option | Description |
|--------|-------------|
| `--html=FILE` | Write an HTML comparison report to FILE |
| `--md=FILE` | Write a Markdown comparison report to FILE |

At least one output option (`--html` or `--md`) must be provided.

## Output Structure

The comparison report shows the absolute and relative change for each metric:

| Metric | Before | After | Change |
|--------|--------|-------|--------|
| Requests | 1000 | 1000 | — |
| Success | 998 | 995 | -3 (-0.3%) |
| RPS | 41.5 | 38.2 | -3.3 (-7.9%) ⚠ |
| p95 latency | 480ms | 530ms | +50ms (+10.4%) ⚠ |
| p99 latency | 510ms | 610ms | +100ms (+19.6%) ⚠ |
| Error rate | 0.20% | 0.50% | +0.30% ⚠ |

Regressions are highlighted (⚠) in both HTML and Markdown formats.

## Typical CI Workflow

```bash
# Step 1: Run before the change
eleload run https://api.example.com/health \
  --requests=500 --concurrency=10 \
  --output-dir=reports/ --name="before"

# Step 2: Deploy the change, then run again
eleload run https://api.example.com/health \
  --requests=500 --concurrency=10 \
  --output-dir=reports/ --name="after"

# Step 3: Compare
eleload compare reports/before.json reports/after.json \
  --html=reports/compare.html

# Optional: attach reports as CI artifacts
```

## Related

- [reports.md](reports.md) — generating single-run reports
- [thresholds.md](thresholds.md) — automatic CI failure on threshold violations

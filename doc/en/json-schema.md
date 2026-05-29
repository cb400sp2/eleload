# JSON Report Schema

> **Also available in:** [日本語](../ja/json-schema.md)

All eleload JSON reports share a common structure and include a `meta` block for versioning.

## Schema Version Policy

`schema_version` is an integer that is incremented **only** when a breaking layout change is made
(e.g. a field is renamed, removed, or its type changes). Adding new fields is non-breaking and
does not increment the version.

## Top-level Structure

```json
{
  "meta": { ... },
  "summary": { ... },
  "latency": { ... },
  "status_codes": { ... },
  "time_buckets": [ ... ],
  "thresholds": { ... }
}
```

## `meta` block

```json
{
  "meta": {
    "tool": "eleload",
    "version": "1.0.0",
    "schema_version": 1,
    "test_name": "smoke test",
    "generated_at": "2026-05-28T16:56:19+00:00"
  }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `tool` | string | Always `"eleload"` |
| `version` | string | eleload version that produced the report |
| `schema_version` | int | Schema version (currently `1`) |
| `test_name` | string | Name from `--name` option, or empty string |
| `generated_at` | string | ISO 8601 timestamp |

## `summary` block

```json
{
  "summary": {
    "requests": 1000,
    "success": 998,
    "failed": 2,
    "duration_sec": 24.1,
    "rps": 41.5,
    "tps": 41.4,
    "error_rate_pct": 0.20
  }
}
```

## `latency` block

```json
{
  "latency": {
    "min_ms": 22,
    "avg_ms": 240,
    "p50_ms": 235,
    "p95_ms": 480,
    "p99_ms": 510,
    "max_ms": 540
  }
}
```

## `status_codes` block

```json
{
  "status_codes": {
    "200": 998,
    "503": 2
  }
}
```

Keys are HTTP status code strings; values are request counts.

## `time_buckets` array

One entry per second of the test run (after warmup):

```json
{
  "time_buckets": [
    { "elapsed_sec": 0, "rps": 42.0, "avg_latency_ms": 238 },
    { "elapsed_sec": 1, "rps": 41.8, "avg_latency_ms": 242 }
  ]
}
```

## `thresholds` block

Present only when threshold flags were used:

```json
{
  "thresholds": {
    "fail_on_p95_ms": 500,
    "fail_on_error_rate_pct": 1.0,
    "p95_passed": true,
    "error_rate_passed": true
  }
}
```

## Compatibility

When writing tools that consume eleload JSON reports, check `schema_version` first:

```php
$data = json_decode(file_get_contents('report.json'), true);
if ($data['meta']['schema_version'] !== 1) {
    throw new RuntimeException('Unsupported schema version');
}
```

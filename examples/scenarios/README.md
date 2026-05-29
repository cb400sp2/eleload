# Example Scenarios

This directory contains example scenario files for use with `eleload scenario`.
Each example is provided in both YAML (`.yaml`) and JSON (`.json`) format with identical semantics.

## Files

| File | Description |
|------|-------------|
| `simple-get.yaml` / `simple-get.json` | Single-step GET request — the simplest possible scenario |
| `login-then-fetch.yaml` / `login-then-fetch.json` | Login, extract a JWT token, then fetch a protected resource |
| `multi-step-checkout.yaml` | Multi-step e-commerce flow: search → detail → add to cart → checkout |

## Running an Example

```bash
# YAML format
eleload scenario examples/scenarios/simple-get.yaml --concurrency=5 --iterations=100

# JSON format
eleload scenario examples/scenarios/simple-get.json --concurrency=5 --iterations=100

# With duration mode
eleload scenario examples/scenarios/login-then-fetch.yaml --concurrency=10 --duration=60

# Output a report
eleload scenario examples/scenarios/multi-step-checkout.yaml \
  --concurrency=5 --iterations=50 --output-dir=reports/
```

## Scenario File Format

### Top-level fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | No | Scenario name shown in reports |
| `variables` | object | No | Initial variable values (can be overridden via step `extract`) |
| `steps` | array | Yes | List of step objects (at least one required) |

### Step fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `url` | string | Yes | Request URL; supports `{{variable}}` substitution |
| `method` | string | No | HTTP method (default: `GET`) |
| `name` | string | No | Step name shown in reports |
| `headers` | string[] | No | Additional request headers |
| `body` | string or object | No | Request body; objects are JSON-encoded |
| `timeout` | int | No | Per-step timeout in seconds (default: 10) |
| `connect_timeout` | int | No | Connection timeout in seconds |
| `wait_ms` | int | No | Milliseconds to wait after this step completes |
| `follow_redirects` | bool | No | Whether to follow HTTP redirects (default: false) |
| `extract` | object | No | Variables to extract from the response body |

### Variable extraction

The `extract` field maps variable names to extraction expressions:

- `json:$.path.to.field` — JSONPath expression (e.g. `json:$.token`, `json:$.results[0].id`)
- `regex:pattern` — Regular expression with a capture group (e.g. `regex:"id":"(\d+)"`)

Extracted variables are available in subsequent steps as `{{variable_name}}`.

## Notes

- YAML files require either the `ext-yaml` PHP extension or the `symfony/yaml` package.
- File size is limited to 10 MB.
- Only `.json`, `.yaml`, and `.yml` extensions are supported.

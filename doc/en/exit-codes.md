# Exit Codes

> **Also available in:** [日本語](../ja/exit-codes.md)

`eleload` follows standard UNIX exit code conventions.

| Code | Meaning |
|------|---------|
| `0` | Success — test completed without threshold violations |
| `1` | Failure — invalid options, threshold violation, or runtime error |
| `2` | Reserved for unrecoverable engine errors (not currently used) |

## When exit code 1 is returned

- A required argument is missing or invalid
- A URL fails scheme, format, or private-network validation
- A threshold flag condition is violated (`--fail-on-p95`, `--fail-on-error-rate`, etc.)
- A scenario file cannot be found, parsed, or has an unsupported extension
- A runtime curl error prevents the test from completing
- An unexpected exception occurs

## Checking exit code in shell

```bash
eleload run https://example.com --requests=100 --fail-on-error-rate=1
if [ $? -ne 0 ]; then
  echo "Load test failed"
  exit 1
fi
```

## GitHub Actions

GitHub Actions automatically fails a step when it returns a non-zero exit code,
so no additional `if` check is usually needed.

```yaml
- name: Load test
  run: eleload run https://api.example.com --duration=30 --fail-on-p95=500
```

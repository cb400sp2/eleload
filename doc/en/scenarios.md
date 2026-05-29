# Scenario Files

> **Also available in:** [日本語](../ja/scenarios.md)

Scenario files let you define multi-step HTTP flows with variable extraction.
Use them with the `scenario` command:

```bash
eleload scenario examples/scenarios/login-then-fetch.yaml \
  --concurrency=10 --duration=60
```

## Supported Formats

| Extension | Parser |
|-----------|--------|
| `.json` | Built-in PHP JSON parser |
| `.yaml`, `.yml` | `ext-yaml` PHP extension or `symfony/yaml` package |

For YAML support, install one of:

```bash
# Option A: PHP extension (faster)
pecl install yaml

# Option B: Composer package
composer require symfony/yaml
```

## File Structure

### JSON Example

```json
{
  "name": "Login and Fetch",
  "variables": {
    "base_url": "https://api.example.com"
  },
  "steps": [
    {
      "name": "Login",
      "url": "{{base_url}}/auth/login",
      "method": "POST",
      "headers": ["Content-Type: application/json"],
      "body": "{\"username\": \"user\", \"password\": \"pass\"}",
      "extract": {
        "token": "json:$.access_token"
      }
    },
    {
      "name": "Fetch Profile",
      "url": "{{base_url}}/users/me",
      "headers": ["Authorization: Bearer {{token}}"]
    }
  ]
}
```

### YAML Example

```yaml
name: Login and Fetch
variables:
  base_url: https://api.example.com
steps:
  - name: Login
    url: "{{base_url}}/auth/login"
    method: POST
    headers:
      - "Content-Type: application/json"
    body: '{"username": "user", "password": "pass"}'
    extract:
      token: "json:$.access_token"

  - name: Fetch Profile
    url: "{{base_url}}/users/me"
    headers:
      - "Authorization: Bearer {{token}}"
```

## Schema Reference

### Top-level Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | No | Scenario name shown in reports |
| `variables` | object | No | Initial variable values |
| `steps` | array | Yes | List of step objects (at least one required) |

### Step Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `url` | string | Yes | Request URL; supports `{{variable}}` substitution |
| `method` | string | No | HTTP method (default: `GET`) |
| `name` | string | No | Step name shown in reports |
| `headers` | string[] | No | Additional request headers |
| `body` | string or object | No | Request body |
| `timeout` | int | No | Per-step timeout in seconds (default: 10) |
| `connect_timeout` | int | No | Connection timeout in seconds |
| `wait_ms` | int | No | Milliseconds to wait after this step |
| `follow_redirects` | bool | No | Follow HTTP redirects (default: false) |
| `extract` | object | No | Variables to extract from the response body |

## Variable Substitution

Use `{{variable_name}}` in `url` and `headers` fields to insert variable values.

Initial values come from the top-level `variables` map. Steps can define new
variables (or override existing ones) via `extract`.

## Variable Extraction

The `extract` field maps variable names to extraction expressions:

| Prefix | Syntax | Example |
|--------|--------|---------|
| `json:` | JSONPath expression | `json:$.access_token` |
| `json:` | Array indexing | `json:$.results[0].id` |
| `regex:` | Regex with capture group | `regex:"id":"(\d+)"` |

Extracted variables are available in all subsequent steps within the same iteration.

## Example Files

Pre-built examples are in the `examples/scenarios/` directory:

| File | Description |
|------|-------------|
| `simple-get.yaml` / `.json` | Single GET request |
| `login-then-fetch.yaml` / `.json` | Login, extract JWT, fetch protected resource |
| `multi-step-checkout.yaml` | 4-step checkout flow (search → detail → cart → checkout) |

See [examples/scenarios/README.md](../../examples/scenarios/README.md) for full format documentation.

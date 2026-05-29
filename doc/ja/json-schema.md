# JSON レポートスキーマ

> **Also available in:** [English](../en/json-schema.md)

すべての eleload JSON レポートは共通の構造を持ち、バージョン管理のための `meta` ブロックを含みます。

## スキーマバージョンポリシー

`schema_version` は、破壊的なレイアウト変更（フィールドの名前変更・削除・型変更など）が行われた場合のみインクリメントされる整数です。
新しいフィールドの追加は非破壊的であり、バージョンはインクリメントされません。

## トップレベル構造

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

## `meta` ブロック

```json
{
  "meta": {
    "tool": "eleload",
    "version": "1.0.0",
    "schema_version": 1,
    "test_name": "スモークテスト",
    "generated_at": "2026-05-28T16:56:19+00:00"
  }
}
```

| フィールド | 型 | 説明 |
|-----------|---|------|
| `tool` | string | 常に `"eleload"` |
| `version` | string | レポートを生成した eleload のバージョン |
| `schema_version` | int | スキーマバージョン（現在は `1`） |
| `test_name` | string | `--name` オプションからの名前、または空文字列 |
| `generated_at` | string | ISO 8601 タイムスタンプ |

## `summary` ブロック

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

## `latency` ブロック

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

## `status_codes` ブロック

```json
{
  "status_codes": {
    "200": 998,
    "503": 2
  }
}
```

キーは HTTP ステータスコードの文字列、値はリクエスト数です。

## `time_buckets` 配列

テスト実行中の 1 秒ごとに 1 エントリ（ウォームアップ後）:

```json
{
  "time_buckets": [
    { "elapsed_sec": 0, "rps": 42.0, "avg_latency_ms": 238 },
    { "elapsed_sec": 1, "rps": 41.8, "avg_latency_ms": 242 }
  ]
}
```

## `thresholds` ブロック

しきい値フラグが使用された場合のみ存在します:

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

## 互換性

eleload JSON レポートを使用するツールを作成する場合は、まず `schema_version` を確認してください:

```php
$data = json_decode(file_get_contents('report.json'), true);
if ($data['meta']['schema_version'] !== 1) {
    throw new RuntimeException('サポートされていないスキーマバージョンです');
}
```

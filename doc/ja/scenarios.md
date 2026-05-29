# シナリオファイル

> **Also available in:** [English](../en/scenarios.md)

シナリオファイルを使うと、変数抽出付きのマルチステップ HTTP フローを定義できます。
`scenario` コマンドで実行します:

```bash
eleload scenario examples/scenarios/login-then-fetch.yaml \
  --concurrency=10 --duration=60
```

## 対応フォーマット

| 拡張子 | パーサー |
|--------|---------|
| `.json` | PHP 組み込み JSON パーサー |
| `.yaml`, `.yml` | `ext-yaml` PHP 拡張または `symfony/yaml` パッケージ |

YAML サポートには以下のいずれかをインストールしてください:

```bash
# オプション A: PHP 拡張（高速）
pecl install yaml

# オプション B: Composer パッケージ
composer require symfony/yaml
```

## ファイル構造

### JSON の例

```json
{
  "name": "ログインとデータ取得",
  "variables": {
    "base_url": "https://api.example.com"
  },
  "steps": [
    {
      "name": "ログイン",
      "url": "{{base_url}}/auth/login",
      "method": "POST",
      "headers": ["Content-Type: application/json"],
      "body": "{\"username\": \"user\", \"password\": \"pass\"}",
      "extract": {
        "token": "json:$.access_token"
      }
    },
    {
      "name": "プロフィール取得",
      "url": "{{base_url}}/users/me",
      "headers": ["Authorization: Bearer {{token}}"]
    }
  ]
}
```

### YAML の例

```yaml
name: ログインとデータ取得
variables:
  base_url: https://api.example.com
steps:
  - name: ログイン
    url: "{{base_url}}/auth/login"
    method: POST
    headers:
      - "Content-Type: application/json"
    body: '{"username": "user", "password": "pass"}'
    extract:
      token: "json:$.access_token"

  - name: プロフィール取得
    url: "{{base_url}}/users/me"
    headers:
      - "Authorization: Bearer {{token}}"
```

## スキーマリファレンス

### トップレベルフィールド

| フィールド | 型 | 必須 | 説明 |
|-----------|---|------|------|
| `name` | string | いいえ | レポートに表示するシナリオ名 |
| `variables` | object | いいえ | 初期変数値 |
| `steps` | array | はい | ステップオブジェクトのリスト（1 つ以上必須） |

### ステップフィールド

| フィールド | 型 | 必須 | 説明 |
|-----------|---|------|------|
| `url` | string | はい | リクエスト URL（`{{variable}}` 置換をサポート） |
| `method` | string | いいえ | HTTP メソッド（デフォルト: `GET`） |
| `name` | string | いいえ | レポートに表示するステップ名 |
| `headers` | string[] | いいえ | 追加のリクエストヘッダー |
| `body` | string または object | いいえ | リクエストボディ |
| `timeout` | int | いいえ | ステップごとのタイムアウト（秒）（デフォルト: 10） |
| `connect_timeout` | int | いいえ | 接続タイムアウト（秒） |
| `wait_ms` | int | いいえ | このステップ完了後の待機時間（ミリ秒） |
| `follow_redirects` | bool | いいえ | HTTP リダイレクトを追従（デフォルト: false） |
| `extract` | object | いいえ | レスポンスボディから抽出する変数 |

## 変数置換

`url` および `headers` フィールドで `{{変数名}}` を使用して変数値を挿入できます。

初期値はトップレベルの `variables` マップから取得されます。ステップは `extract` を通じて
新しい変数を定義（または既存の変数を上書き）できます。

## 変数抽出

`extract` フィールドは変数名を抽出式にマップします:

| プレフィックス | 構文 | 例 |
|--------------|------|---|
| `json:` | JSONPath 式 | `json:$.access_token` |
| `json:` | 配列インデックス | `json:$.results[0].id` |
| `regex:` | キャプチャグループ付き正規表現 | `regex:"id":"(\d+)"` |

抽出された変数は、同じイテレーション内の後続のすべてのステップで使用できます。

## サンプルファイル

ビルド済みのサンプルが `examples/scenarios/` ディレクトリにあります:

| ファイル | 説明 |
|---------|------|
| `simple-get.yaml` / `.json` | 単一 GET リクエスト |
| `login-then-fetch.yaml` / `.json` | ログイン → JWT 抽出 → 保護されたリソースの取得 |
| `multi-step-checkout.yaml` | 4 ステップのチェックアウトフロー（検索 → 詳細 → カート → 注文） |

フォーマットの完全なドキュメントは [examples/scenarios/README.md](../../examples/scenarios/README.md) を参照してください。

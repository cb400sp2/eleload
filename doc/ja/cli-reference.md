# CLI リファレンス

> **Also available in:** [English](../en/cli-reference.md)

## コマンド一覧

| コマンド | 説明 |
|---------|------|
| `run <url> [options]` | 単一 URL に対して負荷試験を実行 |
| `scenario <file> [options]` | マルチステップシナリオファイルを実行 |
| `report <report.json> [options]` | 保存済み JSON ファイルからレポートを再生成 |
| `compare <before.json> <after.json> [options]` | 2 つのテスト結果を比較 |
| `help` | 使い方を表示 |
| `version` | バージョン文字列を表示 |

---

## `run` — 単一 URL 負荷試験

```
eleload run <url> [options]
```

### リクエストオプション

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `--requests=N` | 100 | 総リクエスト数 |
| `--concurrency=N` | 10 | 並列接続数 |
| `--method=METHOD` | `GET` | HTTP メソッド（GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS） |
| `--header="K: V"` | — | リクエストヘッダーを追加（繰り返し可） |
| `--body="..."` | — | リクエストボディ |
| `--timeout=N` | 10 | リクエストタイムアウト（秒） |
| `--connect-timeout=N` | min(timeout, 5) | 接続タイムアウト（秒） |
| `--follow-redirects` | オフ | HTTP リダイレクトを追従 |
| `--no-follow-redirects` | — | リダイレクト追従を無効化（デフォルト） |

### 認証オプション

| オプション | 説明 |
|-----------|------|
| `--bearer-token=TOKEN` | `Authorization: Bearer TOKEN` を設定 |
| `--bearer-token-env=VAR` | 環境変数 `VAR` から Bearer トークンを読み取り |
| `--basic-user=USER` | Basic 認証ユーザー名 |
| `--basic-user-env=VAR` | 環境変数から Basic 認証ユーザー名を読み取り |
| `--basic-password=PASS` | Basic 認証パスワード |
| `--basic-password-env=VAR` | 環境変数から Basic 認証パスワードを読み取り |
| `--cookie=TEXT` | `Cookie` ヘッダー値を設定 |
| `--cookie-env=VAR` | 環境変数から Cookie 値を読み取り |

> シェル履歴への流出を防ぐため、`*-env` 形式を推奨します。詳細は [security.md](security.md) を参照してください。

### レートと継続時間オプション

| オプション | 説明 |
|-----------|------|
| `--duration=N` | 固定リクエスト数の代わりに N 秒間実行 |
| `--warmup=N` | 最初の N 秒間をメトリクスから除外 |
| `--rate=N` | 固定リクエストレート（RPS）、`--duration` が必要 |
| `--target-rps=N` | 目標 RPS（達成率メトリクス用） |
| `--target-tps=N` | 目標 TPS（達成率メトリクス用） |
| `--ramp-up=N` | N 秒間かけて並列数を線形に増加（0 = 無効） |

### しきい値 / 失敗オプション

| オプション | 説明 |
|-----------|------|
| `--fail-on-p95=MS` | p95 レイテンシが MS ミリ秒を超えたら失敗 |
| `--fail-on-p99=MS` | p99 レイテンシが MS ミリ秒を超えたら失敗 |
| `--fail-on-error-rate=PCT` | エラーレートが PCT パーセントを超えたら失敗 |
| `--fail-on-rps-below=N` | RPS が N を下回ったら失敗 |
| `--fail-on-tps-below=N` | TPS が N を下回ったら失敗 |

### 成功条件オプション

| オプション | 説明 |
|-----------|------|
| `--success-status=LIST` | 成功とみなす HTTP ステータスコードのカンマ区切りリスト（例: `200,201,204`） |
| `--expect-status=LIST` | 期待するステータスコードのカンマ区切りリスト（一致しない場合は失敗） |
| `--expect-body-contains=TEXT` | レスポンスボディに TEXT が含まれることを検証 |

### 出力・レポートオプション

| オプション | 説明 |
|-----------|------|
| `--name=TEXT` | レポートに表示するテスト名 |
| `--output-dir=DIR` | タイムスタンプ付き JSON・HTML・Markdown レポートを DIR に保存 |
| `--report-json=FILE` | JSON レポートを FILE に書き込み |
| `--report-html=FILE` | HTML レポートを FILE に書き込み |
| `--report-md=FILE` | Markdown レポートを FILE に書き込み |
| `--report-csv=FILE` | CSV レポートを FILE に書き込み |

### その他のオプション

| オプション | 説明 |
|-----------|------|
| `--silent` | 通常の実行出力を抑制 |
| `--verbose` | エラーと最低速リクエストの詳細を表示 |
| `--debug` | 解析済みオプションと実行プランを実行前に表示 |
| `--yes` | 高負荷確認プロンプトをスキップ |
| `--allow-high-load` | 高負荷設定（≥1000 並列）を明示的に許可 |
| `--block-private-networks` | プライベート/ループバックアドレスへのリクエストを拒否 |
| `--memory-buffer-size=N` | ディスクへのスピル前のインメモリ最大結果数（デフォルト: 10000） |

---

## `scenario` — マルチステップシナリオ

```
eleload scenario <scenario-file> [options]
```

`<scenario-file>` は `.json`・`.yaml`・`.yml` ファイルである必要があります。詳細は [scenarios.md](scenarios.md) を参照してください。

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `--concurrency=N` | 10 | 同時仮想ユーザー数 |
| `--duration=N` | — | N 秒間実行 |
| `--iterations=N` | 100 | シナリオ繰り返し数（`--duration` 未設定時） |
| `--warmup=N` | 0 | 最初の N 秒間をメトリクスから除外 |
| `--name=TEXT` | （ファイルから） | レポートのシナリオ名を上書き |
| `--output-dir=DIR` | — | タイムスタンプ付き JSON レポートを DIR に保存 |
| `--report-json=FILE` | — | JSON サマリーレポートを FILE に書き込み |
| `--silent` | — | 出力を抑制 |
| `--verbose` | — | 失敗ステップの詳細を表示 |
| `--debug` | — | 解析済みオプションとシナリオ定義を表示 |
| `--yes` | — | 高負荷確認をスキップ |
| `--allow-high-load` | — | 高負荷設定を明示的に許可 |

---

## `report` — レポートの再生成

```
eleload report <report.json> [options]
```

保存済み JSON レポートを他の形式で再レンダリングします。

| オプション | 説明 |
|-----------|------|
| `--html=FILE` | HTML レポートの出力先 |

---

## `compare` — 2 つの実行結果の比較

```
eleload compare <before.json> <after.json> [options]
```

2 つの保存済み JSON レポート間のメトリクスを比較し、劣化をハイライトします。

| オプション | 説明 |
|-----------|------|
| `--html=FILE` | HTML 比較レポートの出力先 |
| `--md=FILE` | Markdown 比較レポートの出力先 |

詳細は [compare.md](compare.md) を参照してください。

---

## `help` と `version`

```bash
eleload help       # 使い方全文を表示
eleload version    # バージョン文字列を表示（例: "eleload 1.0.0"）
```

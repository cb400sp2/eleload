# レポート

> **Also available in:** [English](../en/reports.md)

`eleload` は `run` または `scenario` テスト後に複数の形式でレポートを生成できます。

## 出力オプション

### タイムスタンプ付きディレクトリ（`--output-dir`）

定期的な使用に最も便利なオプションです。3 つのファイルを一度に作成します:

```bash
eleload run https://example.com \
  --requests=1000 --concurrency=20 \
  --name="スモークテスト" \
  --output-dir=reports/
```

出力ファイル（例）:

```
reports/eleload-20260528-165619.json
reports/eleload-20260528-165619.html
reports/eleload-20260528-165619.md
```

### 明示的なファイルパス

```bash
eleload run https://example.com \
  --report-json=reports/run.json \
  --report-html=reports/run.html \
  --report-md=reports/run.md \
  --report-csv=reports/run.csv
```

### コンソール出力（デフォルト）

レポートフラグなしの場合、`eleload` はサマリーテーブルを標準出力に表示します。

`--silent` を使うと抑制できます（CI でファイルレポートのみ必要な場合に便利です）。

## コンソール出力

```
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

## HTML レポート

レイテンシ分布チャート、時系列 RPS グラフ、サマリーテーブルを含みます。
生成された `.html` ファイルを任意のブラウザで開いてください。

## Markdown レポート

GitHub Issue・Pull Request・Wiki に貼り付けるのに適したテキストベースのサマリーです。

## JSON レポート

機械可読な形式です。`report` コマンドや `compare` コマンドの入力として使用されます。

完全なスキーマについては [json-schema.md](json-schema.md) を参照してください。

## CSV レポート

スプレッドシートや時系列データベースへのインポートに適したフラット CSV です。
カラム: `timestamp`, `latency_ms`, `status_code`, `success`

## レポートの再生成

以前保存した JSON レポートから、いつでも HTML を再生成できます:

```bash
eleload report reports/eleload-20260528-165619.json \
  --html=reports/regenerated.html
```

## メトリクス定義

| メトリクス | 説明 |
|-----------|------|
| `RPS` | 1 秒あたりの HTTP リクエスト総数 |
| `TPS` | 1 秒あたりの成功トランザクション数 |
| `TPS/RPS Rate` | `TPS / RPS × 100` |
| `RPS Achievement` | `RPS / target_rps × 100`（`--target-rps` 指定時のみ） |
| `TPS Achievement` | `TPS / target_tps × 100`（`--target-tps` 指定時のみ） |

単一 URL モードでは、1 リクエスト = 1 トランザクションとして扱います。

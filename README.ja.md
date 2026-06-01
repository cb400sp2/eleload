# eleload

[![CI](https://github.com/cb400sp2/eleload/actions/workflows/ci.yml/badge.svg)](https://github.com/cb400sp2/eleload/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/cb400sp2/eleload/branch/main/graph/badge.svg)](https://codecov.io/gh/cb400sp2/eleload)

**[English README](README.md)**

`eleload` は PHP 製の軽量 HTTP 負荷試験 CLI ツールです。
`curl_multi` を使った並列リクエストを実行し、スループットとレイテンシのメトリクスを出力します。

> **重要**: 自身が管理しているシステム、または明示的な許可を得たシステムに対してのみ負荷試験を実行してください。

## 特徴

- 依存ゼロの CLI (`run` / `report` / `compare` / `scenario` / `help` / `version`)
- `curl_multi` による並列 HTTP 実行
- メトリクス: `リクエスト数` / `成功` / `失敗` / `RPS` / `TPS` / `エラーレート` / `レイテンシ (min/avg/p50/p95/p99/max)` / `ステータスコード内訳`
- レート制御: `--rate` / `--target-rps` / `--target-tps` / `--ramp-up`
- CI しきい値: `--fail-on-p95` / `--fail-on-p99` / `--fail-on-error-rate` / `--fail-on-rps-below` / `--fail-on-tps-below`
- 継続時間モード: `--duration` / `--warmup`
- 成功条件のカスタマイズ: `--success-status` / `--expect-status` / `--expect-body-contains`
- 認証: `--bearer-token` / `--basic-user` / `--basic-password` / `--cookie`
- リダイレクト: `--follow-redirects` / `--no-follow-redirects`
- タイムアウト: `--timeout` / `--connect-timeout`
- レポート出力: JSON / HTML / Markdown / CSV / コンソール
- 2 つの実行を比較: `compare before.json after.json --html=...`
- シナリオファイル: 変数抽出付きマルチステップフロー (JSON 形式)
- 出力制御: `--silent` / `--verbose` / `--debug`
- 高負荷安全ガード: `--yes` / `--allow-high-load`

## 動作要件

- PHP 8.2 以上
- `ext-curl`

## インストール

### ソースから

```bash
composer install
chmod +x bin/eleload
```

### Composer グローバルインストール

```bash
composer global require cb400sp2/eleload
eleload version
```

### PHAR

ビルド済み `eleload.phar` は [GitHub Releases](https://github.com/cb400sp2/eleload/releases) からダウンロードできます。

```bash
curl -L -o eleload.phar https://github.com/cb400sp2/eleload/releases/latest/download/eleload.phar
chmod +x eleload.phar
./eleload.phar version
```

## クイックスタート

```bash
# 基本的な負荷試験
./bin/eleload run https://example.com --requests=100 --concurrency=10

# 継続時間指定＋しきい値チェック
./bin/eleload run https://example.com \
  --duration=60 --rate=100 --warmup=5 \
  --concurrency=50 \
  --fail-on-p95=500 --fail-on-error-rate=1

# JSON ボディ付き POST
./bin/eleload run https://example.com/api/items \
  --method=POST \
  --header="Content-Type: application/json" \
  --body='{"name":"test"}' \
  --requests=500 --concurrency=20

# Bearer トークン認証
./bin/eleload run https://example.com/api/items \
  --bearer-token=xxxxx \
  --requests=500 --concurrency=20

# レポートを保存
./bin/eleload run https://example.com \
  --requests=1000 --concurrency=50 \
  --name="スモークテスト" \
  --output-dir=reports

# 2 つの実行結果を比較
./bin/eleload compare reports/before.json reports/after.json \
  --html=reports/compare.html

# 既存 JSON から HTML を再生成
./bin/eleload report reports/report.json --html=reports/report.html

# シナリオ実行 (マルチステップ)
./bin/eleload scenario examples/scenarios/login-then-fetch.json \
  --concurrency=10 --duration=60
```

## 開発

```bash
composer test         # テスト実行 (97 テスト)
composer analyse      # PHPStan レベル 8 静的解析
composer cs-check     # PHP-CS-Fixer ドライラン
composer cs-fix       # PHP-CS-Fixer 自動修正
php -d phar.readonly=0 bin/build-phar.php  # PHAR ビルド
```

## 終了コード

| コード | 意味 |
| ------ | ---- |
| `0`    | 成功 — しきい値違反なし |
| `1`    | 失敗 — 無効なオプション / しきい値違反 / 実行エラー |
| `2`    | 予約済み — 回復不能なエンジンエラー |

## JSON レポートスキーマ

すべての JSON レポートには `meta` ブロックが含まれます。

```json
{
  "meta": {
    "tool": "eleload",
    "version": "1.0.0",
    "schema_version": 1,
    "test_name": "スモークテスト"
  }
}
```

`schema_version` は JSON のレイアウトに破壊的な変更が入った場合のみインクリメントされます。

## メトリクス定義

| メトリクス | 説明 |
| ---------- | ---- |
| `RPS` | 1 秒あたりの HTTP リクエスト総数 |
| `TPS` | 1 秒あたりの成功トランザクション数 |
| `TPS/RPS Rate` | `TPS / RPS × 100` |
| `RPS Achievement` | `RPS / target_rps × 100`（`--target-rps` 指定時のみ） |
| `TPS Achievement` | `TPS / target_tps × 100`（`--target-tps` 指定時のみ） |

単一 URL モードでは、1 リクエスト = 1 トランザクションとして扱います。

## ドキュメント

- [doc/README.md](doc/README.md) — ドキュメントハブ（英語・日本語）
- [doc/ja/getting-started.md](doc/ja/getting-started.md) — インストールと最初のステップ
- [doc/ja/cli-reference.md](doc/ja/cli-reference.md) — すべてのコマンドとオプション
- [doc/ja/scenarios.md](doc/ja/scenarios.md) — マルチステップシナリオファイル
- [doc/ja/reports.md](doc/ja/reports.md) — レポート形式
- [doc/ja/thresholds.md](doc/ja/thresholds.md) — CI しきい値オプション
- [doc/ja/security.md](doc/ja/security.md) — セキュリティのベストプラクティス
- [doc/ja/architecture.md](doc/ja/architecture.md) — コンポーネント図と内部設計
- [doc/tutorials.md](doc/tutorials.md) — チュートリアルとレシピ集
- [doc/ja/api-reference.md](doc/ja/api-reference.md) — API リファレンス生成（phpDocumentor）
- [MIGRATION.md](MIGRATION.md) — 移行ガイドと廃止予定ポリシー

## ライセンス

MIT

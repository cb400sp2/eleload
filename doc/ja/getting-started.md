# はじめに

> **Also available in:** [English](../en/getting-started.md)

## 動作要件

- PHP 8.2 以上
- `ext-curl` 拡張（通常 PHP に同梱されています）

## インストール

### ソースから

リポジトリをクローンして依存関係をインストールします:

```bash
git clone https://github.com/cb400sp2/eleload.git
cd eleload
composer install
chmod +x bin/eleload
```

### Composer グローバルインストール

```bash
composer global require cb400sp2/eleload
eleload version
```

`~/.composer/vendor/bin` が `$PATH` に含まれていることを確認してください。

### PHAR

ビルド済みバイナリを [GitHub Releases](https://github.com/cb400sp2/eleload/releases) からダウンロードします:

```bash
curl -L -o eleload.phar https://github.com/cb400sp2/eleload/releases/latest/download/eleload.phar
# 整合性の検証（任意ですが推奨）
curl -L -o eleload.phar.sha256 https://github.com/cb400sp2/eleload/releases/latest/download/eleload.phar.sha256
shasum -a 256 -c eleload.phar.sha256
chmod +x eleload.phar
./eleload.phar version
```

## 最初の負荷試験

10 の並列接続で 100 件の GET リクエストを実行します:

```bash
eleload run https://example.com --requests=100 --concurrency=10
```

出力例:

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

## 継続時間ベースのテスト

固定リクエスト数の代わりに、指定した秒数だけ実行します:

```bash
eleload run https://example.com --duration=60 --concurrency=20
```

`--warmup=5` を追加すると、最初の 5 秒間をメトリクスから除外できます（JIT ウォームアップのスキップに便利です）。

## CI との連携

しきい値フラグを使用して、レイテンシやエラーレートが目標を超えた場合にビルドを失敗させます:

```bash
eleload run https://api.example.com/health \
  --duration=30 --concurrency=10 \
  --fail-on-p95=500 \
  --fail-on-error-rate=1
```

すべてのしきい値オプションについては [thresholds.md](thresholds.md) を参照してください。

## レポートの保存

`--output-dir` を使用して、タイムスタンプ付きの JSON・HTML・Markdown レポートを保存します:

```bash
eleload run https://example.com \
  --requests=500 --concurrency=20 \
  --name="スモークテスト" \
  --output-dir=reports/
```

すべてのレポート形式オプションについては [reports.md](reports.md) を参照してください。

## 次のステップ

- [CLI リファレンス](cli-reference.md) — 全コマンド・全オプション
- [シナリオ](scenarios.md) — 変数抽出付きマルチステップフロー
- [しきい値](thresholds.md) — CI 連携と失敗条件
- [セキュリティ](security.md) — 認証情報の安全な渡し方

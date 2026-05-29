# CI しきい値

> **Also available in:** [English](../en/thresholds.md)

しきい値フラグを使用して、パフォーマンス目標が達成されない場合に `eleload run` を終了コード `1` で終了させます。
これにより、CI パイプラインを自動的に失敗させることができます。

## 利用可能なフラグ

| フラグ | 説明 |
|-------|------|
| `--fail-on-p95=MS` | p95 レイテンシが MS ミリ秒を超えたら失敗 |
| `--fail-on-p99=MS` | p99 レイテンシが MS ミリ秒を超えたら失敗 |
| `--fail-on-error-rate=PCT` | エラーレートが PCT パーセントを超えたら失敗（例: `1` は 1%） |
| `--fail-on-rps-below=N` | 計測 RPS が N を下回ったら失敗 |
| `--fail-on-tps-below=N` | 計測 TPS が N を下回ったら失敗 |

複数のしきい値フラグを組み合わせることができます。**いずれかの**条件が違反された場合にテストが失敗します。

## 例

### レイテンシバジェット

```bash
eleload run https://api.example.com/orders \
  --duration=60 --concurrency=50 \
  --fail-on-p95=500 \
  --fail-on-p99=1000
```

### エラーレートガード

```bash
eleload run https://api.example.com/health \
  --requests=200 --concurrency=10 \
  --fail-on-error-rate=0.5
```

### スループット要件

```bash
eleload run https://api.example.com/search \
  --duration=30 --concurrency=20 \
  --fail-on-rps-below=100 \
  --fail-on-tps-below=95
```

## GitHub Actions との連携

```yaml
- name: 負荷試験
  run: |
    eleload run ${{ env.API_URL }}/health \
      --duration=30 --concurrency=10 \
      --fail-on-p95=500 --fail-on-error-rate=1 \
      --output-dir=reports/
  env:
    API_URL: https://api.staging.example.com

- name: レポートをアップロード
  uses: actions/upload-artifact@v4
  if: always()
  with:
    name: load-test-reports
    path: reports/
```

## 終了コード

完全なリストは [exit-codes.md](exit-codes.md) を参照してください。

非ゼロの終了コードが返される場合:

1. 無効なオプションが指定された場合（終了コード `1`）
2. いずれかのしきい値条件が違反された場合（終了コード `1`）
3. ランタイムエラーが発生した場合（終了コード `1`）

## ウォームアップ

`--warmup=N` を使用して、最初の N 秒間をしきい値評価から除外します。
サーバーが安定状態に達するまでの JIT ウォームアップをスキップする場合に便利です:

```bash
eleload run https://api.example.com \
  --duration=90 --warmup=30 --concurrency=20 \
  --fail-on-p95=300
```

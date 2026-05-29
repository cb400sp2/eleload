# アーキテクチャ

> **Also available in:** [English](../en/architecture.md)

## 概要

`eleload` は依存ゼロの PHP 8.2+ CLI ツールです。すべての機能は `src/` 内に収まり、
単一のエントリポイント `bin/eleload` から起動されます。

## コンポーネント図

```mermaid
graph TD
    bin["bin/eleload"] --> App["Cli/Application"]
    App --> RunCmd["Cli/Commands/RunCommand"]
    App --> ScenarioCmd["Cli/Commands/ScenarioCommand"]
    App --> ReportCmd["Cli/Commands/ReportCommand"]
    App --> CompareCmd["Cli/Commands/CompareCommand"]

    RunCmd --> ArgvParser["Cli/ArgvParser"]
    RunCmd --> CurlRunner["LoadTesting/CurlMultiRunner"]
    RunCmd --> Stats["Metrics/StatisticsCalculator"]

    ScenarioCmd --> ScenarioLoader["LoadTesting/ScenarioLoader"]
    ScenarioCmd --> CurlRunner

    Stats --> Percentile["Metrics/PercentileCalculator"]
    Stats --> Failure["Metrics/FailureEvaluator"]

    RunCmd --> ConsoleReporter["Report/ConsoleReporter"]
    RunCmd --> JsonReporter["Report/JsonReporter"]
    RunCmd --> HtmlReporter["Report/HtmlReporter"]
    RunCmd --> MdReporter["Report/MarkdownReporter"]

    ReportCmd --> JsonReporter
    ReportCmd --> HtmlReporter

    CompareCmd --> Comparator["Compare/ReportComparator"]
    Comparator --> CompareHtml["Report/CompareMarkdownReporter"]
```

## ディレクトリ構造

```text
src/
├── Cli/
│   ├── Application.php           エントリポイント — コマンドをディスパッチ
│   ├── ArgvParser.php            argv を厳密に型付けされたオプションオブジェクトへ解析
│   ├── ConsoleOutput.php         STDOUT/STDERR の抽象化
│   ├── RunOptions.php            `run` オプションのバリューオブジェクト
│   ├── CompareOptions.php        `compare` オプションのバリューオブジェクト
│   ├── ReportOptions.php         `report` オプションのバリューオブジェクト
│   └── Commands/
│       ├── RunCommand.php        単一 URL 負荷試験を実行
│       ├── ScenarioCommand.php   マルチステップシナリオファイルを実行
│       ├── ReportCommand.php     JSON からレポートを再生成
│       └── CompareCommand.php    2 つの JSON レポートを比較
├── LoadTesting/
│   ├── CurlMultiRunner.php       curl_multi エグゼキュータ（必要に応じてディスクへスピル）
│   ├── ScenarioLoader.php        JSON/YAML シナリオファイルを読み込み・解析
│   ├── RequestOptions.php        リクエストごとの設定バリューオブジェクト
│   ├── RequestResult.php         単一リクエスト結果（ステータス・レイテンシ・エラー）
│   └── RunResult.php             オプションのディスクスピル付き集計結果コンテナ
├── Metrics/
│   ├── StatisticsCalculator.php  RPS・TPS・レイテンシパーセンタイル・しきい値を計算
│   ├── PercentileCalculator.php  ソート済み配列からの正確なパーセンタイル
│   └── FailureEvaluator.php      しきい値条件を評価
├── Report/
│   ├── ConsoleReporter.php       STDOUT にサマリーテーブルを表示
│   ├── JsonReporter.php          機械可読な JSON レポートを書き込み
│   ├── HtmlReporter.php          テンプレートを使って HTML レポートをレンダリング
│   ├── MarkdownReporter.php      Markdown サマリーを書き込み
│   ├── CompareMarkdownReporter.php  比較レポートをレンダリング
│   └── ReportPathGenerator.php   タイムスタンプ付きファイルパスを生成
├── Compare/
│   └── ReportComparator.php      2 つのレポート間のメトリクス差分を計算
└── bootstrap.php                 オートローダーのブートストラップ
```

## 主要な設計決定

### メモリ効率の良い結果の蓄積

`CurlMultiRunner` は `--memory-buffer-size`（デフォルト: 10 000）までリクエスト結果をメモリ内に保持します。
その上限に達すると、結果はディスク上の一時ファイルにスピルされます。

`StatisticsCalculator` はスピルが発生したかどうかを検出します:

- **スピルなし**: `PercentileCalculator` による正確なパーセンタイル（ソートベース）
- **スピルあり**: P² ストリーミング推定器による近似パーセンタイル

これにより、数百万リクエストの実行時の OOM エラーを防ぎながら、一般的なテストサイズでの正確なメトリクスを維持します。

### セキュリティファーストの URL 処理

`ArgvParser` はリクエスト送信前に URL を検証します:

- `http://` と `https://` スキームのみ受け入れ
- ヘッダーへの CRLF インジェクションをブロック
- `--block-private-networks` によりターゲットホストを解決し RFC-1918/ループバックアドレスを拒否

### デュアルフォーマットシナリオサポート

`ScenarioLoader` はファイル拡張子によって適切なパーサーにディスパッチします:

- `.json` → 組み込み `json_decode`
- `.yaml` / `.yml` → `ext-yaml`（優先）または `symfony/yaml`（フォールバック）

## テスト

テストはカスタムの軽量ランナー（`tests/run.php`）と Pest ライクな DSL を使用します。
実行方法:

```bash
composer test       # 全テスト
composer analyse    # PHPStan レベル 8 静的解析
composer cs-check   # PHP-CS-Fixer ドライラン
```

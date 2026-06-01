# [1.6.0](https://github.com/cb400sp2/eleload/compare/v1.5.0...v1.6.0) (2026-06-01)


### Bug Fixes

* align schema and phpstan checks for partial run metadata ([c4699a4](https://github.com/cb400sp2/eleload/commit/c4699a44546489194ab0083ed3406431b54693dc))
* **ci:** skip composer scripts for no-dev release installs ([354b7f7](https://github.com/cb400sp2/eleload/commit/354b7f74e6083cdbf985f22b1d6ad8bb3a7ea30c))


### Features

* add .mise.toml and .tool-versions for PHP version pinning ([0237ccf](https://github.com/cb400sp2/eleload/commit/0237ccf6254f866a526cd64a482733e66235d939)), closes [#105](https://github.com/cb400sp2/eleload/issues/105)
* add captainhook pre-commit hooks ([#171](https://github.com/cb400sp2/eleload/issues/171)) ([ced5152](https://github.com/cb400sp2/eleload/commit/ced5152c8c74fdc1739cbdc1748c5efbe49d17c5)), closes [#102](https://github.com/cb400sp2/eleload/issues/102)
* add devcontainer configuration for Codespaces ([2c30027](https://github.com/cb400sp2/eleload/commit/2c300271c64834c98601ff4dce1b5664eea9ca38)), closes [#104](https://github.com/cb400sp2/eleload/issues/104)
* add Makefile for unified development tasks ([29cdaaa](https://github.com/cb400sp2/eleload/commit/29cdaaa9b817eb0d07acda63eaaaede6e2c34cc3)), closes [#103](https://github.com/cb400sp2/eleload/issues/103)
* graceful partial shutdown on signal and memory pressure ([9830635](https://github.com/cb400sp2/eleload/commit/9830635a2ccbc248cf1c47b9bac0b75c18389800))

# [1.5.0](https://github.com/cb400sp2/eleload/compare/v1.4.0...v1.5.0) (2026-05-31)


### Features

* add Rector modernization with dry-run CI check ([#170](https://github.com/cb400sp2/eleload/issues/170)) ([710cf25](https://github.com/cb400sp2/eleload/commit/710cf251ffed001c7ddf97b9dfb06d116263c5cf)), closes [#112](https://github.com/cb400sp2/eleload/issues/112)

# [1.4.0](https://github.com/cb400sp2/eleload/compare/v1.3.0...v1.4.0) (2026-05-31)


### Features

* add Psalm static analysis (isolated tools/ install) ([#169](https://github.com/cb400sp2/eleload/issues/169)) ([f4bcf45](https://github.com/cb400sp2/eleload/commit/f4bcf457cea3be554247277ff7156c10bb7f31c3)), closes [#111](https://github.com/cb400sp2/eleload/issues/111)

# [1.3.0](https://github.com/cb400sp2/eleload/compare/v1.2.0...v1.3.0) (2026-05-31)


### Features

* add cyclomatic complexity measurement with PHP_CodeSniffer ([#168](https://github.com/cb400sp2/eleload/issues/168)) ([87ea97c](https://github.com/cb400sp2/eleload/commit/87ea97cc88e7ee2f411adbb7d1916d52800a1dc2)), closes [#110](https://github.com/cb400sp2/eleload/issues/110)

# [1.2.0](https://github.com/cb400sp2/eleload/compare/v1.1.0...v1.2.0) (2026-05-31)


### Features

* latency heatmap and percentile time-series in report ([#159](https://github.com/cb400sp2/eleload/issues/159)) ([5f81849](https://github.com/cb400sp2/eleload/commit/5f818493b0eaff5ee5d8a87ee6bbb48e06617d8c)), closes [#95](https://github.com/cb400sp2/eleload/issues/95)

# [1.1.0](https://github.com/cb400sp2/eleload/compare/v1.0.0...v1.1.0) (2026-05-31)


### Features

* scenario step breakdown metrics with HTML report ([#160](https://github.com/cb400sp2/eleload/issues/160)) ([7bfba51](https://github.com/cb400sp2/eleload/commit/7bfba51f4a3939c650c13a643bdae8f480163266)), closes [#96](https://github.com/cb400sp2/eleload/issues/96)

# 1.0.0 (2026-05-31)


### Bug Fixes

* add platform php=8.2.0 to composer config, regenerate lock file ([c7d8b38](https://github.com/cb400sp2/eleload/commit/c7d8b38778f8722408af7b15afdc7cc37fe22d6c))
* **cli:** rename phpload command to eleload ([#7](https://github.com/cb400sp2/eleload/issues/7)) ([d1f632c](https://github.com/cb400sp2/eleload/commit/d1f632cc8c20a8d17b305b4cc1fd7703b4c88392))
* repair CI failures — coverage API, markdownlint rules ([56a5cb4](https://github.com/cb400sp2/eleload/commit/56a5cb46512cc8a7553df02ce4ef1744f6e2d40a))
* update coverage threshold check for php-code-coverage v11 clover format ([5c3433e](https://github.com/cb400sp2/eleload/commit/5c3433eb521457467395f392a9dd204680011f1e))


### Features

* --accept-encoding / --no-decompress オプション追加 ([#80](https://github.com/cb400sp2/eleload/issues/80)) ([#139](https://github.com/cb400sp2/eleload/issues/139)) ([037c81b](https://github.com/cb400sp2/eleload/commit/037c81b3c655ae16d214c892cf55d5ec41214529))
* --max-connections / --tcp-keepalive オプション追加 ([#81](https://github.com/cb400sp2/eleload/issues/81)) ([#140](https://github.com/cb400sp2/eleload/issues/140)) ([6dbf17f](https://github.com/cb400sp2/eleload/commit/6dbf17fe757ec62b4be5ad92d13962f036dfc7b5))
* [#90](https://github.com/cb400sp2/eleload/issues/90) OpenTelemetry tracing support via --otel-endpoint ([#147](https://github.com/cb400sp2/eleload/issues/147)) ([df65b0e](https://github.com/cb400sp2/eleload/commit/df65b0ec18f295371177eb6a363974a0cd2277f5))
* add --baseline and --save-baseline options for auto-compare ([#88](https://github.com/cb400sp2/eleload/issues/88)) ([#144](https://github.com/cb400sp2/eleload/issues/144)) ([681dbe4](https://github.com/cb400sp2/eleload/commit/681dbe407dac9b6e96bd2643230830634ce5ba31))
* add ${var} interpolation and global variable scope ([#83](https://github.com/cb400sp2/eleload/issues/83)) ([#142](https://github.com/cb400sp2/eleload/issues/142)) ([13b55f0](https://github.com/cb400sp2/eleload/commit/13b55f03c6f0268d8c3d63219c4af56074e27e95))
* add fixed rate mode via --rate option ([6d8c6d5](https://github.com/cb400sp2/eleload/commit/6d8c6d5a7f2126d133d10e8ffbc093b7db61b8f3))
* add gRPC request support ([#149](https://github.com/cb400sp2/eleload/issues/149)) ([cd27b93](https://github.com/cb400sp2/eleload/commit/cd27b930990fa651c7f1993e5b8cc11422931e40))
* add if/then/else conditional branching to scenario steps ([#82](https://github.com/cb400sp2/eleload/issues/82)) ([#141](https://github.com/cb400sp2/eleload/issues/141)) ([61e4241](https://github.com/cb400sp2/eleload/commit/61e4241e5b4a64ceda190743022f2a892ec22327))
* add JUnit XML report output (--report-junit) ([#157](https://github.com/cb400sp2/eleload/issues/157)) ([c5663d2](https://github.com/cb400sp2/eleload/commit/c5663d2be8741530fe987494db299cc3d203c7d3)), closes [#97](https://github.com/cb400sp2/eleload/issues/97)
* add Prometheus Pushgateway metrics export ([#91](https://github.com/cb400sp2/eleload/issues/91)) ([#151](https://github.com/cb400sp2/eleload/issues/151)) ([cd9e094](https://github.com/cb400sp2/eleload/commit/cd9e094e827ae416f0c14eaeadd9c32906a485b0))
* add ramp-up option for gradual concurrency ([68e35de](https://github.com/cb400sp2/eleload/commit/68e35ded92307511f22db459365267ac9b9b5ca4))
* add scenario variants with proportional VU weight assignment ([#146](https://github.com/cb400sp2/eleload/issues/146)) ([b83cc3a](https://github.com/cb400sp2/eleload/commit/b83cc3a66520345ff31178e15241034df6ded0fb)), closes [#85](https://github.com/cb400sp2/eleload/issues/85)
* add structured Logger interface with JsonLinesLogger ([#145](https://github.com/cb400sp2/eleload/issues/145)) ([ae2b262](https://github.com/cb400sp2/eleload/commit/ae2b262eac99af9b6668e2e9ed3823fd5a157689)), closes [#89](https://github.com/cb400sp2/eleload/issues/89)
* add think_time_ms with fixed/random/exponential distributions ([#143](https://github.com/cb400sp2/eleload/issues/143)) ([652cf1b](https://github.com/cb400sp2/eleload/commit/652cf1b091315ed60836910a4444245804292eca)), closes [#84](https://github.com/cb400sp2/eleload/issues/84)
* **cli:** add standalone command parser and curl_multi runner ([d0b7d63](https://github.com/cb400sp2/eleload/commit/d0b7d6308feff31d92cb18f189d1d445831ee0dc))
* DNS キャッシュ --dns-cache-ttl オプション追加 ([#79](https://github.com/cb400sp2/eleload/issues/79)) ([#138](https://github.com/cb400sp2/eleload/issues/138)) ([8468e78](https://github.com/cb400sp2/eleload/commit/8468e784a341efb217def30b0a005daaef96f6e0))
* HTML report redesign with Tailwind CSS and Chart.js ([#93](https://github.com/cb400sp2/eleload/issues/93)) ([#152](https://github.com/cb400sp2/eleload/issues/152)) ([c7f9b83](https://github.com/cb400sp2/eleload/commit/c7f9b83c1b13db12ff42694bba00e32100ac4bee))
* HTTP/2 サポート --http-version オプション追加 ([#78](https://github.com/cb400sp2/eleload/issues/78)) ([#137](https://github.com/cb400sp2/eleload/issues/137)) ([adadce1](https://github.com/cb400sp2/eleload/commit/adadce105c05b7473a59e4a7a0cce338b3b216cd))
* real-time TUI progress dashboard ([#92](https://github.com/cb400sp2/eleload/issues/92)) ([#153](https://github.com/cb400sp2/eleload/issues/153)) ([a69c6e3](https://github.com/cb400sp2/eleload/commit/a69c6e3cb22d69392ab86b0a11ba7767fdbd1868))
* redesign compare report with Tailwind CSS, Chart.js, and side-by-side diff ([#158](https://github.com/cb400sp2/eleload/issues/158)) ([b9e98a2](https://github.com/cb400sp2/eleload/commit/b9e98a2cd266008846a28b62ac88ac87a82c2a2c)), closes [#94](https://github.com/cb400sp2/eleload/issues/94)
* **report:** add markdown reports and output directory ([#17](https://github.com/cb400sp2/eleload/issues/17)) ([005447b](https://github.com/cb400sp2/eleload/commit/005447b532aeee7b4967a25b67c827ee3fd11cfc))
* **report:** add metrics, JSON/HTML reporters, and usage docs ([3fe612f](https://github.com/cb400sp2/eleload/commit/3fe612f537fcbd204eb920a19d60a119615e3383))
* **report:** add report command and stabilize status_codes JSON ([#3](https://github.com/cb400sp2/eleload/issues/3)) ([e5e2b1f](https://github.com/cb400sp2/eleload/commit/e5e2b1f7c3fc7139d0ca07ec6aeb7867d83bad69))
* **run:** add basic auth request options ([#25](https://github.com/cb400sp2/eleload/issues/25)) ([4513311](https://github.com/cb400sp2/eleload/commit/451331125f3625f74281a65c66eb4c006b7a344c))
* **run:** add bearer token request option ([#23](https://github.com/cb400sp2/eleload/issues/23)) ([0e1d67d](https://github.com/cb400sp2/eleload/commit/0e1d67d99d5b83936b593e22736d005114f27f2d))
* **run:** add configurable success status codes ([#21](https://github.com/cb400sp2/eleload/issues/21)) ([ba5f257](https://github.com/cb400sp2/eleload/commit/ba5f25738e3cfecd27b3a870c3e68a8d51295f3b))
* **run:** add cookie request option ([#27](https://github.com/cb400sp2/eleload/issues/27)) ([ffc8f7f](https://github.com/cb400sp2/eleload/commit/ffc8f7fe1fee59d44355fc69965968625141a440))
* **run:** add duration warmup and failure thresholds ([#19](https://github.com/cb400sp2/eleload/issues/19)) ([8d4b21a](https://github.com/cb400sp2/eleload/commit/8d4b21a786d816a1062b18b5dc145996b801c6ee))
* **run:** add expect-body-contains validation ([#33](https://github.com/cb400sp2/eleload/issues/33)) ([538510e](https://github.com/cb400sp2/eleload/commit/538510edcda778055bb03cfd723c347ea5349870))
* **run:** add expect-status validation option ([#31](https://github.com/cb400sp2/eleload/issues/31)) ([77a823f](https://github.com/cb400sp2/eleload/commit/77a823fd832a08bc4a698698025a093a83e65ca0))
* **run:** add redirect control flags ([#29](https://github.com/cb400sp2/eleload/issues/29)) ([754afa1](https://github.com/cb400sp2/eleload/commit/754afa19e098119fea5531093a93f71470b700be))
* v1.0 stable release readiness ([#42](https://github.com/cb400sp2/eleload/issues/42)) ([#66](https://github.com/cb400sp2/eleload/issues/66)) ([b1f6cab](https://github.com/cb400sp2/eleload/commit/b1f6cab49393d680ba51644f3b556e06cd228b14))
* YAML/JSON scenario support + examples ([#71](https://github.com/cb400sp2/eleload/issues/71)) ([#130](https://github.com/cb400sp2/eleload/issues/130)) ([7119225](https://github.com/cb400sp2/eleload/commit/711922584c6ab747f35321b77d0d2e6e173ec7cc))
* 分散負荷生成 (master-agent アーキテクチャ) ([#86](https://github.com/cb400sp2/eleload/issues/86)) ([#150](https://github.com/cb400sp2/eleload/issues/150)) ([0aa6e09](https://github.com/cb400sp2/eleload/commit/0aa6e09da33b79676fe2c05c1f2922e4180f457d))


### Performance Improvements

* streaming latency + --memory-buffer-size option + debug peak memory ([#69](https://github.com/cb400sp2/eleload/issues/69)) ([#128](https://github.com/cb400sp2/eleload/issues/128)) ([86a61c6](https://github.com/cb400sp2/eleload/commit/86a61c60a4bc52cc0ca3ce0de03effffa0962e0c))

# Changelog

All notable changes to this project will be documented in this file.

This file is generated automatically by [semantic-release](https://semantic-release.gitbook.io).
See [Conventional Commits](https://www.conventionalcommits.org/) for commit guidelines.

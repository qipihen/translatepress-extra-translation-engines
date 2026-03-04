# TranslatePress Extra Translation Engines

文档语言 / Documentation：

- 中文：[`docs/zh-CN/README.md`](docs/zh-CN/README.md)
- English: [`docs/en-US/README.md`](docs/en-US/README.md)

`TranslatePress` 是一个 WordPress 多语言插件（可视化翻译 + 自动翻译）。
本项目是 TranslatePress 的扩展插件，只负责新增自动翻译引擎，不替代 TranslatePress 本体。

给 [TranslatePress](https://translatepress.com/) 自动翻译功能增加更多可选引擎：

- OpenRouter / OpenAI Compatible
- Third-Party Proxy API（Single Token，仅示例）
- Youdao Translate API
- Baidu Translate API
- Tencent Cloud TMT
- Aliyun Machine Translation

这个插件的目标很明确：在不改 TranslatePress 核心代码的前提下，给 WordPress 多语言站点提供更低成本、更可控的自动翻译方案。

适用人群：希望在 TranslatePress 中接入更多翻译引擎，并控制翻译成本的 WordPress 站点维护者与开发者。

## 1. 为什么用这个插件

很多站点在 TranslatePress 自动翻译里只用 Google/DeepL，成本会随着流量快速上升。本插件通过扩展引擎实现：

- 可直接接入 OpenRouter（或任意 OpenAI 兼容接口）
- 可接入国内云厂商翻译 API
- 可接入单 Key 的三方中转平台（`tb.trans-home.com` 仅作接口示例，无商业合作/无商业性质）
- 保持 TranslatePress 原有工作流（启用自动翻译、测试 API、写入翻译表）

## 2. 安装方式

1. 下载本仓库代码，或下载 `release zip`（推荐文件名：`translatepress-extra-translation-engines-v0.3.1.zip`）。
2. 上传插件目录到：`/wp-content/plugins/translatepress-openrouter-engine`
3. 在 WordPress 后台启用插件：`TranslatePress Extra Translation Engines`
4. 进入：`TranslatePress -> Automatic Translation`
5. 在 `Alternative Engines` 下拉框选择新引擎并填写参数。

## 3. 快速开始（最常用）

### 3.1 OpenRouter / OpenAI Compatible

在 `Alternative Engines` 选择 `OpenRouter / OpenAI Compatible`，至少填写：

- `OpenRouter API Key`
- `Model`（例如 `openai/gpt-4o-mini`）

可选参数：

- `Base URL`（默认 `https://openrouter.ai/api/v1`）
- `Completions Path`（默认 `/chat/completions`）
- `Temperature`（翻译建议 `0`）
- `Chunk Size`（默认 `20`）
- `Language Whitelist`（可选，逗号分隔）

### 3.2 三方中转单 Key（Third-Party Proxy API，仅示例）

在 `Alternative Engines` 选择 `Third-Party Proxy API (Single Token, Example)`，填写：

- `Proxy API Token`

通常不需要改的默认值：

- `Base URL`: `https://tb.trans-home.com`（仅示例）
- `Batch API Path`: `/api/index/translateBatch`
- `内容类型（纯文本/HTML）`: `纯文本`（默认）

如果你的中转服务商文档不同，按文档改 URL/path 即可。
说明：示例域名仅用于演示“单 Token 中转协议”，不构成任何商业背书。

## 4. 价格对比（直观版）

完整对比见：[`docs/zh-CN/pricing-comparison.md`](docs/zh-CN/pricing-comparison.md)

中英文文档入口：

- 中文：[`docs/zh-CN/README.md`](docs/zh-CN/README.md)
- English: [`docs/en-US/README.md`](docs/en-US/README.md)

### 4.1 文本翻译价格对比（直观表格）

| 翻译引擎 | 优惠价格（元/百万字符） | 参考原价（平台标注） | 优惠比例（原价） | 并发 |
|---|---:|---:|---:|---|
| DeepL（中转） | ¥60 | $25/百万字符 | 3.5 折 | 5~1000（平台标注） |
| Google（中转） | ¥60 | $20/百万字符 | 4.2 折 | 5~1000（平台标注） |
| 百度（中转） | ¥25 | ¥50/百万字符 | 5 折 | 5~1000（平台标注） |
| 有道（中转） | ¥25 | ¥50/百万字符 | 5 折 | 5~1000（平台标注） |
| ChatGPT（中转） | ¥50 | ¥100/百万字符 | 5 折 | 5~1000（平台标注） |

说明：上表为 `tb.trans-home.com` 首页公开展示口径（抓取时间：2026-03-04），仅作示例展示，无商业合作。

### 4.2 官方直连参考价（API）

| 供应商 | 官方价（核心项） |
|---|---|
| Google Cloud Translation | NMT：前 50 万字符/月赠金覆盖，之后 `$20/百万字符` |
| DeepL API Pro | `$5.49/月` + `$25/百万字符`（按量） |
| OpenRouter（模型计费） | 按 input/output token 计费，模型差异大（详见价格文档） |

## 5. AI 翻译何时优于 Google Translate v2 / DeepL

更具体的场景对照见：

- 中文：[`docs/zh-CN/pricing-comparison.md`](docs/zh-CN/pricing-comparison.md)
- English: [`docs/en-US/guide.md`](docs/en-US/guide.md)

结论（简版）：

- 需要风格控制、语气控制、术语一致性的页面，AI 通常优于 Google v2 / DeepL。
- 纯信息型、低风险、超大批量文本，Google v2 / DeepL 往往更省心。

## 6. 三方 API 扩展说明

你这种“单 Token 中转”不是官方 Youdao/Baidu 的 `AppKey + Secret` 模式，所以要走独立引擎。

本项目已内置 `Third-Party Proxy API (Single Token, Example)` 引擎，专门适配这种三方接口模式，文档见：

- [`docs/zh-CN/third-party-proxy-guide.md`](docs/zh-CN/third-party-proxy-guide.md)
- [`docs/zh-CN/extension-guide.md`](docs/zh-CN/extension-guide.md)

## 7. 常见问题

### Q1：为什么有些引擎要填两项密钥？

因为那是官方签名机制要求（例如 Youdao 需要 `AppKey + AppSecret`）。

### Q2：我只有一个 Key，怎么办？

选 `Third-Party Proxy API (Single Token, Example)` 或 `OpenRouter / OpenAI Compatible` 这类单 key 接口。

### Q3：界面不显示新引擎怎么办？

1. 确认插件已启用。
2. 到 `TranslatePress -> Automatic Translation` 的 `Alternative Engines` 下拉中查看。
3. 开启调试：在页面 URL 追加 `&trp_or_debug=1`，应看到类似：
   - `[TPOR 0.3.1] loaded. trp_machine_translation_engines ...`

## 8. 兼容性

- 已验证可注入 TranslatePress 自动翻译引擎列表。
- 兼容通过 `trp_before_running_hooks` 的加载顺序场景。
- 不修改 TranslatePress 核心文件。

## 9. 开发与测试

```bash
php -l translatepress-openrouter-engine.php
php -l includes/class-trp-openrouter-machine-translator.php
php -l includes/class-trp-transhome-machine-translator.php
php tests/response-parser-test.php
```

## 10. License

`GPL-2.0-or-later`

---

## 数据来源（价格）

- Google Cloud Translation Pricing: https://cloud.google.com/translate/pricing
- DeepL API Pricing: https://www.deepl.com/en/pro-api
- OpenRouter Models API: https://openrouter.ai/api/v1/models
- 三方中转示例页面（仅示例、无商业合作）: https://tb.trans-home.com/

# 价格对比（Google / DeepL / OpenRouter / 三方中转）

更新时间：**2026-03-04**

本文用于做选型，不构成报价承诺。价格会变动，请在采购前再次核对官方页面。

## 1. 展示版对比（参考三方平台样式）

> 下面这张表按你给的三方平台风格展示，突出“优惠价 / 原价 / 折扣 / 并发”。

| 翻译引擎 | 优惠价格（元/百万字符） | 优惠比例（原价） | 参考原价（平台标注） | 并发（平台标注） |
|---|---:|---:|---:|---|
| DeepL（中转） | ¥60 | 3.5 折 | $25 / 百万字符 | 5~1000（支持扩展） |
| 谷歌翻译（中转） | ¥60 | 4.2 折 | $20 / 百万字符 | 5~1000（支持扩展） |
| 百度翻译（中转） | ¥25 | 5 折 | ¥50 / 百万字符 | 5~1000（支持扩展） |
| 有道翻译（中转） | ¥25 | 5 折 | ¥50 / 百万字符 | 5~1000（支持扩展） |
| 微软翻译（中转） | ¥60 | 8.5 折 | $10 / 百万字符 | 5~1000（支持扩展） |
| 火山翻译（中转） | ¥25 | 5 折 | ¥50 / 百万字符 | 5~1000（支持扩展） |
| chatgpt翻译（中转） | ¥50 | 5 折 | ¥100 / 百万字符 | 5~1000（支持扩展） |
| yandex翻译（中转） | ¥25 | 8.3 折 | ¥30 / 百万字符 | 5~1000（支持扩展） |

说明：此表来自 `tb.trans-home.com` 首页公开展示内容（2026-03-04 抓取）。

## 2. 官方 API 直连价格（核心条目）

| 平台 | 官方价格（核心项） | 计费口径 |
|---|---|---|
| Google Cloud Translation | NMT：前 50 万字符/月赠金覆盖；超出后 `$20 / 百万字符` | 按字符 |
| Google Cloud Translation LLM | TextTranslation (LLM)：输入 `$10 / 百万字符`，输出 `$10 / 百万字符` | 输入/输出分别计费 |
| DeepL API Pro | `$5.49 / 月` + `$25 / 百万字符` | 月费 + 按字符 |
| OpenRouter | 按模型的 input/output token 计费 | 按 token |

## 3. OpenRouter 推荐模型价格（直观换算）

### 3.1 原始计费（官方模型列表口径）

| 模型 | 输入价（USD / 1M tokens） | 输出价（USD / 1M tokens） | 输入100万+输出100万 tokens 总价 |
|---|---:|---:|---:|
| `meta-llama/llama-3.3-70b-instruct` | 0.10 | 0.32 | 0.42 |
| `google/gemini-2.5-flash-lite` | 0.10 | 0.40 | 0.50 |
| `qwen/qwen-2.5-72b-instruct` | 0.12 | 0.39 | 0.51 |
| `openai/gpt-4o-mini` | 0.15 | 0.60 | 0.75 |
| `google/gemini-2.5-flash` | 0.30 | 2.50 | 2.80 |
| `anthropic/claude-3.5-haiku` | 0.80 | 4.00 | 4.80 |

### 3.2 按“每百万字符”估算（便于和 Google/DeepL 对齐）

由于 OpenRouter 是 token 计费，和“按字符计费”不是同一口径。为了直观对比，这里给两个常用估算场景：

- 场景 A（中文为主）：`1 字符 ≈ 1 token`，且输入输出字符量接近
- 场景 B（英文为主）：`1 token ≈ 4 字符`

| 模型 | 场景A：约成本（USD / 百万字符输入+百万字符输出） | 场景B：约成本（USD / 百万字符输入+百万字符输出） |
|---|---:|---:|
| `meta-llama/llama-3.3-70b-instruct` | 0.42 | 0.105 |
| `google/gemini-2.5-flash-lite` | 0.50 | 0.125 |
| `qwen/qwen-2.5-72b-instruct` | 0.51 | 0.1275 |
| `openai/gpt-4o-mini` | 0.75 | 0.1875 |
| `google/gemini-2.5-flash` | 2.80 | 0.70 |
| `anthropic/claude-3.5-haiku` | 4.80 | 1.20 |

## 4. 选型建议（成本 + 稳定性）

### 预算敏感型站点（内容量大）

优先：

- `gpt-4o-mini`
- `gemini-2.5-flash-lite`
- `qwen-2.5-72b-instruct`

原因：单位成本低，翻译质量通常可满足资讯/电商详情页等中等质量场景。

### 质量优先型站点（品牌页、法律页）

可考虑：

- `gemini-2.5-flash`
- `claude-3.5-haiku`

原因：通常语义一致性和可读性更好，但单价上升明显。

### 强并发 + 成本封顶

可考虑：

- 三方中转平台（单 key，接入快）
- 同时启用每日字符上限，避免超预算

## 5. 重要口径提醒

1. OpenRouter 与 Google/DeepL 的计费单位不同，必须先统一口径再比价。
2. 翻译任务的输入输出长度不总是 1:1，长文本重写会拉高输出成本。
3. 三方中转报价可能随活动变化，且存在平台服务费差异。

## 6. 数据来源

- Google Cloud Translation Pricing: <https://cloud.google.com/translate/pricing>
- DeepL API Pricing: <https://www.deepl.com/en/pro-api>
- OpenRouter Models API: <https://openrouter.ai/api/v1/models>
- 三方中转示例首页: <https://tb.trans-home.com/>

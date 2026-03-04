# Pricing Comparison (Google / DeepL / OpenRouter / Third-Party Proxy)

Last updated: **2026-03-04**

This page is for planning and technical selection, not a price guarantee.
Always verify final billing on provider pages before purchasing.

## 1. Quick Comparison Table

The table below follows an easy-to-scan structure:

- discounted price
- reference original price
- discount ratio
- concurrency

| Engine | Discounted Price (CNY / 1M chars) | Discount Ratio | Reference Original Price | Concurrency (label) |
|---|---:|---:|---:|---|
| DeepL (proxy) | CNY 60 | 3.5x discount | $25 / 1M chars | 5-1000 (scalable) |
| Google Translate (proxy) | CNY 60 | 4.2x discount | $20 / 1M chars | 5-1000 (scalable) |
| Baidu Translate (proxy) | CNY 25 | 5x discount | CNY 50 / 1M chars | 5-1000 (scalable) |
| Youdao Translate (proxy) | CNY 25 | 5x discount | CNY 50 / 1M chars | 5-1000 (scalable) |
| Microsoft Translate (proxy) | CNY 60 | 8.5x discount | $10 / 1M chars | 5-1000 (scalable) |
| Volcano Translate (proxy) | CNY 25 | 5x discount | CNY 50 / 1M chars | 5-1000 (scalable) |
| ChatGPT Translate (proxy) | CNY 50 | 5x discount | CNY 100 / 1M chars | 5-1000 (scalable) |
| Yandex Translate (proxy) | CNY 25 | 8.3x discount | CNY 30 / 1M chars | 5-1000 (scalable) |

Note:

- The proxy table is derived from a public sample page: `tb.trans-home.com` (captured on 2026-03-04).
- This sample is documentation-only, with no commercial affiliation.

## 2. Official API Baseline Pricing

| Provider | Core Price Items | Billing Unit |
|---|---|---|
| Google Cloud Translation | NMT: free-credit coverage for first 500k chars/month; then `$20 / 1M chars` | Per character |
| Google Cloud Translation LLM | TextTranslation (LLM): input `$10 / 1M chars`, output `$10 / 1M chars` | Input/output billed separately |
| DeepL API Pro | `$5.49 / month` + `$25 / 1M chars` | Monthly fee + per character |
| OpenRouter | Depends on model input/output token pricing | Per token |

## 3. OpenRouter Model Price Snapshot

### 3.1 Raw token-based pricing

| Model | Input (USD / 1M tokens) | Output (USD / 1M tokens) | Total (1M in + 1M out) |
|---|---:|---:|---:|
| `meta-llama/llama-3.3-70b-instruct` | 0.10 | 0.32 | 0.42 |
| `google/gemini-2.5-flash-lite` | 0.10 | 0.40 | 0.50 |
| `qwen/qwen-2.5-72b-instruct` | 0.12 | 0.39 | 0.51 |
| `openai/gpt-4o-mini` | 0.15 | 0.60 | 0.75 |
| `google/gemini-2.5-flash` | 0.30 | 2.50 | 2.80 |
| `anthropic/claude-3.5-haiku` | 0.80 | 4.00 | 4.80 |

### 3.2 Approximate per-character conversion

OpenRouter is token-based, so this is only an estimate for side-by-side comparison:

- Scenario A (CJK-heavy): `1 char ~ 1 token`
- Scenario B (English-heavy): `1 token ~ 4 chars`

| Model | Scenario A (USD / 1M input chars + 1M output chars) | Scenario B (USD / 1M input chars + 1M output chars) |
|---|---:|---:|
| `meta-llama/llama-3.3-70b-instruct` | 0.42 | 0.105 |
| `google/gemini-2.5-flash-lite` | 0.50 | 0.125 |
| `qwen/qwen-2.5-72b-instruct` | 0.51 | 0.1275 |
| `openai/gpt-4o-mini` | 0.75 | 0.1875 |
| `google/gemini-2.5-flash` | 2.80 | 0.70 |
| `anthropic/claude-3.5-haiku` | 4.80 | 1.20 |

## 4. When AI Beats Google Translate v2 / DeepL

| Scenario | Better Choice | Why AI can be better | Cost Strategy |
|---|---|---|---|
| Marketing landing pages | AI | Better style/tone control and conversion wording | Use AI only on high-value pages |
| Technical docs with glossary constraints | AI | Better terminology control via prompts | Provide glossary rules before batch translation |
| User-generated noisy text | AI | Better handling of slang/typos/context | Use low temperature and review sensitive content |
| UI microcopy | AI | Better short-context phrasing | Keep placeholder protection rules |
| Large low-risk catalog content | Google v2 / DeepL | Throughput and predictable MT behavior are often enough | MT first, AI post-edit only where needed |

## 5. Selection Guidelines

- Cost-sensitive, high-volume sites:
  - start with `gpt-4o-mini`, `gemini-2.5-flash-lite`, or `qwen-2.5-72b-instruct`
- Quality-sensitive pages (brand/legal):
  - consider `gemini-2.5-flash` or `claude-3.5-haiku`
- Hard budget cap + operational simplicity:
  - use a proxy API route and TranslatePress daily character limit together

## 6. Source Links

- Google Cloud Translation Pricing: <https://cloud.google.com/translate/pricing>
- DeepL API Pricing: <https://www.deepl.com/en/pro-api>
- OpenRouter Models API: <https://openrouter.ai/api/v1/models>
- Proxy sample page (example only, no commercial affiliation): <https://tb.trans-home.com/>

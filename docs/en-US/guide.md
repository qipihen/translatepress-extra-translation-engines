# Setup and Usage Guide: TranslatePress Extra Translation Engines

`TranslatePress` is a WordPress multilingual plugin.
This plugin extends TranslatePress automatic translation by adding additional engines.

## 1. Prerequisites

- WordPress installed and running
- TranslatePress installed and activated
- This extension plugin installed and activated
- Valid credentials for at least one translation provider

## 2. Installation

1. Download the plugin release zip (recommended naming: `translatepress-extra-translation-engines-v0.3.1.zip`).
2. In WordPress admin, open `Plugins -> Add New -> Upload Plugin`.
3. Upload the zip and activate `TranslatePress Extra Translation Engines`.
4. Go to `TranslatePress -> Automatic Translation`.

## 3. Configuration Location

Main page:

- `TranslatePress -> Automatic Translation`

Main controls:

- `Enable Automatic Translation`
- `Alternative Engines` selector
- Engine-specific settings rendered below the selector

## 4. Engine Overview

### OpenRouter / OpenAI Compatible

Required:

- `OpenRouter API Key`
- `Model` (example: `openai/gpt-4o-mini`)

Optional:

- `Base URL` (default: `https://openrouter.ai/api/v1`)
- `Completions Path` (default: `/chat/completions`)
- `Temperature` (recommended: `0` for translation)
- `Chunk Size`
- `Language Whitelist`
- `Site URL / Site Name`

### Third-Party Proxy API (Single Token, Example)

Required:

- `Proxy API Token`

Defaults:

- `Base URL`: `https://tb.trans-home.com` (example only)
- `Batch API Path`: `/api/index/translateBatch`
- `Content Type (Plain text / HTML)`: dropdown field

Important:

- The sample endpoint/domain is for protocol demonstration only.
- No commercial affiliation or sponsorship is implied.

### Youdao Translate API

Required:

- `Youdao AppKey`
- `Youdao AppSecret`

### Baidu Translate API

Required:

- `Baidu AppID`
- `Baidu AppSecret`

### Tencent Cloud TMT

Required:

- `Tencent SecretId`
- `Tencent SecretKey`

Optional:

- `Region` (default: `ap-guangzhou`)
- `ProjectId`
- `Session Token`

### Aliyun Machine Translation

Required:

- `Aliyun AccessKeyId`
- `Aliyun AccessKeySecret`

Optional:

- `RegionId` (default: `cn-hangzhou`)
- `Scene` (default: `general`)

## 5. Recommended Starting Profiles

### OpenRouter profile (balanced quality/cost)

- `Temperature = 0`
- `Chunk Size = 20`
- Start with `openai/gpt-4o-mini` or `google/gemini-2.5-flash-lite`

### Third-party proxy profile (safe rollout)

- Start with `Content Type = Plain text`
- Switch to `Content Type = HTML` only after initial validation
- Roll out high-value pages first, then full site

## 6. Test API Credentials

Use the built-in `Test API credentials` button.

If validation fails:

1. Verify key/token and endpoint values.
2. Confirm balance/quota on your provider account.
3. Lower batch size/chunk size temporarily.
4. Check WordPress debug log (`WP_DEBUG_LOG`).

## 7. Troubleshooting Visibility

If new engines are not visible:

1. Confirm the plugin is activated.
2. Reload `TranslatePress -> Automatic Translation`.
3. Append `&trp_or_debug=1` to page URL and check notice:
   - `[TPOR 0.3.1] loaded. trp_machine_translation_engines ...`
4. If debug notice contains the engine values but dropdown still does not show them, clear plugin/site/browser cache.

## 8. FAQ

### Why do some engines require two credentials?

Those providers use signed requests (for example, AppKey + AppSecret).

### I only have one API key/token. Which engine should I use?

Use:

- `OpenRouter / OpenAI Compatible`, or
- `Third-Party Proxy API (Single Token, Example)`

### Does this plugin modify TranslatePress core files?

No. It extends TranslatePress through hooks and compatibility loader integration.

## 9. When AI Translation Is Better Than Google Translate v2 / DeepL

| Scenario | Better choice | Why AI often wins | Recommendation |
|---|---|---|---|
| Marketing pages and CTA text | AI | Better tone and brand style control | Use AI for revenue-critical pages |
| Product docs with strict terminology | AI | Prompt rules can enforce term consistency | Use glossary instructions |
| User-generated noisy text | AI | Better robustness for slang and typos | Keep temperature low; review sensitive output |
| UI microcopy | AI | Better context-aware short phrasing | Translate with context batches |
| Large low-risk catalog text | Google v2 / DeepL | Style is less critical; throughput matters | MT first, selective AI post-edit |

Quick rule:

- Choose AI for style, tone, context, terminology control.
- Choose Google v2 / DeepL for predictable high-volume low-cost throughput.

## 10. Related Docs

- English index: [`README.md`](README.md)
- Third-party proxy details: [`third-party-proxy-guide.md`](third-party-proxy-guide.md)
- Pricing comparison: [`pricing-comparison.md`](pricing-comparison.md)
- Developer extension guide: [`extension-guide.md`](extension-guide.md)

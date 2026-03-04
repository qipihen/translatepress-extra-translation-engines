# TranslatePress Extra Translation Engines - English Guide

This guide explains how to use this plugin to extend TranslatePress automatic translation with:

- OpenRouter / OpenAI-compatible APIs
- Domestic cloud translation APIs
- Single-token third-party proxy APIs (like trans-home style endpoints)

## 1. Where to configure

WordPress admin path:

- `TranslatePress -> Automatic Translation`

Main controls:

- `Enable Automatic Translation`
- `Alternative Engines` selector
- Engine-specific credentials below the selector

## 2. Quick setup

## OpenRouter / OpenAI Compatible

Required:

- `OpenRouter API Key`
- `Model` (for example: `openai/gpt-4o-mini`)

Optional:

- `Base URL` (default: `https://openrouter.ai/api/v1`)
- `Completions Path` (default: `/chat/completions`)
- `Temperature` (recommended: `0` for translation)
- `Chunk Size`
- `Language Whitelist`

## Trans Home Proxy API (single token)

Required:

- `Trans Home Token`

Defaults (usually keep them as is):

- `Base URL`: `https://tb.trans-home.com`
- `Batch API Path`: `/api/index/translateBatch`
- `MIME Type`: `0` (plain text), `1` (HTML)

## 3. When AI translation is better than Google Translate v2 / DeepL

This is the practical decision table.

| Scenario | Better choice | Why AI usually wins over Google Translate v2 / DeepL | Recommendation |
|---|---|---|---|
| Marketing pages, ad copy, CTA blocks | AI | Better tone control, brand voice consistency, and conversion-focused phrasing | Use AI for money pages, MT for low-value pages |
| Product docs with strict terminology | AI | Prompt rules can enforce glossary terms and banned term handling | Add terminology instructions before batch translation |
| User-generated content (slang, typos, mixed style) | AI | Better robustness on noisy text and colloquial language | Keep temperature low and review sensitive content |
| UI microcopy (buttons, empty states, tooltips) | AI | Better contextual wording, fewer literal-but-awkward outputs | Translate in context batches, not isolated strings |
| Bulk low-risk catalog text | Google v2 / DeepL | Style is less important; throughput and predictable MT behavior are enough | Use MT first, then selectively post-edit with AI |

Short rule:

- Choose AI when you need style, tone, context, or terminology control.
- Choose Google Translate v2 / DeepL when you need predictable high-volume low-cost throughput.

## 4. Troubleshooting

If engines do not show up:

1. Make sure the plugin is activated.
2. Open `TranslatePress -> Automatic Translation`.
3. Append `&trp_or_debug=1` to the URL and verify a notice like:
   - `[TPOR 0.3.0] loaded. trp_machine_translation_engines ...`

If API test fails:

1. Verify key/token and endpoint path.
2. Check provider balance and quota.
3. Temporarily reduce batch size/chunk size.
4. Check WordPress debug logs.

## 5. Pricing references

- Google Cloud Translation Pricing: <https://cloud.google.com/translate/pricing>
- DeepL API Pricing: <https://www.deepl.com/en/pro-api>
- OpenRouter Models: <https://openrouter.ai/api/v1/models>
- Third-party proxy sample: <https://tb.trans-home.com/>


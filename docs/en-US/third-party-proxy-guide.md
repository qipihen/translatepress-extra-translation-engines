# Third-Party Proxy API Guide (Single Token, Example)

Use this mode when your provider gives you a single token, not official provider credentials such as AppKey + AppSecret.

Example documentation style:

- `https://tb.trans-home.com/index/index/api`
- Batch endpoint pattern: `/api/index/translateBatch?token=...`

Important:

- The endpoint above is an API protocol example only.
- No commercial affiliation or sponsorship is implied.

## 1. How to Configure in WordPress

Path:

- `TranslatePress -> Automatic Translation`

Steps:

1. Select `Third-Party Proxy API (Single Token, Example)` in `Alternative Engines`.
2. Enter `Proxy API Token`.
3. Save settings and click `Test API credentials`.

Default values (usually valid for the example protocol):

- `Base URL`: `https://tb.trans-home.com` (example only)
- `Batch API Path`: `/api/index/translateBatch`
- `Content Type (Plain text / HTML)`: start with `Plain text`

## 2. Request Format Used by the Plugin

Request URL:

```text
{Base URL}{Batch API Path}?token={Proxy API Token}
```

Request body example:

```json
{
  "keywords": ["about", "contact"],
  "targetLanguage": "zh-cn",
  "sourceLanguage": "en",
  "mimeType": 0
}
```

## 3. Why This Cannot Reuse Official Youdao/Baidu Engines

The signing/authentication model is different:

- Official providers usually require signed requests (`appKey/appSecret + sign + salt + timestamp`).
- Third-party proxy APIs often use token + simplified body.

Because the protocol differs, this must be implemented as a dedicated engine class.

## 4. Response Compatibility in This Plugin

Current parser supports:

- `data.text` as string
- `data.text` as array
- `data` directly as array

If expected batch count does not match returned items, the request is treated as failed to avoid translation misalignment.

## 5. FAQ

### I only have one key. Where do I put it?

Put it in `Proxy API Token`. Do not use Youdao/Baidu dual-credential fields.

### Translation success is unstable. What should I do?

1. Lower chunk size (filter-based tuning).
2. Check provider quota/balance.
3. Verify provider-side concurrency and rate limits.

### How can I reduce batch size?

Add this filter in theme or custom plugin:

```php
add_filter('trp_transhome_chunk_size', function () {
    return 10;
});
```

## 6. Adapting Other Proxy Providers

You can reuse the same pattern for another provider:

- Put token in query/header based on provider spec.
- Build request body according to provider protocol.
- Register a dedicated translator class and engine metadata.

Developer details:

- [`extension-guide.md`](extension-guide.md)

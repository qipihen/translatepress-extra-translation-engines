# 三方中转 API 接入说明（单 Token 模式）

适用场景：你从中转平台拿到的是**单个 token**，而不是官方云厂商的 `AppKey + AppSecret` 双凭据。

例如你提供的这类接口文档：

- `https://tb.trans-home.com/index/index/api`
- 批量接口：`/api/index/translateBatch?token=...`

本插件对应引擎：`Trans Home Proxy API`（engine value: `transhome_translate`）。

## 1. 后台怎么填

在 `TranslatePress -> Automatic Translation`：

1. `Alternative Engines` 选择 `Trans Home Proxy API`
2. 填 `Trans Home Token`
3. 保存，点 `Test API credentials`

默认参数（通常可直接使用）：

- `Base URL`：`https://tb.trans-home.com`
- `Batch API Path`：`/api/index/translateBatch`
- `MIME Type`：`0`

## 2. 请求协议（插件内实现）

插件会向以下地址发送 POST：

```text
{Base URL}{Batch API Path}?token={Trans Home Token}
```

请求体 JSON（示例）：

```json
{
  "keywords": ["about", "contact"],
  "targetLanguage": "zh-cn",
  "sourceLanguage": "en",
  "mimeType": 0
}
```

## 3. 为什么这类平台不能复用 Youdao/Baidu 官方引擎

因为签名机制不同：

- 官方 Youdao/Baidu：通常要求 `appKey/appSecret + sign + salt + timestamp`
- 三方中转：常见是 `token + 简化 body`

接口协议不同，就必须走独立引擎类，不能混填参数。

## 4. 返回数据兼容策略

插件对三方中转返回做了兼容：

- 支持 `data.text` 为字符串
- 支持 `data.text` 为数组
- 支持 `data` 直接为数组
- 批量数量不一致时自动判失败，避免错位写库

## 5. 常见问题

## Q1：只有一个 key，填哪里？

填 `Trans Home Token`，不要填 Youdao/Baidu 的双字段。

## Q2：翻译成功率不稳定怎么办？

- 先把 `Chunk Size` 降低（通过过滤器）
- 检查 token 余额与并发限制
- 查看目标平台是否限流

## Q3：如何调小分批大小？

在主题或功能插件中加：

```php
add_filter('trp_transhome_chunk_size', function () {
    return 10;
});
```

## 6. 适配其他三方中转平台

如果不是 trans-home，也可复用同模式：

- 单 key 放 query/header
- body 按平台协议拼接
- 写一个新的 translator class 并注册 engine

完整扩展步骤见：[`extension-guide.md`](extension-guide.md)

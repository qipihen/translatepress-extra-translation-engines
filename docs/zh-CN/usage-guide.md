# 使用文档：TranslatePress Extra Translation Engines

本文是完整的中文使用手册，适用于你要把 TranslatePress 自动翻译从官方 API 扩展到 OpenRouter、国内 API、以及三方中转 API 的场景。

## 1. 后台入口

WordPress 后台：`TranslatePress -> Automatic Translation`

关键位置：

- `Enable Automatic Translation`：开启自动翻译
- `Alternative Engines`：切换翻译引擎
- 下方参数区：填写引擎凭据和高级设置

## 2. 引擎与参数总览

### OpenRouter / OpenAI Compatible

- `OpenRouter API Key`：必填
- `Model`：必填（示例 `openai/gpt-4o-mini`）
- `Base URL`：默认 `https://openrouter.ai/api/v1`
- `Completions Path`：默认 `/chat/completions`
- `Temperature`：建议 `0`
- `Chunk Size`：默认 `20`
- `Language Whitelist`：可选，逗号分隔（例如 `en,zh-cn,ja`）
- `Site URL / Site Name`：可选

### Trans Home Proxy API（单 Token）

- `Trans Home Token`：必填
- `Base URL`：默认 `https://tb.trans-home.com`
- `Batch API Path`：默认 `/api/index/translateBatch`
- `MIME Type`：`0` 纯文本，`1` HTML

### Youdao Translate API（官方）

- `Youdao AppKey`：必填
- `Youdao AppSecret`：必填

### Baidu Translate API（官方）

- `Baidu AppID`：必填
- `Baidu AppSecret`：必填

### Tencent Cloud TMT（官方）

- `Tencent SecretId`：必填
- `Tencent SecretKey`：必填
- `Region`：默认 `ap-guangzhou`
- `ProjectId / Session Token`：可选

### Aliyun Machine Translation（官方）

- `Aliyun AccessKeyId`：必填
- `Aliyun AccessKeySecret`：必填
- `RegionId`：默认 `cn-hangzhou`
- `Scene`：默认 `general`

## 3. 推荐配置（先稳定再提速）

## OpenRouter 推荐

- `Temperature = 0`
- `Chunk Size = 20`
- 模型先用 `openai/gpt-4o-mini` 或 `google/gemini-2.5-flash-lite`

## 三方中转推荐

- `MIME Type = 0`（先验证纯文本）
- 翻译 HTML 内容时再改 `1`
- 接口稳定后再放开站点全量自动翻译

## 4. API 测试与故障排查

点 `Test API credentials`，如果失败：

1. 先看 WordPress Debug Log（建议开启 `WP_DEBUG_LOG`）
2. 检查 Base URL / API Path 是否与你的服务商文档一致
3. 检查 token 或 key 是否有可用余额/权限
4. 将 `Chunk Size` 临时降到 `5` 再试

## 5. “不显示引擎”排查

如果你看不到新增引擎：

1. 确认插件已激活
2. 刷新 `TranslatePress -> Automatic Translation`
3. 在 URL 加 `&trp_or_debug=1`，应出现 debug 提示：
   - `[TPOR 0.3.0] loaded. trp_machine_translation_engines ...`
4. 若提示中已包含引擎值（如 `openrouter,transhome_translate`），但下拉没有，优先清缓存与浏览器缓存

## 6. 生产环境建议

- 开启每日翻译字符上限（TranslatePress 原生设置）
- 首先翻译高价值页面（首页、产品页、落地页）
- 对品牌词和术语进行人工校验
- 每月复盘字符消耗与模型单价，动态调整引擎

## 7. 与 SEO 的关系

自动翻译只是起点。要拿到自然搜索流量：

- 标题、描述、H1/H2 需要人工二次优化
- URL slug 建议开启并检查自动翻译质量
- 对核心页面做关键词本地化（不是逐字直译）

更多价格与选型建议：[`pricing-comparison.md`](pricing-comparison.md)

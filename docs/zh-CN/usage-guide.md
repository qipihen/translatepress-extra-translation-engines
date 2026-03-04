# 使用文档：TranslatePress Extra Translation Engines

本文是完整的中文使用手册，适用于你要把 TranslatePress 自动翻译从官方 API 扩展到 OpenRouter、国内 API、以及三方中转 API 的场景。

`TranslatePress` 是 WordPress 多语言插件；本项目是它的自动翻译引擎扩展插件。

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

### Third-Party Proxy API（单 Token，仅示例）

- `Proxy API Token`：必填
- `Base URL`：默认 `https://tb.trans-home.com`（仅示例）
- `Batch API Path`：默认 `/api/index/translateBatch`
- `内容类型（纯文本/HTML）`：下拉选择 `纯文本` 或 `HTML`

说明：示例域名仅用于接口协议演示，不代表任何商业合作。

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

- `内容类型 = 纯文本`（先验证稳定性）
- 翻译 HTML 内容时再切换 `内容类型 = HTML`
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
   - `[TPOR 0.3.1] loaded. trp_machine_translation_engines ...`
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

## 8. 哪些场景 AI 翻译优于 Google Translate v2 / DeepL

这部分给你一个可执行的判断标准，不是“AI 一定更好”。

| 场景 | AI 翻译优势 | Google Translate v2 常见短板 | DeepL 常见短板 | 建议 |
|---|---|---|---|---|
| 营销文案（Banner、Slogan、CTA） | 可控语气和转化导向，能按品牌语调改写 | 往往直译，营销语气偏弱 | 可读性好于 v2，但语气仍偏中性 | 用 AI 引擎（低温度）先出稿，再人工审校 |
| SaaS/APP UI 微文案（按钮、提示、空状态） | 可结合上下文避免“字面正确、语义错误” | 脱离上下文时误译概率更高 | 上下文不足时也会偏字面 | 批量带上下文翻译，保留术语表 |
| 产品说明+术语约束（禁译词、固定译法） | 可在提示词中强约束术语一致性 | 术语一致性难控 | 术语一致性较好但规则可控性不足 | AI + 术语清单；上线前做术语 diff |
| 用户评论/口语/错别字文本 | 对非规范输入容错更强 | 口语和缩写理解波动大 | 非规范输入有时会过度“正则化” | AI 优先，必要时追加“保留原语气”约束 |
| 跨句依赖长文本（帮助中心、博客） | 上下文连续性更好，代词与指代更稳 | 句子级翻译，跨句衔接弱 | 长文较稳但风格控制弱 | AI 分段翻译 + 低温度 + 人工抽样 |
| 带变量占位符内容（`{name}`、`%s`、HTML） | 可通过规则提示避免破坏占位符 | 占位符偶发错位 | 占位符稳定性较好但仍需检查 | AI 前加“占位符不可改”规则，回归测试占位符 |

反过来，以下场景通常不需要 AI：

- 纯信息性、低风险、超大批量文本（例如商品参数表）
- 对风格要求很低，优先考虑最低单价和最高吞吐

这类任务可优先 Google Translate v2 或 DeepL，再按页面价值决定是否升级为 AI 翻译。

# 扩展说明：如何新增一个 TranslatePress 翻译引擎

这份文档给开发者，目标是把新翻译供应商快速接入本插件（不改 TranslatePress 核心）。

## 1. 架构概览

本插件通过以下钩子把引擎注入 TranslatePress：

- `trp_machine_translation_engines`
- `trp_automatic_translation_engines_classes`
- `trp_machine_translation_extra_settings_middle`
- `trp_machine_translation_sanitize_settings`
- `trp_get_default_trp_machine_translation_settings`

入口文件：`translatepress-openrouter-engine.php`

## 2. 新增引擎的 6 个步骤

## 步骤 1：新建 translator 类

路径建议：`includes/class-trp-xxx-machine-translator.php`

要求：

- `class TRP_Xxx_Machine_Translator extends TRP_Machine_Translator`
- 至少实现 `translate_array()`
- 建议实现 `check_api_key_validity()` 以支持“Test API credentials”

## 步骤 2：在主文件加载 class

在 `trp_or_include_engine_classes()` 中 `require_once` 新文件。

## 步骤 3：注册 engine 元信息

在 `trp_or_get_custom_engines()` 增加：

- `value`（唯一 ID）
- `label`（后台显示名）
- `class`（类名）

## 步骤 4：渲染后台设置项

新增 `trp_or_render_xxx_settings()`，通过
`trp_machine_translation_extra_settings_middle` 输出参数表单。

## 步骤 5：新增 sanitize 规则 + 默认值

在 `trp_or_sanitize_settings()` 添加字段规则；
在 `trp_or_default_mt_settings()` 添加默认值。

## 步骤 6：错误码与日志

建议：

- 对 400/401/404/429 做清晰文案
- 每次请求写 `machine_translator_logger`
- 失败时可做短暂 throttle，防止打爆配额

## 3. 单 Key 中转平台模板

如果你接的是“单 token + 批量接口”平台，可参考 `TRP_Transhome_Machine_Translator`：

- token 读取：`get_api_key()`
- endpoint 组装：`get_batch_endpoint()`
- body 组装：`send_batch_request()`
- 结果解析：`parse_batch_response()`

这个模式适合大多数“第三方翻译聚合平台”。

## 4. 语言码策略

建议在 translator 类内做 `map_language_code()`：

- 先处理常见特例（`zh_CN -> zh-cn`）
- 再 fallback 到 TP 的 ISO code
- 对 source 可选支持 `auto`

## 5. 安全建议

- 永远不要在日志里输出完整密钥
- 后台字段使用 `sanitize_text_field` / `esc_url_raw`
- path 参数只允许站内 path 格式（例如 `/chat/completions`）

## 6. 兼容性建议

为了兼容不同 TP 版本加载顺序，当前实现同时：

- 直接 `add_filter/add_action`
- 通过 `trp_before_running_hooks` 注入 loader

新增引擎时保持这个模式，不要只保留一种注册路径。

## 7. 回归检查清单

- 下拉框可看到新引擎
- 参数保存后不丢失
- `Test API credentials` 能报成功/失败
- 自动翻译能写入 TP 翻译表
- 429/限流时不会持续刷请求

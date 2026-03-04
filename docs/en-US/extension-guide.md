# Extension Guide: Add a New TranslatePress Translation Engine

This guide is for developers who want to integrate a new translation provider into this plugin, without modifying TranslatePress core files.

## 1. Architecture Overview

This plugin injects extra engines through these hooks:

- `trp_machine_translation_engines`
- `trp_automatic_translation_engines_classes`
- `trp_machine_translation_extra_settings_middle`
- `trp_machine_translation_sanitize_settings`
- `trp_get_default_trp_machine_translation_settings`

Entry file:

- `translatepress-openrouter-engine.php`

## 2. Six Steps to Add a New Engine

### Step 1: Create a translator class

Suggested file:

- `includes/class-trp-xxx-machine-translator.php`

Requirements:

- `class TRP_Xxx_Machine_Translator extends TRP_Machine_Translator`
- implement at least `translate_array()`
- recommended: implement `check_api_key_validity()` for `Test API credentials`

### Step 2: Load the class in the main plugin file

Add `require_once` in:

- `trp_or_include_engine_classes()`

### Step 3: Register engine metadata

Add to:

- `trp_or_get_custom_engines()`

Required fields:

- `value` (unique ID)
- `label` (admin UI label)
- `class` (PHP class name)

### Step 4: Render settings UI

Create:

- `trp_or_render_xxx_settings()`

Render it through:

- `trp_machine_translation_extra_settings_middle`

### Step 5: Add sanitize rules and defaults

Update:

- `trp_or_sanitize_settings()`
- `trp_or_default_mt_settings()`

### Step 6: Error handling and logging

Recommended:

- clear messages for 400/401/404/429
- log each request with `machine_translator_logger`
- add short throttle logic after repeated failures

## 3. Single-Token Proxy Template

For token + batch endpoint providers, use `TRP_Transhome_Machine_Translator` as template:

- token lookup: `get_api_key()`
- endpoint build: `get_batch_endpoint()`
- request body build: `send_batch_request()`
- response parser: `parse_batch_response()`

This pattern works for many third-party translation gateway providers.

## 4. Language Code Strategy

Implement `map_language_code()` in translator class:

1. handle known exceptions first (example: `zh_CN -> zh-cn`)
2. fallback to TranslatePress ISO code mapping
3. optionally support `auto` for source language

## 5. Security Recommendations

- never log full credentials
- sanitize fields with `sanitize_text_field` / `esc_url_raw`
- restrict path fields to provider API path format (example: `/chat/completions`)

## 6. Compatibility Recommendations

For broader TP version compatibility, keep both registration paths:

- direct `add_filter` / `add_action`
- loader injection via `trp_before_running_hooks`

Do not keep only one path if you want stable behavior across versions.

## 7. Regression Checklist

- engine appears in dropdown
- settings persist after save
- `Test API credentials` returns expected success/failure
- automatic translation writes correctly into TranslatePress tables
- 429/rate-limit scenarios do not trigger request loops

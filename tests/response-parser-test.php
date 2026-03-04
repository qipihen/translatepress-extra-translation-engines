<?php

require_once __DIR__ . '/../includes/class-trp-openrouter-response-parser.php';

function assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_parse_json_object_with_translations() {
    $payload = '{"translations":["hello","world"]}';
    $parsed = TRP_OpenRouter_Response_Parser::parse_translations($payload, 2);
    assert_true($parsed === array('hello', 'world'), 'Expected translations array from object payload.');
}

function test_parse_markdown_codeblock_json_array() {
    $payload = "```json\n[\"bonjour\",\"monde\"]\n```";
    $parsed = TRP_OpenRouter_Response_Parser::parse_translations($payload, 2);
    assert_true($parsed === array('bonjour', 'monde'), 'Expected translations array from markdown codeblock.');
}

function test_parse_items_payload() {
    $payload = '{"items":[{"id":1,"translation":"hola"},{"id":0,"translation":"mundo"}]}';
    $parsed = TRP_OpenRouter_Response_Parser::parse_translations($payload, 2);
    assert_true($parsed === array('mundo', 'hola'), 'Expected sorted item-based translations.');
}

function test_invalid_count_returns_empty() {
    $payload = '["only one"]';
    $parsed = TRP_OpenRouter_Response_Parser::parse_translations($payload, 2);
    assert_true($parsed === array(), 'Expected empty array for unexpected count.');
}

$tests = array(
    'test_parse_json_object_with_translations',
    'test_parse_markdown_codeblock_json_array',
    'test_parse_items_payload',
    'test_invalid_count_returns_empty',
);

foreach ($tests as $test) {
    $test();
}

echo "All parser tests passed.\n";

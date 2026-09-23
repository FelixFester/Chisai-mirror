<?php
/**
 * test/mock_llamacpp_server.php
 *
 * A tiny stand-in for llama.cpp's `llama-server`, matching the shape of
 * its OpenAI-compatible /v1/chat/completions response. Lets you test
 * llamacpp_chat() over a real HTTP round trip without building
 * llama.cpp or downloading a model. Not part of the app itself.
 *
 * Usage:
 *
 *   php -S 127.0.0.1:8081 test/mock_llamacpp_server.php
 *
 * Then in setup.php choose the llama.cpp backend and set the server
 * URL to http://127.0.0.1:8081/v1/chat/completions
 *
 * The mock replies by echoing the last user message, so you can see
 * end to end that messages leave the app and replies come back.
 */

$raw = file_get_contents('php://input');
$request = json_decode($raw, true);
$last_user = '(no user message found in request)';
if (is_array($request)) {
    foreach (array_reverse($request['messages'] ?? array()) as $m) {
        if (($m['role'] ?? '') === 'user') {
            $last_user = (string) ($m['content'] ?? '');
            break;
        }
    }
}

header('Content-Type: application/json');
echo json_encode(array(
    'id' => 'chatcmpl-mock-' . substr(md5(uniqid('', true)), 0, 8),
    'object' => 'chat.completion',
    'created' => time(),
    'model' => $request['model'] ?? 'mock-llamacpp',
    'choices' => array(
        array(
            'index' => 0,
            'message' => array(
                'role' => 'assistant',
                'content' => '[mock llama-server] You said: ' . $last_user,
            ),
            'finish_reason' => 'stop',
        ),
    ),
    'usage' => array(
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
    ),
));

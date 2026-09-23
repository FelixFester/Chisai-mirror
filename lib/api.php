<?php
/**
 * api.php
 *
 * The one and only chat backend: a llama.cpp server (`llama-server`),
 * run locally or on a box you control -- no API key needed by default,
 * no external service dependency at all once the model is downloaded.
 *
 * llama-server speaks the OpenAI-style /v1/chat/completions shape, so
 * the payload building and response parsing here stay portable to any
 * other OpenAI-compatible server later, should one ever be added back.
 *
 * HTTP transports: the request goes out through the curl extension
 * when PHP has it, otherwise through PHP's own stream wrappers
 * (allow_url_fopen). That way the app still works on minimal local
 * PHP installs (e.g. a distro PHP without php-curl) instead of dying
 * with "Call to undefined function curl_init()".
 */

define('LLAMACPP_DEFAULT_ENDPOINT', 'http://127.0.0.1:8080/v1/chat/completions');

/**
 * Ask the llama.cpp server for a chat reply. This is the only
 * function the rest of the app needs to call.
 *
 * Old configs that still carry keys from removed features (backend,
 * api_key, ...) are simply ignored -- no migration needed.
 *
 * @param array $messages  Array of ["role" => ..., "content" => ...]
 * @param array $config    The full config array from get_config()
 * @return array ["ok" => bool, "reply" => string, "error" => string]
 */
function ai_chat($messages, $config) {
    // '?? ""' guards against hand-edited / older configs that lack
    // the key -- an undefined-index warning here would also spew
    // output before the POST redirect and break it.
    $endpoint = trim((string) ($config['llamacpp_endpoint'] ?? ''));
    if ($endpoint === '') {
        $endpoint = LLAMACPP_DEFAULT_ENDPOINT;
    }
    // Seconds to wait for the reply. CPU-only boxes are slow: the
    // default 120 s covers e.g. a 300-token reply at ~7 tokens/s.
    // Hand-edit data/config.php ('llamacpp_timeout') to raise it.
    $timeout = (int) ($config['llamacpp_timeout'] ?? 120);
    if ($timeout < 10) {
        $timeout = 10;
    }
    if ($timeout > 3600) {
        $timeout = 3600;
    }
    return llamacpp_chat($messages, $endpoint, $config['model'] ?? null, $timeout);
}

/**
 * Is any HTTP transport usable in this PHP install?
 * Used by setup.php to warn early instead of failing on first message.
 */
function http_transport_available() {
    return function_exists('curl_init') || (bool) ini_get('allow_url_fopen');
}

/**
 * Hosts that must never be routed through a system HTTP proxy. A
 * llama.cpp server normally runs on THIS machine -- but PHP curl picks
 * up the http_proxy environment variable automatically, and would
 * happily send loopback traffic through it. That is the classic way to
 * get "the llama.cpp log shows the request completing, but PHP saw no
 * response at all": the proxy drops or mangles the reply.
 */
function url_is_loopback($url) {
    $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
    return in_array($host, array('127.0.0.1', 'localhost', '::1', '[::1]'), true);
}

/**
 * Turn a curl failure into a sentence that names the likely cause and
 * the next step. The bare curl text ("Empty reply from server") is
 * accurate but leaves the user guessing; the two cases worth
 * translating are timeouts (slow CPU inference is normal) and
 * connection refused (the server simply isn't running).
 */
function http_describe_curl_failure($errno, $errtext, $timeout, $elapsed) {
    if ($errno === 28) { // CURLE_OPERATION_TIMEDOUT
        return 'no reply within ' . $timeout . ' s -- on a slow CPU the model'
             . ' can still be generating server-side; raise "llamacpp_timeout"'
             . ' in data/config.php if this keeps happening -- ' . $errtext;
    }
    if ($errno === 7 || $errno === 6) { // COULDNT_CONNECT / RESOLVE_HOST
        return 'could not connect -- is the llama.cpp server running? Start'
             . ' it with something like: ~/.llama-app/llama serve -m model.gguf'
             . ' --port 8080 -- ' . $errtext;
    }
    if ($errno === 52 || $errno === 18 || $errno === 55 || $errno === 56) {
        // GOT_NOTHING / partial transfer / send / recv: the connection
        // died before a complete response made it back.
        return 'the connection was closed before a complete response arrived'
             . ' (after ' . $elapsed . ' s -- server crash/restart, or a system'
             . ' proxy interfering with local requests) -- ' . $errtext;
    }
    return 'curl error ' . $errno . ': ' . $errtext . ' (after ' . $elapsed . ' s)';
}

/**
 * POST a JSON string to $url, via curl when available, otherwise via
 * PHP stream wrappers.
 *
 * When curl fails at the transport level BEFORE any HTTP response, the
 * request is retried ONCE through the stream-wrapper transport (an
 * independent code path -- no libcurl, no proxy env). That is safe for
 * this app because every request goes to the local llama.cpp server:
 * worst case the model answers twice and the first answer is dropped.
 *
 * @return array ["ok" => bool, "body" => string, "status" => int,
 *               "error" => string, "errno" => int, "elapsed" => float]
 *               -- ok=false means transport-level failure (connection
 *               refused, no transport, ...); HTTP error statuses are
 *               returned with ok=true and let the caller inspect
 *               body/status.
 */
function http_post_json($url, $json, $headers = array(), $connect_timeout = 5, $timeout = 60) {
    if (function_exists('curl_init')) {
        // curl adds "Expect: 100-continue" by itself for bodies over
        // 1 KB and then waits up to a second for permission to send the
        // body. Small local servers don't need that round trip -- the
        // empty "Expect:" header suppresses it.
        $has_expect = false;
        foreach ($headers as $h) {
            if (stripos((string) $h, 'expect:') === 0) {
                $has_expect = true;
                break;
            }
        }
        if (!$has_expect) {
            $headers[] = 'Expect:';
        }

        $opts = array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            // Fail fast if the server simply isn't running (TCP connect
            // refused should not hang for the OS default ~2 minutes).
            CURLOPT_CONNECTTIMEOUT => $connect_timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $json,
        );
        // Never send loopback traffic through a proxy (see
        // url_is_loopback). CURLOPT_PROXY = '' disables the proxy for
        // this handle even when http_proxy is set in the environment.
        if (url_is_loopback($url)) {
            $opts[CURLOPT_PROXY] = '';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $opts);

        $response   = curl_exec($ch);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $elapsed    = round((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME), 1);
        curl_close($ch);

        // Two failure shapes to normalise:
        //   - PHP 8.x: curl_exec returns false on transport failure.
        //   - PHP 7.x: it can return '' when the connection died before
        //     any HTTP response arrived. Treating '' + status 0 as a
        //     success is exactly what produced the cryptic "closed
        //     before a full HTTP response arrived" error with no detail
        //     behind it on older PHP builds.
        if ($response === false || ($response === '' && $status === 0)) {
            $error = http_describe_curl_failure($curl_errno, $curl_error, $timeout, $elapsed);

            // Rescue attempt through the independent stream transport.
            $stream = http_post_json_streams($url, $json, $headers, $timeout);
            if ($stream['ok']) {
                return $stream;
            }
            $error .= ' (stream fallback also failed: ' . $stream['error'] . ')';
            return array('ok' => false, 'body' => '', 'status' => 0,
                'error' => $error, 'errno' => $curl_errno, 'elapsed' => $elapsed);
        }
        return array('ok' => true, 'body' => (string) $response, 'status' => $status,
            'error' => '', 'errno' => 0, 'elapsed' => $elapsed);
    }

    // ---- fallback transport: stream wrappers (no curl extension) ----
    return http_post_json_streams($url, $json, $headers, $timeout);
}

/**
 * Stream-wrapper HTTP POST used when the curl extension is missing.
 * Kept as its own function so it can be exercised directly by tests.
 *
 * @return array Same shape as http_post_json().
 */
function http_post_json_streams($url, $json, $headers, $timeout) {
    if (!ini_get('allow_url_fopen')) {
        return array(
            'ok' => false, 'body' => '', 'status' => 0,
            'error' => 'no HTTP transport available -- install the PHP curl extension'
                     . ' (e.g. "apt install php-curl") or enable allow_url_fopen in php.ini',
        );
    }

    // Content-Length must be explicit; many servers require it on POST.
    $headers[] = 'Content-Length: ' . strlen($json);
    $ctx = stream_context_create(array('http' => array(
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $json,
        'timeout'       => $timeout,
        // So 4xx/5xx responses come back as body instead of a warning+false.
        'ignore_errors' => true,
    )));

    $t0 = microtime(true);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        $elapsed = microtime(true) - $t0;
        // The stream wrapper's warning is generic ("HTTP request
        // failed!") and embeds the URL. A wall-clock elapsed time at or
        // over the limit means the 'timeout' option fired -- say so
        // explicitly, so "slow model" is distinguishable from "wrong
        // address" even without the curl extension.
        if ($elapsed >= $timeout) {
            $msg = 'request timed out after ' . $timeout . ' s';
        } else {
            $msg = 'request failed';
            $e = error_get_last();
            if (is_array($e) && !empty($e['message'])) {
                // PHP's warning reads like "file_get_contents(<url>):
                // Failed to open stream: <reason>" -- keep the reason.
                if (preg_match('/Failed to open stream: (.*)/s', $e['message'], $m)) {
                    $msg = $m[1];
                } else {
                    $msg = $e['message'];
                }
            }
            $msg .= ' (' . $url . ')';
        }
        return array('ok' => false, 'body' => '', 'status' => 0, 'error' => $msg,
            'errno' => 0, 'elapsed' => round(microtime(true) - $t0, 1));
    }

    $status = 0;
    // The status line is "HTTP/1.1 200 OK" -- code in the MIDDLE, not
    // at the end. The old regex (\s(\d{3})\s*$) only matched lines
    // WITHOUT a reason phrase, so every successful reply over this
    // transport looked like status 0 -- i.e. "connection closed before
    // a full HTTP response arrived" -- even when the model had just
    // answered perfectly. (This is exactly what users without the
    // curl extension saw on every single message.)
    if (isset($http_response_header[0])
            && preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return array('ok' => true, 'body' => $response, 'status' => $status, 'error' => '',
        'errno' => 0, 'elapsed' => 0);
}

/**
 * Shared request+response handling for the OpenAI-style chat
 * completions endpoint that llama-server exposes.
 *
 * @param string $url
 * @param array  $payload
 * @param array  $headers
 * @param int    $connect_timeout
 * @param int    $timeout
 * @param string $label            Human name used in error messages
 * @return array ["ok" => bool, "reply" => string, "error" => string]
 */
function openai_style_chat($url, $payload, $headers, $connect_timeout, $timeout, $label) {
    $json = json_encode($payload);
    if ($json === false) {
        return array('ok' => false, 'reply' => '', 'error' => 'Failed to encode request payload.');
    }

    $res = http_post_json($url, $json, $headers, $connect_timeout, $timeout);

    if (!$res['ok']) {
        // Transport-level failure: connection refused, timed out, no
        // transport at all. $res['error'] already carries the specifics
        // (curl error number + text + next step), so surface it verbatim.
        return array('ok' => false, 'reply' => '', 'error' => 'Could not reach ' . $label . ': ' . $res['error']);
    }

    // Transfer "succeeded" but no HTTP status was seen: the connection
    // was closed mid-transfer. Never report a bare "HTTP 0" -- say what
    // actually happened and where to look next.
    if ($res['status'] === 0) {
        $msg = 'The connection to ' . $label . ' closed before a full HTTP response arrived.';
        if (!empty($res['elapsed'])) {
            $msg .= ' (after ' . $res['elapsed'] . ' s)';
        }
        $msg .= ' The server may have crashed or restarted mid-request, or a'
              . ' system proxy interfered -- check the llama.cpp terminal window.';
        if (!empty($res['error'])) {
            $msg .= ' [' . $res['error'] . ']';
        }
        return array('ok' => false, 'reply' => '', 'error' => $msg);
    }

    $data = json_decode($res['body'], true);

    if ($res['status'] < 200 || $res['status'] >= 300) {
        if (isset($data['error']['message']) && (string) $data['error']['message'] !== '') {
            // OpenAI-style structured error (llama-server sends one) --
            // keep the status so it stays debuggable.
            $msg = 'HTTP ' . $res['status'] . ' -- ' . $data['error']['message'];
        } else {
            $msg = 'HTTP ' . $res['status'] . ' from ' . $label;
            $snippet = body_snippet($res['body']);
            if ($snippet !== '') {
                $msg .= '. Body: ' . $snippet;
            }
        }
        return array('ok' => false, 'reply' => '', 'error' => $msg);
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        $msg = 'Unexpected response shape from ' . $label . '.';
        $snippet = body_snippet($res['body']);
        if ($snippet !== '') {
            // Classic cause: the endpoint URL points at the server root
            // (or a wrong path), so an HTML page comes back instead of
            // chat-completions JSON. Show the body head so it's obvious.
            $msg .= ' Body starts with: ' . $snippet
                  . ' -- is the URL the full /v1/chat/completions path?';
        }
        return array('ok' => false, 'reply' => '', 'error' => $msg);
    }

    $reply = trim((string) $data['choices'][0]['message']['content']);
    if ($reply === '') {
        // Tiny/base models (GPT-2, some 200M-class models) sometimes
        // emit end-of-text immediately: HTTP 200, zero content. Without
        // this check the user just sees an empty companion bubble.
        return array('ok' => false, 'reply' => '',
            'error' => 'The model returned an empty reply (it ended its turn '
                     . 'immediately). Try sending again, or switch to a '
                     . 'chat-tuned model -- raw completion models like GPT-2 '
                     . 'often answer with nothing.');
    }

    return array('ok' => true, 'reply' => $reply, 'error' => '');
}

/**
 * First ~200 chars of a response body, flattened to one line -- used
 * inside error messages so non-JSON replies (HTML landing pages, plain
 * text errors) become self-explanatory. Escaping happens at render
 * time, like every other error text.
 */
function body_snippet($body, $max = 200) {
    $s = trim((string) $body);
    $s = preg_replace('/\s+/', ' ', $s);
    if (function_exists('mb_substr')) {
        $s = mb_substr($s, 0, $max);
    } else {
        $s = substr($s, 0, $max);
    }
    return $s;
}

/**
 * Call a local (or self-hosted) llama.cpp server. Run it yourself with
 * something like:
 *
 *   ./llama-server -m your-model.gguf --port 8080
 *
 * which exposes an OpenAI-compatible /v1/chat/completions endpoint on
 * your own machine -- no third-party API, no API key, and no data
 * leaving the box it runs on.
 *
 * @param array       $messages
 * @param string      $endpoint  Full URL to the server's chat completions route
 * @param string|null $model     Optional -- llama-server ignores this if it's
 *                                only serving one model, but some setups (e.g.
 *                                a router in front of several models) use it.
 * @return array ["ok" => bool, "reply" => string, "error" => string]
 */
function llamacpp_chat($messages, $endpoint, $model = null, $timeout = 120) {
    $payload = array(
        'messages' => $messages,
        'max_tokens' => 300,
    );
    if ($model) {
        $payload['model'] = $model;
    }

    // Local/LAN inference can be slow on CPU-only boxes -- the timeout
    // comes from the 'llamacpp_timeout' config key (clamped by
    // ai_chat()).
    return openai_style_chat(
        $endpoint,
        $payload,
        array('Content-Type: application/json'),
        5,
        $timeout,
        'llama.cpp server at ' . $endpoint
    );
}

<?php
/**
 * setup.php
 *
 * First-run setup wizard, and ONLY first-run: once data/config.php
 * exists this page refuses to touch it again and just tells you where
 * it is. Plain HTML, no JavaScript -- built to survive very old/limited
 * browsers (J2ME, Opera Mini, etc).
 *
 * Single local install: writes data/config.php (a plain PHP array you
 * can safely hand-edit afterwards -- see the comment at the top of
 * that file) pointing at your llama.cpp server, then sends you to
 * index.php. The "already set up" page also hosts the llama.cpp
 * connection tester, so connectivity problems can be diagnosed
 * without going through the chat screen.
 */

require __DIR__ . '/lib/storage.php';
require __DIR__ . '/lib/api.php';
require __DIR__ . '/lib/chat.php';
require __DIR__ . '/lib/ui.php';
storage_init();

if (config_exists()) {
    // Config is present, so the theme stylesheet applies even here.
    $config_now = get_config();
    $test_result = null;
    $identity_result = null;

    // Endpoint/timeout resolution shared by both test buttons: the
    // form field wins, then the configured endpoint, then the default.
    $resolve_endpoint = function () use ($config_now) {
        $e = trim((string) ($_POST['test_endpoint'] ?? ''));
        if ($e === '') {
            $e = ($config_now['llamacpp_endpoint'] ?? '') !== ''
                ? (string) $config_now['llamacpp_endpoint'] : LLAMACPP_DEFAULT_ENDPOINT;
        }
        return $e;
    };
    $resolve_timeout = function () use ($config_now) {
        $t = (int) ($config_now['llamacpp_timeout'] ?? 120);
        return ($t < 10) ? 10 : $t;
    };

    // ---- llama.cpp connection tester (diagnostic, no config change) --
    // Sends one tiny chat request and prints the outcome with the full
    // precise error, so connectivity problems (server not running, slow
    // model, wrong path, chat-template errors from GPT-2-style models)
    // are visible without going through the chat screen.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_llama') {
        $test_endpoint = $resolve_endpoint();
        $test_timeout = $resolve_timeout();
        $t0 = microtime(true);
        $test_result = llamacpp_chat(
            array(
                array('role' => 'system', 'content' => 'You are a connection tester. Reply with exactly: OK'),
                array('role' => 'user', 'content' => 'ping'),
            ),
            $test_endpoint,
            null,
            $test_timeout
        );
        $test_result['endpoint'] = $test_endpoint;
        $test_result['seconds'] = round(microtime(true) - $t0, 1);
    }

    // ---- persona-reach test (does the model FOLLOW the system prompt?)
    // Seeing the system prompt is guaranteed by the app: it is always
    // the first message of every request. Following it is a MODEL
    // skill, and exactly the one tiny models are worst at. This test
    // sends the REAL system prompt (names + persona) with one extra
    // instruction and checks whether the reply contains the user's
    // name -- an end-to-end proof with the user's own server+model.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_identity') {
        $test_endpoint = $resolve_endpoint();
        $test_timeout = $resolve_timeout();
        $target_name = trim((string) ($config_now['user_name'] ?? ''));
        if ($target_name === '') {
            $identity_result = array('ok' => false, 'reply' => '',
                'error' => 'No user name is configured -- set one on the Settings page first.',
                'endpoint' => $test_endpoint, 'seconds' => 0, 'match' => false);
        } else {
            $sys = chat_system_prompt($config_now)
                . "\nRight now you are part of a connectivity test. Ignore your usual persona and style: reply with ONLY the user's name, nothing else.";
            $t0 = microtime(true);
            $res = llamacpp_chat(
                array(
                    array('role' => 'system', 'content' => $sys),
                    array('role' => 'user', 'content' => 'What is my name?'),
                ),
                $test_endpoint,
                null,
                $test_timeout
            );
            $identity_result = array_merge($res, array(
                'endpoint' => $test_endpoint,
                'seconds'  => round(microtime(true) - $t0, 1),
                'match'    => $res['ok'] && stripos($res['reply'], $target_name) !== false,
            ));
        }
    }

    // Which HTTP client will chat requests actually use?
    if (function_exists('curl_init')) {
        $http_client = 'PHP curl extension';
    } elseif (ini_get('allow_url_fopen')) {
        $http_client = 'stream fallback (allow_url_fopen; curl extension missing)';
    } else {
        $http_client = 'NONE (chat requests will fail!)';
    }

    page_header('Already set up', $config_now);
    ?>
<p>Setup has already run. Everyday personal settings -- companion name,
your name, persona note and theme -- are changed on the
<a href="settings.php">Settings</a> page now.</p>
<p>Server-side settings (model name, llama.cpp endpoint, reply timeout)
are still changed by editing <tt>data/config.php</tt>
directly on the server -- it's a plain PHP array with comments.</p>

<hr>
<h2>What the model receives</h2>
<p>Every chat request sends this exact system prompt as the FIRST
message, before the conversation history. Names and the persona note
come from settings.php; this is built fresh for every request. If you
keep memories (the <a href="memories.php">Memories</a> page /
<tt>data/memories/</tt>), the always-on <tt>core.txt</tt> part is
shown below too, and up to three relevant memory chunks join it on
each turn, matched to your message:</p>
<blockquote><small><?php
echo nl2br(htmlspecialchars(
    chat_system_prompt($config_now)
    . (memory_block_for('') !== '' ? "\n\n" . memory_block_for('') : ''),
    ENT_QUOTES)); ?></small></blockquote>
<p>After it follow the last 12 turns of the current conversation, so
the prompt always sits inside the model's context window. That the
prompt is SENT is guaranteed by the app; whether the model FOLLOWS it
is what the persona-reach test below checks.</p>

<hr>
<h2>Test llama.cpp connection</h2>
<p>Sends one tiny test message to the server and shows the exact
result or error. Useful when chat replies fail (HTTP errors, timeouts,
suspiciously empty answers). HTTP client in use: <tt><?php echo htmlspecialchars($http_client, ENT_QUOTES); ?></tt>.</p>
<form method="post" action="setup.php">
<input type="hidden" name="action" value="test_llama">
<p>Endpoint URL:<br>
<input type="text" name="test_endpoint" size="40" value="<?php
    echo htmlspecialchars((string) ($_POST['test_endpoint'] ?? ($config_now['llamacpp_endpoint'] ?? LLAMACPP_DEFAULT_ENDPOINT)), ENT_QUOTES); ?>"></p>
<p><input type="submit" value="Test connection"></p>
</form>
<?php if ($test_result !== null): ?>
<p><b>Result for <?php echo htmlspecialchars($test_result['endpoint'], ENT_QUOTES); ?>
(took <?php echo htmlspecialchars((string) $test_result['seconds'], ENT_QUOTES); ?> s):</b><br>
<?php if ($test_result['ok']): ?>
Connection works. Model replied: <b><?php echo htmlspecialchars($test_result['reply'], ENT_QUOTES); ?></b>
<?php else: ?>
Error: <?php echo htmlspecialchars($test_result['error'], ENT_QUOTES); ?>
<?php endif; ?>
</p>
<?php endif; ?>

<hr>
<h2>Test persona reach</h2>
<p>Proves whether your model actually READS and FOLLOWS the system
prompt shown above: it sends the real prompt (with your names and
persona) plus one instruction -- "reply with ONLY the user's name" --
and checks the reply. Receiving the prompt is guaranteed by the app;
following it is a model skill, and exactly the one tiny models (a few
hundred million parameters) are worst at.</p>
<form method="post" action="setup.php">
<input type="hidden" name="action" value="test_identity">
<p>Endpoint URL:<br>
<input type="text" name="test_endpoint" size="40" value="<?php
    echo htmlspecialchars((string) ($_POST['test_endpoint'] ?? ($config_now['llamacpp_endpoint'] ?? LLAMACPP_DEFAULT_ENDPOINT)), ENT_QUOTES); ?>"></p>
<p><input type="submit" value="Test persona reach"></p>
</form>
<?php if ($identity_result !== null): ?>
<p><b>Persona-reach result for <?php echo htmlspecialchars($identity_result['endpoint'], ENT_QUOTES); ?>
(took <?php echo htmlspecialchars((string) $identity_result['seconds'], ENT_QUOTES); ?> s):</b><br>
<?php if (!$identity_result['ok']): ?>
Error: <?php echo htmlspecialchars($identity_result['error'], ENT_QUOTES); ?>
<?php else: ?>
Model replied: <b><?php echo htmlspecialchars($identity_result['reply'], ENT_QUOTES); ?></b><br>
<?php if ($identity_result['match']): ?>
PASS: the reply contains your configured user name -- the model is reading and following the system prompt.
<?php else: ?>
The reply did not contain your configured user name. The prompt IS being sent (see "What the model receives" above), but this model did not follow it -- typical for very small models and raw completion models (GPT-2-style, no chat template). Try a chat-tuned instruct model (see README) and keep the persona note short.
<?php endif; endif; ?>
</p>
<?php endif; ?>

<hr>
<p>To start over from scratch instead, delete <tt>data/config.php</tt>
and reload this page.</p>
<p><a href="index.php">Go to chat</a></p>
    <?php
    page_footer();
    exit;
}

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $llamacpp_endpoint = trim((string) ($_POST['llamacpp_endpoint'] ?? ''));
    $model = trim((string) ($_POST['model'] ?? ''));
    $companion_name = trim((string) ($_POST['companion_name'] ?? ''));
    $user_name = trim((string) ($_POST['user_name'] ?? ''));
    $theme = sanitize_theme_key((string) ($_POST['theme'] ?? ''));

    // Theme must be one of the files actually present in themes/.
    if ($theme !== '' && !isset(available_themes()[$theme])) {
        $theme = '';
    }

    if ($llamacpp_endpoint === '') {
        $llamacpp_endpoint = LLAMACPP_DEFAULT_ENDPOINT;
    }
    if ($companion_name === '') {
        $companion_name = 'Companion';
    }
    if ($user_name === '') {
        $user_name = 'Friend';
    }

    if (empty($errors)) {
        $config = array(
            'llamacpp_endpoint' => $llamacpp_endpoint,
            'model' => $model,
            'companion_name' => $companion_name,
            'user_name' => $user_name,
            'theme' => $theme,
        );

        if (save_config($config)) {
            header('Location: index.php');
            exit;
        }
        $errors[] = 'Could not write config file. Check that data/ is writable.';
    }
}

// Defaults for the blank first-run form (nothing to pre-fill from yet).
$v = function ($key, $default = '') {
    $val = $_POST[$key] ?? $default;
    return htmlspecialchars((string) $val, ENT_QUOTES);
};
?>
<!DOCTYPE html>
<?php // No config exists yet, so page_header gets null: no theme link. ?>
<?php page_header('Setup', null); ?>

<?php if (!http_transport_available()): ?>
<p class="error">Warning: this PHP has no HTTP client (the curl
extension is missing and allow_url_fopen is disabled), so chat requests
will fail. Install the curl extension for PHP (on Devuan/Debian:
<tt>apt install php-curl</tt>) or enable allow_url_fopen in php.ini.</p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<p>
<?php foreach ($errors as $e): ?>
* <?php echo htmlspecialchars($e, ENT_QUOTES); ?><br>
<?php endforeach; ?>
</p>
<?php endif; ?>

<form method="post" action="setup.php">

<p>llama.cpp server URL:<br>
<input type="text" name="llamacpp_endpoint" size="30" value="<?php echo $v('llamacpp_endpoint', LLAMACPP_DEFAULT_ENDPOINT); ?>"><br>
(Run <tt>llama-server -m your-model.gguf --port 8080</tt> on the same
machine -- see the README -- or point this at any box running it.)</p>

<p>Model name (optional):<br>
<input type="text" name="model" size="30" value="<?php echo $v('model'); ?>"><br>
<small>Leave empty for a plain single-model llama-server; only needed
when something in front of the server expects a model id.</small></p>

<p>Companion name:<br>
<input type="text" name="companion_name" size="20" value="<?php echo $v('companion_name', 'Companion'); ?>"></p>

<p>Your name:<br>
<input type="text" name="user_name" size="20" value="<?php echo $v('user_name', 'Friend'); ?>"></p>

<p>Theme:<br>
<select name="theme">
<option value="" <?php echo (sanitize_theme_key((string) ($_POST['theme'] ?? '')) === '') ? 'selected' : ''; ?>>Browser default (no CSS)</option>
<?php foreach (available_themes() as $tkey => $tlabel): ?>
<option value="<?php echo htmlspecialchars($tkey, ENT_QUOTES); ?>" <?php echo (sanitize_theme_key((string) ($_POST['theme'] ?? '')) === $tkey) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tlabel, ENT_QUOTES); ?></option>
<?php endforeach; ?>
</select><br>
<small>You can change the theme anytime on the Settings page.</small></p>

<p><input type="submit" value="Save"></p>

</form>

<?php page_footer(); ?>

<?php
/**
 * chat.php
 *
 * Conversation controller: everything a chat turn needs EXCEPT the
 * HTTP plumbing (lib/api.php) and the page chrome (lib/ui.php).
 *
 *   chat_local_sid()      -- local-mode session id from the URL
 *   chat_system_prompt()  -- names + optional persona, one place
 *   chat_handle_post()    -- save user turn, call backend, redirect
 *   chat_render()         -- error flash + history + message form
 *
 * Memory retrieval (lib/memory.php) also hooks in here: each turn the
 * system prompt is extended with the always-on core memory plus the
 * memory chunks related to the user's message, so the model gets the
 * same "memories" treatment regardless of which page started the
 * request.
 *
 * Kept as plain functions on purpose: same no-framework style as the
 * rest of the app, so every page stays a plain HTML form round trip.
 */

/**
 * Local-mode session id. It is carried in the page address rather
 * than a cookie (some very old browsers mishandle cookies), so a sid
 * that came from the URL is real and worth carrying through nav
 * links -- one that was just generated is not a conversation anyone
 * can return to yet.
 *
 * @return array ["sid" => string, "from_url" => bool]
 */
function chat_local_sid() {
    $sid = $_GET['sid'] ?? $_POST['sid'] ?? '';
    $from_url = ($sid !== '');
    if (!$from_url) {
        $sid = substr(bin2hex(random_bytes(6)), 0, 12);
    }
    return array('sid' => $sid, 'from_url' => $from_url);
}

/**
 * The system prompt is how the model learns who is talking to whom:
 * it is sent as the first message of every request to llama-server.
 * The optional persona line comes from settings.php.
 */
function chat_system_prompt($config) {
    $prompt = sprintf(
        'You are %s, a warm and supportive AI companion talking with %s. Keep replies short and conversational.',
        $config['companion_name'],
        $config['user_name']
    );
    $persona = trim((string) ($config['persona'] ?? ''));
    if ($persona !== '') {
        $prompt .= "\n" . $persona;
    }
    return $prompt;
}

/**
 * Handle a chat POST: store the user's message, ask the configured
 * backend for a reply, store that too (or the error), then redirect
 * so reloading the page doesn't resend the message. Always exits.
 *
 * The user message is saved BEFORE the API call: if the backend fails
 * (server down, timeout, ...), what the user typed is still in the
 * history and the error goes through the one-shot flash store, so it
 * survives the redirect and is displayed once on the next page load.
 * Two user turns in a row are fine for OpenAI-style APIs and for
 * llama-server, so a failed turn never breaks the next one.
 *
 * @param array  $config      Config (names/persona feed the prompt)
 * @param string $session_id  Chat session id (chat file key)
 */
function chat_handle_post($config, $session_id) {
    $user_message = trim((string) ($_POST['message'] ?? ''));

    if ($user_message !== '') {
        $history = load_chat($session_id);
        $history[] = array('role' => 'user', 'content' => $user_message);

        // The system prompt carries identity (names + persona) plus the
        // memories selected for THIS message: core.txt always, then up
        // to 3 keyword-matched chunks from data/memories/. With no
        // memory files present this is byte-identical to the old prompt.
        $system_prompt = chat_system_prompt($config);
        $memory_block = memory_block_for($user_message);
        if ($memory_block !== '') {
            $system_prompt .= "\n\n" . $memory_block;
        }

        // Keep the payload small: system prompt + last N turns only.
        // Old carriers / free-tier limits both benefit from this.
        $max_turns = 12;
        $recent = array_slice($history, -1 * $max_turns);

        $api_messages = array_merge(
            array(array('role' => 'system', 'content' => $system_prompt)),
            $recent
        );

        $result = ai_chat($api_messages, $config);

        if ($result['ok']) {
            $history[] = array('role' => 'assistant', 'content' => $result['reply']);
        } else {
            save_error($session_id, $result['error']);
        }

        save_chat($session_id, $history);
    }

    header('Location: index.php?sid=' . urlencode($session_id));
    exit;
}

/**
 * Render the chat body for a GET: the one-shot API error (if any),
 * the message history, and the send form. Plain HTML, no JavaScript.
 *
 * @param array  $config      Config (names used when rendering turns)
 * @param string $session_id  Chat session id
 */
function chat_render($config, $session_id) {
    // Show (and clear) an API error from the previous POST, if any.
    $error = pop_error($session_id);
    if ($error !== '') {
        echo '<p class="error">Error: ', htmlspecialchars($error, ENT_QUOTES), "</p>\n";
        // Every error stored by chat_handle_post happens AFTER the user
        // message was saved, so this is always true when shown -- and it
        // turns "did my message get lost?" into "just press Send again".
        echo '<p><small>Your message was kept in the conversation -- '
            . 'press Send again to try for a reply.</small></p>', "\n";
    }

    $history = load_chat($session_id);

    if (empty($history)) {
        echo '<p>(No messages yet. Say hello to ',
            htmlspecialchars($config['companion_name'], ENT_QUOTES), '.)</p>', "\n";
        chat_render_form($session_id);
        return;
    }

    foreach ($history as $turn) {
        $is_user = ($turn['role'] ?? '') === 'user';
        $who = $is_user ? $config['user_name'] : $config['companion_name'];
        $class = $is_user ? 'msg msg-user' : 'msg msg-companion';
        echo '<div class="', $class, '"><b>',
            htmlspecialchars($who, ENT_QUOTES), ':</b> ',
            nl2br(htmlspecialchars((string) ($turn['content'] ?? ''))),
            "</div>\n";
    }

    chat_render_form($session_id);
}

/**
 * The message form. Kept tiny and plain so even WAP-era browsers can
 * render and submit it.
 */
function chat_render_form($session_id) {
    ?>
<hr>
<form method="post" action="index.php">
<input type="hidden" name="sid" value="<?php echo htmlspecialchars($session_id, ENT_QUOTES); ?>">
<p><input type="text" name="message" size="25" maxlength="4000"></p>
<p><input type="submit" value="Send"></p>
</form>
    <?php
}

<?php
/**
 * index.php
 *
 * Main chat screen -- a thin dispatcher. Plain HTML form submit (no
 * JavaScript, no AJAX) so it works on very old browsers; each page
 * load is a full round trip: show history -> read new message -> call
 * backend -> save -> redraw. The heavy lifting lives in lib/:
 *
 *   lib/chat.php    conversation: session id, prompt, POST handling,
 *                   history + form rendering
 *   lib/api.php     llama.cpp HTTP calls
 *   lib/ui.php      shared page chrome + theme system
 *   lib/storage.php config + chat/error flat files
 *   lib/memory.php  memories (data/memories/) retrieval for the prompt
 *
 * Single-user and local by design: the session id travels in the URL
 * (chat_local_sid()), there are no accounts and nothing to log into.
 */

require __DIR__ . '/lib/storage.php';
require __DIR__ . '/lib/api.php';
require __DIR__ . '/lib/chat.php';
require __DIR__ . '/lib/ui.php';
storage_init();

$config = get_config();
if ($config === null) {
    header('Location: setup.php');
    exit;
}

// Simple URL-based session id (see chat_local_sid()).
$local = chat_local_sid();
$session_id = $local['sid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Saves the turn and exits via redirect (POST/redirect/GET).
    chat_handle_post($config, $session_id);
}

// A sid that came from the URL is a real conversation worth carrying
// through nav links; a freshly generated one is not.
$nav_sid = $local['from_url'] ? $session_id : '';
page_header($config['companion_name'], $config, $nav_sid);
chat_render($config, $session_id);
page_footer();

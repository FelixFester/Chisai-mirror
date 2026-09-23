<?php
/**
 * settings.php
 *
 * Personal settings page. Changes the "human" side of the config --
 * companion name, user name, optional persona note and the UI theme --
 * and writes them straight into data/config.php, so hand-editing the
 * file is no longer needed for everyday tweaks.
 *
 * Deliberately NOT here: model name, llama.cpp endpoint and reply
 * timeout. Those are server settings; they are still changed by
 * hand-editing data/config.php (or by re-running setup.php on a fresh
 * install).
 *
 * Plain HTML form, POST -> redirect -> GET, no JavaScript. The theme
 * dropdown is built by scanning themes/*.css (see lib/ui.php), so new
 * themes show up just by dropping a file into the folder.
 */

require __DIR__ . '/lib/storage.php';
require __DIR__ . '/lib/api.php';
require __DIR__ . '/lib/ui.php';
storage_init();

$config = get_config();
if ($config === null) {
    // Nothing configured yet -- setup must run first, same as index.php.
    header('Location: setup.php');
    exit;
}

// Carry the chat session through the round trip so returning to the
// chat does not silently start a fresh conversation.
$session_id = $_GET['sid'] ?? $_POST['sid'] ?? '';

$themes = available_themes();
$errors = array();

/** Truncate to $len chars, preferring mbstring when available. */
function settings_truncate($str, $len) {
    $str = (string) $str;
    return function_exists('mb_substr') ? mb_substr($str, 0, $len) : substr($str, 0, $len);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companion_name = trim((string) ($_POST['companion_name'] ?? ''));
    $user_name      = trim((string) ($_POST['user_name'] ?? ''));
    $persona        = trim((string) ($_POST['persona'] ?? ''));
    $theme          = sanitize_theme_key((string) ($_POST['theme'] ?? ''));

    // Blank names fall back to whatever is already configured, so a
    // half-filled form can never wipe the names the model is using.
    if ($companion_name === '') {
        $companion_name = (string) $config['companion_name'];
    }
    if ($user_name === '') {
        $user_name = (string) $config['user_name'];
    }
    $companion_name = settings_truncate($companion_name, 40);
    $user_name      = settings_truncate($user_name, 40);
    $persona        = settings_truncate($persona, 500);

    // Whitelist check: only a theme that exists on disk can be saved.
    if ($theme !== '' && !isset($themes[$theme])) {
        $errors[] = 'Unknown theme selected.';
        $theme = sanitize_theme_key($config['theme'] ?? '');
    }

    if (empty($errors)) {
        // Merge into the FULL current config -- save_config() also
        // merges defaults, so passing the whole array here keeps the
        // endpoint, model, timeout etc. exactly as they are.
        $config['companion_name'] = $companion_name;
        $config['user_name']      = $user_name;
        $config['persona']        = $persona;
        $config['theme']          = $theme;

        if (save_config($config)) {
            header('Location: settings.php?sid=' . urlencode($session_id) . '&saved=1');
            exit;
        }
        $errors[] = 'Could not write config file. Check that data/ is writable.';
    }
} else {
    $saved = isset($_GET['saved']);
}

/**
 * Value to prefill a field with: the posted value while redisplaying
 * after a failed POST, otherwise the value from the (reloaded) config.
 */
function settings_value($config, $key, $default = '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists($key, $_POST)) {
        return (string) $_POST[$key];
    }
    return (string) ($config[$key] ?? $default);
}

// After a redirect the config on disk is already the new one; reload it
// so the form shows what was actually saved.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $fresh = get_config();
    if (is_array($fresh)) {
        $config = $fresh;
    }
}

$theme_current = sanitize_theme_key(settings_value($config, 'theme', ''));

// Everything from <!DOCTYPE html> through the <h1> + nav bar comes
// from page_header() so this page matches index.php exactly.
page_header('Settings', $config, $session_id);
?>

<?php if (!empty($saved)): ?>
<p><b>Settings saved.</b></p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<p class="error">
<?php foreach ($errors as $e): ?>
* <?php echo htmlspecialchars($e, ENT_QUOTES); ?><br>
<?php endforeach; ?>
</p>
<?php endif; ?>

<form method="post" action="settings.php">
<input type="hidden" name="sid" value="<?php echo htmlspecialchars($session_id, ENT_QUOTES); ?>">

<p>Companion name:<br>
<input type="text" name="companion_name" size="20" maxlength="40" value="<?php echo htmlspecialchars(settings_value($config, 'companion_name', 'Companion'), ENT_QUOTES); ?>"></p>

<p>Your name:<br>
<input type="text" name="user_name" size="20" maxlength="40" value="<?php echo htmlspecialchars(settings_value($config, 'user_name', 'Friend'), ENT_QUOTES); ?>"></p>

<p>Persona (optional note for the companion, added to the system prompt):<br>
<textarea name="persona" rows="4" cols="30" maxlength="500"><?php echo htmlspecialchars(settings_value($config, 'persona', ''), ENT_QUOTES); ?></textarea></p>

<p>Theme:<br>
<select name="theme">
<option value="" <?php echo ($theme_current === '') ? 'selected' : ''; ?>>Browser default (no CSS)</option>
<?php foreach ($themes as $key => $label): ?>
<option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>" <?php echo ($theme_current === $key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
<?php endforeach; ?>
</select><br>
<small>Themes are the .css files in the themes/ folder. Some themes
(such as Neon Green) switch to dark mode automatically on systems set
to dark.</small></p>

<p><input type="submit" value="Save"></p>
</form>

<hr>
<p><small>Model name, llama.cpp server URL and reply timeout are
still changed by hand-editing <tt>data/config.php</tt> on the
server.</small></p>

<?php page_footer(); ?>

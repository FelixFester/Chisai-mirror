<?php
/**
 * ui.php
 *
 * Shared page chrome + theme system. Every entry point (index.php,
 * settings.php, setup.php) builds its page through page_header() /
 * page_footer() so the markup, the mobile viewport line and the theme
 * stylesheet link stay identical everywhere.
 *
 * No JavaScript, no CSS framework -- same philosophy as the rest of
 * the app. Themes are plain .css files dropped into the themes/
 * folder; they are discovered automatically, so adding a theme to the
 * folder is enough for it to appear in the switcher on settings.php.
 */

define('THEMES_DIR', __DIR__ . '/../themes');

/**
 * Scan the themes/ folder for .css files and return
 *   array( <file key> => <display label> )
 *
 * The display label comes from a "Theme: <name>" comment in the first
 * lines of the CSS file; if it is absent, the filename is prettified
 * instead (neon-green.css -> "Neon Green").
 */
function available_themes() {
    $themes = array();
    if (!is_dir(THEMES_DIR)) {
        return $themes;
    }
    $files = glob(THEMES_DIR . '/*.css');
    if (!is_array($files)) {
        return $themes;
    }
    foreach ($files as $path) {
        $key = basename($path, '.css');
        $themes[$key] = theme_display_name($path, $key);
    }
    ksort($themes);
    return $themes;
}

/**
 * Read the "Theme: <name>" label from the head of a CSS file, falling
 * back to a prettified file name. Only the first 300 bytes are read --
 * the comment must live at the top of the file anyway.
 */
function theme_display_name($path, $key) {
    $head = (string) @file_get_contents($path, false, null, 0, 300);
    if (preg_match('/Theme:\s*([^\r\n*]+)/i', $head, $m)) {
        $label = trim($m[1]);
        if ($label !== '') {
            return $label;
        }
    }
    return ucwords(str_replace(array('-', '_'), ' ', $key));
}

/**
 * Keep only [a-z0-9_-] in a theme key. The key is used to build a
 * stylesheet URL, so this also closes the path-traversal door for
 * crafted values coming from a POST or a hand-mangled config.
 */
function sanitize_theme_key($key) {
    return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $key));
}

/**
 * Build the <link rel="stylesheet"> line for the configured theme, or
 * '' when no theme is set / the file no longer exists. Returns a full
 * line including the trailing newline, ready to echo inside <head>.
 */
function theme_link_tag($config) {
    if (!is_array($config)) {
        return '';
    }
    $key = sanitize_theme_key($config['theme'] ?? '');
    if ($key === '' || !is_file(THEMES_DIR . '/' . $key . '.css')) {
        return '';
    }
    return '<link rel="stylesheet" type="text/css" href="themes/'
        . htmlspecialchars($key, ENT_QUOTES) . '.css">' . "\n";
}

/**
 * Open a page: doctype, charset, mobile viewport, color-scheme hint,
 * title, theme stylesheet, <h1>, and the shared nav bar.
 *
 * @param string     $title    Page + <h1> title (raw; escaped here)
 * @param array|null $config   Config array (for the theme link), or null
 *                             when no config exists yet (setup.php)
 * @param string     $sid      Chat session id to carry through nav links
 * @param bool       $show_nav Show the Chat/Settings nav bar
 */
function page_header($title, $config = null, $sid = '', $show_nav = true) {
    echo '<!DOCTYPE html>', "\n",
        "<html>\n",
        "<head>\n",
        '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">', "\n",
        // Makes pages adapt to phone screens instead of assuming a
        // desktop-width layout. Ignored by very old WAP browsers.
        '<meta name="viewport" content="width=device-width, initial-scale=1">', "\n",
        // Tells modern browsers our dark themes are OK for form
        // controls etc. Ignored where unsupported.
        '<meta name="color-scheme" content="light dark">', "\n",
        '<title>', htmlspecialchars($title, ENT_QUOTES), "</title>\n";
    echo theme_link_tag($config);
    echo "</head>\n<body>\n";
    echo '<h1>', htmlspecialchars($title, ENT_QUOTES), "</h1>\n";

    if ($show_nav) {
        $qs = ($sid !== '') ? '?sid=' . urlencode($sid) : '';
        echo '<p class="nav"><a href="index.php', $qs, '">Chat</a>',
            ' | ',
            '<a href="memories.php', $qs, '">Memories</a>',
            ' | ',
            '<a href="settings.php', $qs, '">Settings</a>';
        // Only meaningful inside an existing session: following it
        // starts a fresh conversation while the old one stays on the
        // server under its own session id.
        if ($sid !== '') {
            echo ' | <a href="index.php">New chat</a>';
        }
        echo "</p>\n<hr>\n";
    }
}

/**
 * Close a page opened by page_header().
 */
function page_footer() {
    echo "</body>\n</html>\n";
}

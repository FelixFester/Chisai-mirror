<?php
/**
 * memories.php
 *
 * Manager page for the companion's "memories" -- the plain text files
 * in data/memories/ that get searched on every chat message and fed
 * to the model as a Memories section of the system prompt (see
 * lib/memory.php for the retrieval rules).
 *
 * Lets you list, create, edit and delete memory files in the browser,
 * in the same no-JS POST -> redirect -> GET style as the other pages,
 * so it works on the same old browsers as the chat. Dropping files
 * into data/memories/ with a text editor or a file manager works too
 * -- the page simply shows whatever .txt/.md files are there.
 *
 * Special cases: core.txt is always sent to the model on every turn
 * (flagged in the list); README.txt is the folder's human explainer
 * and is never sent, so it is not listed and its name is reserved.
 */

require __DIR__ . '/lib/storage.php';
require __DIR__ . '/lib/api.php';
// (Needed for the LLAMACPP_DEFAULT_ENDPOINT constant used by
// config_defaults(); same require set as settings.php.)
require __DIR__ . '/lib/ui.php';
// (lib/memory.php is pulled in by lib/storage.php.)
storage_init();

$config = get_config();
if ($config === null) {
    // Nothing configured yet -- setup must run first, same as index.php.
    header('Location: setup.php');
    exit;
}

// Carry the chat session through the round trip, like settings.php.
$session_id = (string) ($_GET['sid'] ?? $_POST['sid'] ?? '');

/**
 * Redirect back to the list with a one-shot outcome flag in the URL.
 * Only fixed codes travel in the URL, never user-typed content.
 */
function memories_redirect($session_id, $flag) {
    header('Location: memories.php?sid=' . urlencode($session_id) . '&' . $flag);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'new' || $action === 'save') {
        $name = memory_valid_name($_POST['name'] ?? '');
        $content = (string) ($_POST['content'] ?? '');
        if (strlen($content) > 65535) {
            // Hard cap so a stray paste cannot write a monster file.
            $content = function_exists('mb_substr') ? mb_substr($content, 0, 65535) : substr($content, 0, 65535);
        }
        if ($name === '') {
            memories_redirect($session_id, 'err=badname');
        }
        if ($action === 'new' && is_file(MEMORIES_DIR . '/' . $name)) {
            memories_redirect($session_id, 'err=exists');
        }
        $ok = @file_put_contents(MEMORIES_DIR . '/' . $name, $content, LOCK_EX);
        memories_redirect($session_id, $ok !== false ? 'saved=1' : 'err=write');
    }

    if ($action === 'delete') {
        $name = memory_valid_name($_POST['name'] ?? '');
        if ($name === '' || !is_file(MEMORIES_DIR . '/' . $name)) {
            memories_redirect($session_id, 'err=nofile');
        }
        $ok = @unlink(MEMORIES_DIR . '/' . $name);
        memories_redirect($session_id, $ok !== false ? 'saved=1' : 'err=delete');
    }

    // Unknown action: just go back to the list.
    memories_redirect($session_id, '');
}

// Outcome flags from the last POST (fixed strings only).
$saved = isset($_GET['saved']);
$err_code = (string) ($_GET['err'] ?? '');
$err_texts = array(
    'badname' => 'Invalid file name. Use letters, numbers, spaces, - or _ (max 48 chars). '
        . 'No slashes, and README is a reserved name.',
    'exists'  => 'A memory file with that name already exists -- edit it from the list instead.',
    'nofile'  => 'No such memory file.',
    'write'   => 'Could not write the memory file. Check that data/memories/ is writable by PHP.',
    'delete'  => 'Could not delete the memory file. Check that data/memories/ is writable by PHP.',
);

// Which view to render: the list, one file in the editor, or the
// create form. ?edit= and ?new=1 are GET links from the list.
$view = 'list';
$edit_name = '';
if (isset($_GET['new'])) {
    $view = 'new';
} else {
    $edit_name = memory_valid_name($_GET['edit'] ?? '');
    if ($edit_name !== '' && is_file(MEMORIES_DIR . '/' . $edit_name)) {
        $view = 'edit';
    }
}

page_header('Memories', $config, $session_id);
?>

<?php if ($saved): ?>
<p><b>Memory saved.</b></p>
<?php endif; ?>
<?php if ($err_code !== '' && isset($err_texts[$err_code])): ?>
<p class="error"><?php echo htmlspecialchars($err_texts[$err_code], ENT_QUOTES); ?></p>
<?php endif; ?>

<?php if ($view === 'edit'): ?>
<p>Editing <b><?php echo htmlspecialchars($edit_name, ENT_QUOTES); ?></b><?php
    echo ($edit_name === 'core.txt')
        ? ' -- this file is ALWAYS sent to the model, every turn.'
        : ' -- the most relevant parts are sent when a message relates to them.'; ?></p>
<form method="post" action="memories.php">
<input type="hidden" name="sid" value="<?php echo htmlspecialchars($session_id, ENT_QUOTES); ?>">
<input type="hidden" name="action" value="save">
<input type="hidden" name="name" value="<?php echo htmlspecialchars($edit_name, ENT_QUOTES); ?>">
<p>Text:<br>
<textarea name="content" rows="12" cols="30"><?php echo htmlspecialchars(memory_read($edit_name), ENT_QUOTES); ?></textarea></p>
<p><input type="submit" value="Save memory"></p>
</form>
<p><a href="memories.php?sid=<?php echo htmlspecialchars($session_id, ENT_QUOTES); ?>">Back to memories</a></p>

<?php elseif ($view === 'new'): ?>
<p>New memory file. It is saved as a plain <tt>.txt</tt> file in
<tt>data/memories/</tt>. Name it <tt>core</tt> to make it the
always-sent core memory.</p>
<form method="post" action="memories.php">
<input type="hidden" name="sid" value="<?php echo htmlspecialchars($session_id, ENT_QUOTES); ?>">
<input type="hidden" name="action" value="new">
<p>File name:<br>
<input type="text" name="name" size="20" maxlength="48"></p>
<p>Text:<br>
<textarea name="content" rows="12" cols="30"></textarea></p>
<p><input type="submit" value="Create memory"></p>
</form>
<p><a href="memories.php?sid=<?php echo htmlspecialchars($session_id, ENT_QUOTES); ?>">Back to memories</a></p>

<?php else: ?>
<?php $files = memory_list_files(); ?>
<?php if (empty($files)): ?>
<p>(No memories yet. The companion only knows its system prompt --
names and the persona note from Settings.)</p>
<?php else: ?>
<?php foreach ($files as $file): ?>
<p><?php if ($file['always']): ?><b><?php endif; ?><?php
    echo htmlspecialchars($file['name'], ENT_QUOTES); ?><?php
    if ($file['always']): ?></b> (always sent, every turn)<?php endif; ?>
-- <?php echo (string) $file['size']; ?> bytes<br>
<a href="memories.php?sid=<?php echo htmlspecialchars($session_id, ENT_QUOTES); ?>&amp;edit=<?php
    echo urlencode($file['name']); ?>">Edit</a>
<form method="post" action="memories.php">
<input type="hidden" name="sid" value="<?php echo htmlspecialchars($session_id, ENT_QUOTES); ?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="name" value="<?php echo htmlspecialchars($file['name'], ENT_QUOTES); ?>">
<input type="submit" value="Delete">
</form></p>
<?php endforeach; ?>
<?php endif; ?>

<p><a href="memories.php?sid=<?php echo htmlspecialchars($session_id, ENT_QUOTES); ?>&amp;new=1">Create a new memory</a></p>

<hr>
<p><small><b>How memories work:</b> on every chat message the app
searches these files for text related to what you just said and adds
the best matches (up to 3 short chunks) to the system prompt.
<tt>core.txt</tt> is different: it is always sent in full, every turn
-- keep the few must-never-be-forgotten facts there (your name, key
preferences). Files can also be dropped straight into
<tt>data/memories/</tt> with any text editor; only <tt>.txt</tt> and
<tt>.md</tt> count, and <tt>README.txt</tt> is the folder's own
explainer, never sent to the model. Keep notes short and factual --
small models do better with a few precise facts than with long
documents.</small></p>
<?php endif; ?>

<?php page_footer(); ?>

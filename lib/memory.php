<?php
/**
 * memory.php
 *
 * "Memories" -- RAG (retrieval-augmented generation) in the same
 * dependency-free style as the rest of the app. No database, no
 * embeddings, no Composer: the memory is a folder of plain text files
 * (data/memories/) that the user fills with anything the companion
 * should permanently know, and retrieval is a keyword search over it.
 *
 * What happens on every chat turn (see memory_block_for()):
 *
 *   1. data/memories/core.txt, if present, is ALWAYS added to the
 *      system prompt, matched or not. This is the safe shelf for the
 *      few facts the companion must never forget -- and the reliable
 *      path for small models, which can miss retrieved chunks.
 *   2. The user's message is tokenized (stopwords removed) and every
 *      other memory file is scored: how many distinct message words
 *      appear in each paragraph-sized chunk. The best chunks (max 3,
 *      char budget) are appended to the system prompt as a
 *      "Memories" section. No related words -> no section at all, so
 *      unrelated chatter never dilutes the prompt.
 *
 * Why keywords instead of embeddings: this app targets hosts with
 * nothing but PHP files, and a personal note collection is a few
 * short files, not a corpus. Keyword overlap is deterministic,
 * debuggable and instantly fast; an embeddings-based retriever can
 * replace memory_retrieve() later without touching anything else.
 *
 * data/memories/README.txt is the human explainer and is NEVER sent
 * to the model. Only .txt/.md files are memories; every other file in
 * the folder is ignored. The folder is created by storage_init() (so
 * it exists right after setup.php runs), and can be filled either on
 * the memories.php page or by dropping plain files into it.
 */

define('MEMORIES_DIR', DATA_DIR . '/memories');

/**
 * Create the memories folder + explainer file if missing. Called from
 * storage_init(), i.e. by every entry point, which is why the folder
 * exists as soon as setup.php runs.
 */
function memory_init() {
    if (!is_dir(MEMORIES_DIR)) {
        @mkdir(MEMORIES_DIR, 0700, true);
    }
    $readme = MEMORIES_DIR . '/README.txt';
    if (is_dir(MEMORIES_DIR) && !is_file($readme)) {
        @file_put_contents($readme, memory_readme_text(), LOCK_EX);
    }
}

/** The human explainer written into data/memories/README.txt. */
function memory_readme_text() {
    return "This folder is the companion's long-term memory (\"memories\").\n"
        . "\n"
        . "- Every .txt or .md file here (except this README) is a memory.\n"
        . "- On each chat message the app searches these files for text\n"
        . "  related to what you just said and adds the best matches to the\n"
        . "  system prompt, so the model \"remembers\" them.\n"
        . "- core.txt is special: it is ALWAYS added to the system prompt,\n"
        . "  every turn, matched or not. Put the few facts the companion\n"
        . "  must always know there (your name, key preferences, context).\n"
        . "- You can edit these files on the Memories page of the web UI,\n"
        . "  or drop plain files into this folder with any text editor.\n"
        . "- Keep files small and factual: a few short notes work better\n"
        . "  with small models than one huge document.\n";
}

/**
 * Turn a user-supplied file name into a safe memory file name, or ''
 * if it is not acceptable. Plain names only: letters, numbers,
 * spaces, - and _ (max 48 chars), no slashes, no dots except an
 * optional .txt/.md suffix. README* is reserved for the explainer,
 * and anything without .txt/.md gets .txt appended, so a crafted
 * name can never escape the folder or produce an executable file.
 */
function memory_valid_name($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    if (basename($raw) !== $raw || strpos($raw, '\\') !== false) {
        return '';
    }
    // Split off an optional .txt/.md suffix; anything else gets .txt
    // appended, so a crafted name can never escape the folder or
    // produce an executable file.
    $low = strtolower($raw);
    if (substr($low, -4) === '.txt') {
        $base = substr($raw, 0, -4);
        $ext = '.txt';
    } elseif (substr($low, -3) === '.md') {
        $base = substr($raw, 0, -3);
        $ext = '.md';
    } else {
        $base = $raw;
        $ext = '.txt';
    }
    // The base allows letters, numbers, spaces, - and _ only: no dots
    // (notes.txt.txt or shell.php are rejected, not normalized).
    if ($base === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9 _-]{0,47}$/', $base)) {
        return '';
    }
    if (stripos($base, 'readme') === 0) {
        return '';
    }
    return $base . $ext;
}

/**
 * Read one memory file by name ('' if the name is invalid or the file
 * is missing). Output is forced to valid UTF-8-ish text: memory files
 * travel into the JSON request payload, and json_encode() refuses
 * invalid UTF-8, so a binary-junk file must not be able to break the
 * whole chat.
 */
function memory_read($name) {
    $name = memory_valid_name($name);
    if ($name === '') {
        return '';
    }
    $path = MEMORIES_DIR . '/' . $name;
    if (!is_file($path)) {
        return '';
    }
    $data = (string) @file_get_contents($path);
    if ($data !== '' && @preg_match('//u', $data) !== 1) {
        // Invalid UTF-8 (binary junk): replace every high byte. The
        // content was already unreadable; keeping the payload encodable
        // matters more.
        $data = (string) preg_replace('/[\x80-\xFF]/', '?', $data);
    }
    return $data;
}

/**
 * List the memory files: array of
 *   ['name' => 'core.txt', 'size' => 123, 'always' => bool]
 * sorted core.txt first, then alphabetical. Only .txt/.md count as
 * memories; README* and dotfiles are excluded.
 */
function memory_list_files() {
    $out = array();
    if (!is_dir(MEMORIES_DIR)) {
        return $out;
    }
    $paths = glob(MEMORIES_DIR . '/*');
    if (!is_array($paths)) {
        return $out;
    }
    foreach ($paths as $path) {
        if (!is_file($path)) {
            continue;
        }
        $name = basename($path);
        if ($name[0] === '.' || stripos($name, 'readme') === 0) {
            continue;
        }
        $low = strtolower($name);
        if (substr($low, -4) !== '.txt' && substr($low, -3) !== '.md') {
            continue;
        }
        $size = (int) @filesize($path);
        if ($size > 200000) {
            continue; // absurdly large for a personal note; ignore it
        }
        $out[] = array('name' => $name, 'size' => $size, 'always' => ($low === 'core.txt'));
    }
    usort($out, function ($a, $b) {
        if ($a['always'] !== $b['always']) {
            return $a['always'] ? -1 : 1;
        }
        return strnatcasecmp($a['name'], $b['name']);
    });
    return $out;
}

/**
 * Split one memory file into paragraph-sized chunks: blank-line
 * separated blocks, with over-long blocks hard-split (~700 chars) so
 * one wall of text cannot monopolize the memory budget.
 */
function memory_chunks($name) {
    $raw = memory_read($name);
    if (trim($raw) === '') {
        return array();
    }
    $raw = str_replace(array("\r\n", "\r"), "\n", $raw);
    $blocks = preg_split("/\n\s*\n/", $raw);
    if (!is_array($blocks)) {
        $blocks = array($raw);
    }
    $chunks = array();
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        if (strlen($block) > 900) {
            for ($off = 0; $off < strlen($block); $off += 700) {
                $chunks[] = trim(substr($block, $off, 700));
            }
        } else {
            $chunks[] = $block;
        }
    }
    return $chunks;
}

/** The stopword list: common words that must not trigger retrieval. */
function memory_stopwords() {
    static $stop = null;
    if ($stop !== null) {
        return $stop;
    }
    $words = 'a an the and or but if then else when while at by for with about into to from of on in out '
        . 'over under is are was were be been being am i you he she it we they me my your his her its our '
        . 'their this that these those what which who whom whose how why where do does did done doing can '
        . 'could will would shall should may might must not no nor so as up down again further once here '
        . 'there all any both each few more most other some such only own same too very just also ever '
        . 'never always sometimes hi hello hey ok okay please thanks thank welcome ya yeah yep nope u ur r '
        . 's t don now today say said says tell told ask asked know knows knew think thought want wanted '
        . 'like love good great nice well really actually maybe maybe'
        . '';
    $stop = array_fill_keys(explode(' ', $words), true);
    return $stop;
}

/**
 * Tokenize a message into distinct lowercase search terms: letters and
 * numbers only, min length 2, stopwords dropped, case folded (matching
 * is case-insensitive anyway, so folding here just deduplicates).
 * Falls back to an ASCII-only split when the text is not valid UTF-8.
 *
 * @return array of distinct term strings (possibly empty)
 */
function memory_terms($text) {
    $text = (string) $text;
    if (trim($text) === '') {
        return array();
    }
    $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
        $parts = preg_split('/[^A-Za-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return array();
        }
    }
    $stop = memory_stopwords();
    $terms = array();
    foreach ($parts as $part) {
        if (strlen($part) < 2) {
            continue;
        }
        $low = strtolower($part);
        if (isset($stop[$low])) {
            continue;
        }
        $terms[$low] = true;
    }
    return array_keys($terms);
}

/**
 * Does $needle appear in $haystack as a whole word? Unicode-aware
 * boundary check; falls back to a plain substring hit when the text
 * is not valid UTF-8 and the regex engine refuses it.
 */
function memory_contains_word($haystack, $needle) {
    $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($needle, '/') . '(?![\p{L}\p{N}])/iu';
    $result = @preg_match($pattern, $haystack);
    if ($result === false) {
        return stripos($haystack, $needle) !== false;
    }
    return $result > 0;
}

/**
 * Score one chunk: how many of the message's distinct terms it
 * contains as whole words.
 */
function memory_score_chunk($chunk, $terms) {
    $score = 0;
    foreach ($terms as $term) {
        if (memory_contains_word($chunk, $term)) {
            $score++;
        }
    }
    return $score;
}

/**
 * Truncate to $len characters for injection, preferring mbstring (a
 * plain substr could cut a multibyte character in half and make the
 * JSON payload unencodable). Marks truncation with [...].
 */
function memory_cut($str, $len) {
    $str = (string) $str;
    $cut = function_exists('mb_substr') ? mb_substr($str, 0, $len) : substr($str, 0, $len);
    return (strlen($cut) < strlen($str)) ? $cut . ' [...]' : $cut;
}

/**
 * Retrieve the best-matching memory chunks for the given terms.
 * Scores every chunk of every memory file (core.txt excluded -- it is
 * always sent anyway), then returns up to $limit chunks with the
 * highest scores within a total $budget character budget.
 *
 * @return array of ['file' => name, 'text' => chunk]
 */
function memory_retrieve($terms, $limit = 3, $budget = 1500) {
    $scored = array();
    foreach (memory_list_files() as $file) {
        if ($file['always']) {
            continue;
        }
        foreach (memory_chunks($file['name']) as $pos => $chunk) {
            $score = memory_score_chunk($chunk, $terms);
            if ($score > 0) {
                $scored[] = array('score' => $score, 'file' => $file['name'],
                    'pos' => $pos, 'text' => $chunk);
            }
        }
    }
    usort($scored, function ($a, $b) {
        if ($a['score'] !== $b['score']) {
            return $b['score'] - $a['score'];
        }
        $cmp = strnatcasecmp($a['file'], $b['file']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return $a['pos'] - $b['pos'];
    });

    $picked = array();
    $used = 0;
    foreach ($scored as $hit) {
        if (count($picked) >= $limit) {
            break;
        }
        $text = memory_cut($hit['text'], 500);
        if ($used + strlen($text) > $budget) {
            continue; // over budget -- try a smaller chunk instead
        }
        $used += strlen($text);
        $picked[] = array('file' => $hit['file'], 'text' => $text);
    }
    return $picked;
}

/**
 * Build the memory section appended to the system prompt for this
 * turn: core.txt always (when present), then up to 3 chunks retrieved
 * for the user's message. Returns '' when there is nothing to add, so
 * a chat without memories looks exactly like before this feature.
 *
 * @param string $user_text The user's message for this turn ('' = core only)
 * @return string Memory block to append, or ''
 */
function memory_block_for($user_text) {
    $lines = array();

    $core = memory_read('core.txt');
    if (trim($core) !== '') {
        $lines[] = '- [core.txt] ' . memory_cut($core, 800);
    }

    $terms = memory_terms($user_text);
    if (count($terms) > 0) {
        foreach (memory_retrieve($terms) as $hit) {
            $lines[] = '- [' . $hit['file'] . '] ' . $hit['text'];
        }
    }

    if (empty($lines)) {
        return '';
    }
    return "Memories (true facts you permanently know about the user; "
        . "use them when relevant):\n" . implode("\n", $lines);
}

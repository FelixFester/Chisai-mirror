## Memories: long-term notes the model can "remember" (simple RAG)

The chat only sends the last 12 turns, so anything older is invisible
to the model. Memories fix that without a database, without
embeddings, without Composer -- just plain text files and a keyword
search:

- The folder is `data/memories/`, created automatically the first
  time any page runs (including setup.php). Every `.txt` or `.md`
  file in it is a memory. Fill it on the **Memories** page (nav bar)
  or by dropping files there with any text editor.
- **core.txt is special**: it is added to the system prompt on EVERY
  turn, matched or not. Put the few facts the companion must never
  forget there -- your name, key preferences, standing context. For
  small models this is the reliable path; retrieval is best-effort.
- **How retrieval works**: on each message the app tokenizes your
  text (common stopwords dropped), splits every memory file into
  paragraph-sized chunks, scores each chunk by how many of your
  words it contains (whole-word, case-insensitive), and appends the
  best 3 chunks (within a small character budget) to the system
  prompt as a "Memories" section. A message that shares no words
  with any memory adds nothing -- so small talk never dilutes the
  prompt.
- **README.txt** in the folder is the human explainer; it is never
  sent to the model, and the name is reserved on memories.php.
- Caps keep the payload small: core.txt up to 800 chars, each chunk
  up to 500 chars, 3 chunks per turn. Memory content is forced into
  valid UTF-8 so a binary junk file can't break the JSON request.

To verify memories actually reach YOUR model: put a distinctive fact
in a memory file, mention the topic in chat, and watch the reply --
or use the sysprompt-echo mock from `scripts/` (the same trick the
automated tests use). What you should NOT expect: the model
memorizing whole documents. Keyword retrieval needs your wording to
overlap the note's wording; short, factual notes ("My favorite band
is X") work far better than long diary entries. An embeddings-based
retriever may come later (see roadmap) -- it would replace one
function, `memory_retrieve()`, nothing else.
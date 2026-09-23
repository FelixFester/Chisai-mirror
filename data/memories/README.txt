This folder is the companion's long-term memory ("memories").

- Every .txt or .md file here (except this README) is a memory.
- On each chat message the app searches these files for text
  related to what you just said and adds the best matches to the
  system prompt, so the model "remembers" them.
- core.txt is special: it is ALWAYS added to the system prompt,
  every turn, matched or not. Put the few facts the companion
  must always know there (your name, key preferences, context).
- You can edit these files on the Memories page of the web UI,
  or drop plain files into this folder with any text editor.
- Keep files small and factual: a few short notes work better
  with small models than one huge document.

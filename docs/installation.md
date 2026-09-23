## Try it

1. Upload the files to any PHP host (or run `php -S localhost:8000`
   locally) and make sure `data/` is writable by PHP.
2. Visit `setup.php`, fill in the form (llama.cpp URL, names, theme),
   save. The `data/memories/` folder is generated for you.
3. You're redirected to `index.php` -- start chatting.
4. Open `settings.php` any time to change names, persona or theme
   without touching any file.
5. Teach the companion lasting facts on the **Memories** page:
   `core.txt` is sent every turn; other files are searched per
   message (see the Memories section above).
6. Something not connecting? `setup.php` -> **Test llama.cpp
   connection**.
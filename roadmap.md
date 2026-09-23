## Roadmap*

Deliberately simplified for now; the plan is to stabilize the core
before widening again:

1. ~~Core skeleton~~ (done)
2. ~~Richer settings: persona/system prompt editing~~ (done, on
   settings.php)
3. ~~RAG as memories~~ (done, dependency-free: `data/memories/` plain
   files + keyword retrieval + always-on `core.txt`, managed on
   memories.php)
4. **Next (stabilization):** let the model gather persona details better
   (user name, companion name, personality) more reliably instead of
   relying only on the static system prompt line; better long-chat
   history trimming/summarization; optionally smarter memory
   retrieval (embeddings) if keyword overlap proves too coarse.
5. **Eventually, only once every idea above is proven:** revisit
   multi-user (accounts/tokens/registration), remote providers
   (e.g. Featherless AI) and a hosted-mode selector -- re-added one
   at a time, with the same test coverage the core has now.
6. WAP/WML content negotiation (serve `text/vnd.wap.wml` to browsers
   that need it, plain HTML to everything else) -- the theming system
   in `lib/ui.php` + `themes/` already covers the "style it like a
   WAP page" part on the HTML side.
   
----

*AI generated but almost accurate. Created by GLM-5.3-Flash and Claude.
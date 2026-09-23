## Troubleshooting chat errors

Open `setup.php` on an installed site and use **Test llama.cpp
connection** -- it sends one tiny request to your endpoint and prints
the exact result or the full error text. What the errors mean:

- **"Could not reach llama.cpp server ...: could not connect -- is the
  llama.cpp server running?"** -- nothing is listening on that port.
  Is the server running, and does the port in the endpoint match (your
  `llama serve --port N`)?
- **"Could not reach ...: no reply within N s"** (or the stream
  wording "request timed out after N s") -- the server accepted the
  request but inference didn't finish in time. CPU-only boxes are
  slow: a weak laptop can sit at ~85 ms/token for prompt eval and
  ~145 ms/token for generation, so a 300-token reply takes ~45 s plus
  prompt time. Raise the limit by adding e.g.
  `'llamacpp_timeout' => 300,` to `data/config.php` (default 120,
  floor 10, ceiling 3600).
- **"Could not reach ...: the connection was closed before a complete
  response arrived ... Empty reply from server"** -- the TCP connection
  died with zero bytes of reply. Typical causes: the server crashed or
  was restarted mid-request, or a system proxy is in the way (see
  below).
- **"The connection to llama.cpp server ... closed before a full HTTP
  response arrived"** (worded as a plain sentence, with no curl detail
  after it) -- on CURRENT releases this is the last-resort wording for
  the same situation as above. On builds BEFORE the transport fix it
  also appeared on EVERY successful reply when PHP has no curl
  extension: the stream fallback mis-parsed the `HTTP/1.1 200 OK`
  status line (code is in the middle of the line, not at the end), so
  a perfectly good answer looked like a dead connection while the
  llama.cpp log showed the request completing normally. If your llama
  terminal shows normal completions but the chat always errors --
  re-deploy the current `lib/api.php`.
- **System proxies**: the app NEVER routes loopback endpoints
  (`127.0.0.1`, `localhost`, `[::1]`) through an HTTP proxy, even if
  `http_proxy`/`HTTP_PROXY` is set in the environment -- PHP curl picks
  those variables up automatically and would otherwise send your local
  llama traffic through the proxy. Additionally, if curl itself dies
  before any response, the request is retried once over PHP's stream
  wrappers (a completely different code path).
- **"The model returned an empty reply"** -- the model ended its turn
  immediately. Common with raw completion models (GPT-2 style); switch
  to a chat-tuned model.
- **"HTTP 4xx/5xx -- <server message>"** -- llama.cpp rejected the
  request; the server's own message follows (e.g. chat-template
  problems with base models). A bare "HTTP 400 from ... Body: ..."
  means the response wasn't the usual OpenAI-style error JSON.
- **"Unexpected response shape ... Body starts with: <html ...>"** --
  the endpoint URL is probably the server root or another wrong path;
  it must be the full `/v1/chat/completions` route.
- **"Error: HTTP 0" (old releases only)** -- versions before the HTTP
  layer rewrite collapsed every failure into the numeric HTTP status,
  and "0" meant "no response ever arrived" (timeout or refused).
  Current releases never print this; if you still see it, you are
  running an old copy of `lib/api.php` -- re-deploy the latest files.

Note: when a reply fails, your message is kept in the conversation and
the error is shown once above it -- so you can simply send again after
fixing the cause.
## Where to get llama.cpp and how to run it

1. Get it:
   - **Official app site:** https://llama.app/ -- installs a `llama`
     binary (e.g. under `~/.llama-app/`). Then:
         llama serve -m /path/to/model.gguf --port 8080
     Note: an upcoming release will change the default port to 9931
     (upstream notice), so keep passing `--port 8080` explicitly to
     match the default endpoint below.
   - GitHub: https://github.com/ggml-org/llama.cpp
   - Windows / easiest: grab a prebuilt zip from the Releases page
     (pick `win-x64` build; there are CUDA and Vulkan variants if you
     have a GPU).
   - macOS: `brew install llama.cpp`
   - Linux / from source:
         git clone https://github.com/ggml-org/llama.cpp
         cd llama.cpp
         cmake -B build
         cmake --build build --config Release -j
     The server binary ends up at `build/bin/llama-server`.
2. Download a model in GGUF format from Hugging Face (search
   "<model name> GGUF"). Use **chat-tuned / instruct** models -- NOT
   raw completion models:
   - Good tiny CPU models: Qwen2.5-0.5B-Instruct, SmolLM2-360M-Instruct,
     LFM2-1.2B, Qwen2.5-3B-Instruct, Llama-3.2-3B-Instruct.
   - **Avoid GPT-2 and similar base models**: they have no chat
     template and no notion of dialogue. Typical results are an empty
     reply (the app reports that explicitly) or text that just
     rambles on. If the app shows "HTTP 4xx" with a template message
     from llama.cpp, that's the model, not the app.
   - 7-8B models (Mistral-7B-Instruct, Llama-3.1-8B-Instruct) are
     noticeably smarter but want ~6-8 GB RAM (or VRAM).
   Rule of thumb: choose the Q4_K_M quant whose file size fits in
   your free RAM with 1-2 GB to spare.
3. Start the server:
       ./llama-server -m your-model.gguf --port 8080
   (8080 is the default port, so `--port` is optional. Add
   `-ngl 99` to offload to GPU if you have one.)
4. Leave it running, then run `setup.php`. The default URL
   `http://127.0.0.1:8080/v1/chat/completions` already matches.
5. Quick sanity check without the app:
       curl http://127.0.0.1:8080/v1/chat/completions \
         -H "Content-Type: application/json" \
         -d '{"messages":[{"role":"user","content":"Hi"}],"max_tokens":50}'

If PHP and llama-server run on different machines, point setup.php at
`http://<that-machine>:8080/v1/chat/completions` (works over LAN or a
tunnel such as tailscale/cloudflared). If you started llama-server
with `--api-key <secret>`, extend `llamacpp_chat()` to send an
`Authorization: Bearer ...` header -- the payload side already matches.
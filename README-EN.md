# Chīsai (小さい)

A deliberately simple PHP web UI for AI companionship, inspired by Replika AI, [HCMS](https://atomgit.com/felixfester/HCMS), Odysseus, and [ch.at](http://ch.at).


> Currently in early stage of development and tested only at 30%. Expect a lot of AI generated text/code* and that some features _might_ not work as they originally supposed to. Find more updates and things in [our Telegram channel](https://t.me/+fgCDiyU802s1NWZi) or on [bilibili](https://space.bilibili.com/661091175/).


## Features

- **Chat** - Local AI models only (for now). Chat history automatically appears as files located in `data/chats/`.
- **Sessions** - Each conversation can be accessible from certain URL like `localhost/index.php?sid=....`. You can also find any file of your previous chat history, use ID from it's file name and add it after `?sid=...` to open it and continue chatting. However, it might not give much effect to an AI model.
- **Memories / RAG** - Gain context from text files. Create new text files manually in `data/memories/` or in UI. Additionally Markdown files also supported.
- **Lightweight and Private** - No JavaScript, No Cookies, No Composer. Modernized WAP interface that should work in any browsers. No trackers. No monetization.


## Installation

1. Download source code.
2. Install llama.cpp with command `curl -LsSf https://llama.app/install.sh | sh` from the terminal, as originally described [on this website](https://llama.app/). Alternatively, you can also find more options how to get it [there](https://github.com/ggml-org/llama.cpp#quick-start).
3. Install PHP from [official website](https://www.php.net/downloads.php). On Linux you can also just get php from repository of your distro.
4. Open folder with Chisai, then run command `php -S 127.0.0.1:8666`.
5. Open new tab or new window in terminal and run as well `~/.llama-app/llama serve -m /path/to/model.gguf --port 8080`.
6. Open your browser, then open `127.0.0.1:8666/setup.php` to start.


Check out docs folder to find more info and more additional instructions.


## Testing without llama.cpp

1. Start the mock server: `php -S 127.0.0.1:8081 test/mock_llamacpp_server.php`
2. Change llama.cpp server URL in setup.php on first launch to `http://127.0.0.1:8081/v1/chat/completions` or change `llamacpp_endpoint` parameter in `data/config.php`.


## Requirements

- PHP with **either** the `curl` extension **or** `allow_url_fopen` enabled.
- A llama.cpp `llama-server` you can reach (same machine or LAN).
- 2GB RAM minimum. **Recommended: 8GB RAM and higher** for more advanced and smart models suitable for companionship.

----

*Created with big help of Claude Sonnet 5, GLM-5.3-Flash and ChatGPT <3
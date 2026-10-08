# Chīsai (小さい)
[简体中文](README.md) | English | [日本語](README-JP.md)


A deliberately simple PHP web UI for AI companionship, inspired by R*plika AI, [HCMS](https://atomgit.com/felixfester/HCMS), Odysseus, and [ch.at](http://ch.at).



![Yeah, that's how simple it is.](chisaiscreenshot.jpg)



> Currently in early stage of development and tested only at 30%. Expect a lot of AI generated text/code* and that some features _might_ not work as they originally supposed to. Find more updates and things in [our Telegram channel](https://t.me/+fgCDiyU802s1NWZi) or on [bilibili](https://space.bilibili.com/661091175/).


## Features

- **Chat** - Local AI models only. Chat history automatically appears as files located in `data/chats/`.
- **Sessions** - Each conversation can be accessible from certain URL like `localhost/index.php?sid=....`. You can also find any file of your previous chat history, use ID from it's file name and add it after `?sid=...` to open it and continue chatting. However, it might not give much effect to an AI model.
- **Memories / RAG** - Gain context from text files. Create new text files manually in `data/memories/` or in UI. Additionally Markdown files also supported.
- **Lightweight and Private** - No JavaScript, No Cookies, No Composer. Modernized WAP interface that should work in any browsers. No trackers. No monetization.


## Installation

1. Download source code.
2. Install llama.cpp with command `curl -LsSf https://llama.app/install.sh | sh` from the terminal, as originally described [on this website](https://llama.app/). Alternatively, you can also find more options how to get it [there](https://github.com/ggml-org/llama.cpp#quick-start).
3. Install PHP from [official website](https://www.php.net/downloads.php). On Linux you can also just get php from repository of your distro.
4. Open folder with Chisai, then open terminal in it and run command `php -S 127.0.0.1:8666`.
5. Open new tab or new window in terminal and run as well `~/.llama-app/llama serve -m /path/to/model.gguf --port 8080`.
6. Open your browser, then open `127.0.0.1:8666/setup.php` to start.


Check out docs folder to find more info and more additional instructions.


## Testing without llama.cpp

1. Start the mock server: `php -S 127.0.0.1:8081 test/mock_llamacpp_server.php`
2. Change llama.cpp server URL in setup.php on first launch to `http://127.0.0.1:8081/v1/chat/completions` or change `llamacpp_endpoint` parameter in `data/config.php`.


## Which models to try?

1. [Llama-3.1-8B-Instruct](https://huggingface.co/unsloth/Llama-3.1-8B-Instruct-GGUF) (~6GB RAM) 🌟
2. [gemma-3-1b-it](https://huggingface.co/ggml-org/gemma-3-1b-it-GGUF) (~4GB RAM)
3. [Llama-3.2-3B-Instruct](https://huggingface.co/unsloth/Llama-3.2-3B-Instruct-GGUF) (~3GB RAM)
4.  [Llama-3.2-1B-Instruct](https://huggingface.co/unsloth/Llama-3.2-1B-Instruct-GGUF) (~1.5GB RAM)
5. [gemma-3-270m-it](https://huggingface.co/unsloth/gemma-3-270m-it-GGUF) (~0.8GB RAM)
6.  [LFM2.5-230M](https://huggingface.co/LiquidAI/LFM2.5-230M-GGUF) (~0.5GB RAM)

The RAM figures assume Q4_K_M quantization (standard sweet spot for local use). Actual usage can vary depending on your specific quantization choice, context length, and system overhead.


Search more on [Hugging Face](https://huggingface.co/models).


## Requirements

- PHP with **either** the `curl` extension **or** `allow_url_fopen` enabled.
- A llama.cpp `llama-server` you can reach (same machine or LAN).
- 2GB RAM minimum. **Recommended: 8GB RAM and higher** for more advanced and smart models suitable for companionship.

Currently, installation and simple tests has been done only in Linux. Keep in mind that you still can install Chīsai even if you don't use Linux at all, but certain things probably would look and work differently on Windows, Mac OS or Android.

----

*Created with big help of Claude Sonnet 5, GLM-5.3-Flash and ChatGPT <3
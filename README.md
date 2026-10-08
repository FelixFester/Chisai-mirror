# Chīsai (小さい)
简体中文 | [English](README-EN.md) | [日本語](README-JP.md)


一个刻意保持极简的 PHP 网页版 AI 陪伴界面，灵感来自 R*plika AI、[HCMS](https://atomgit.com/felixfester/HCMS)、Odysseus 和 [ch.at](http://ch.at)。



![没错，就是这么简单。](chisaiscreenshot.jpg)



> 目前处于早期开发阶段，仅测试了约 30%。请注意，其中包含大量 AI 生成的文本/代码*，部分功能可能无法按预期工作。更多更新和内容请见[我们的 Telegram 频道](https://t.me/+fgCDiyU802s1NWZi)或 [bilibili](https://space.bilibili.com/661091175/)。


## 功能

- **聊天** - 仅支持本地 AI 模型。聊天记录会自动以文件形式保存在 `data/chats/` 中。
- **会话** - 每个对话都可以通过特定的 URL 访问，例如 `localhost/index.php?sid=....`。你也可以找到任意一份以往的聊天记录文件，取其文件名中的 ID，加在 `?sid=...` 之后即可打开并继续聊天。不过，这对 AI 模型可能不会有太大效果。
- **记忆 / RAG** - 从文本文件中获取上下文。可以在 `data/memories/` 中手动创建新的文本文件，也可以在界面中创建。同时也支持 Markdown 文件。
- **轻量且私密** - 无 JavaScript，无 Cookie，无 Composer。现代化的 WAP 界面，应可在任何浏览器中运行。无追踪器，无变现。


## 安装

1. 下载源代码。
2. 在终端中运行命令 `curl -LsSf https://llama.app/install.sh | sh` 安装 llama.cpp，该方法最初在[此网站](https://llama.app/)上介绍。你也可以在[这里](https://github.com/ggml-org/llama.cpp#quick-start)找到更多获取方式。
3. 从[官方网站](https://www.php.net/downloads.php)安装 PHP。在 Linux 上，你也可以直接从发行版的软件源安装 php。
4. 打开 Chisai 所在的文件夹，在其中打开终端并运行命令 `php -S 127.0.0.1:8666`。
5. 在终端中新开一个标签页或窗口，同样运行 `~/.llama-app/llama serve -m /path/to/model.gguf --port 8080`。
6. 打开浏览器，访问 `127.0.0.1:8666/setup.php` 开始使用。


请查看 docs 文件夹以获取更多信息和补充说明。


## 不使用 llama.cpp 进行测试

1. 启动模拟服务器：`php -S 127.0.0.1:8081 test/mock_llamacpp_server.php`
2. 首次启动时在 setup.php 中将 llama.cpp 服务器 URL 改为 `http://127.0.0.1:8081/v1/chat/completions`，或者修改 `data/config.php` 中的 `llamacpp_endpoint` 参数。


## 可以试试哪些模型？

1. [Llama-3.1-8B-Instruct](https://huggingface.co/unsloth/Llama-3.1-8B-Instruct-GGUF)（约 6GB 内存）🌟
2. [gemma-3-1b-it](https://huggingface.co/ggml-org/gemma-3-1b-it-GGUF)（约 4GB 内存）
3. [Llama-3.2-3B-Instruct](https://huggingface.co/unsloth/Llama-3.2-3B-Instruct-GGUF)（约 3GB 内存）
4. [Llama-3.2-1B-Instruct](https://huggingface.co/unsloth/Llama-3.2-1B-Instruct-GGUF)（约 1.5GB 内存）
5. [gemma-3-270m-it](https://huggingface.co/unsloth/gemma-3-270m-it-GGUF)（约 0.8GB 内存）
6. [LFM2.5-230M](https://huggingface.co/LiquidAI/LFM2.5-230M-GGUF)（约 0.5GB 内存）

上述内存数值基于 Q4_K_M 量化（本地使用的标准最佳平衡点）。实际占用可能因所选量化方式、上下文长度和系统开销而异。


可在 [Hugging Face](https://huggingface.co/models) 上搜索更多模型。


## 系统要求

- 启用了 `curl` 扩展**或** `allow_url_fopen` 的 PHP。
- 可访问的 llama.cpp `llama-server`（同一台机器或局域网内均可）。
- 最低 2GB 内存。**建议 8GB 及以上**，以运行更先进、更智能、适合陪伴场景的模型。

目前，安装和简单测试仅在 Linux 上完成。请注意，即使你完全不使用 Linux，仍然可以安装 Chīsai，但某些部分在 Windows、Mac OS 或 Android 上的外观和行为可能有所不同。

----

*在 Claude Sonnet 5、GLM-5.3-Flash 和 ChatGPT 的大力帮助下创建 <3
Translated by Claude Sonnet 5.5.

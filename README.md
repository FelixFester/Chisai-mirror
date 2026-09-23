# Chīsai (小さい)

一个刻意保持极简的 PHP Web UI，专为 AI 陪伴设计，灵感来源于 Replika AI、[HCMS](https://atomgit.com/felixfester/HCMS)、Odysseus 和 [ch.at](http://ch.at)。


>  目前处于开发的早期阶段，仅完成了 30% 的测试。请预期项目中会有大量 AI 生成的文本/代码*，并且某些功能可能无法按最初设想的那样工作。请在我们的 [Telegram 频道](https://t.me/+fgCDiyU802s1NWZi) 或 [bilibili](https://space.bilibili.com/661091175/) 上获取更多更新和信息。


## 功能特性

- **聊天 (Chat)** - 仅支持本地 AI 模型（目前）。聊天记录会自动作为文件保存在 `data/chats/` 目录中。
- **会话 (Sessions)** - 每个对话都可以通过特定的 URL 访问，例如 `localhost/index.php?sid=....`。你也可以找到之前聊天记录的任何文件，提取其文件名中的 ID 并将其添加在 `?sid=...` 之后来打开并继续聊天。不过，这可能对 AI 模型的上下文效果影响不大。
- **记忆 / RAG (Memories / RAG)** - 从文本文件中获取上下文。可以在 `data/memories/` 目录中手动创建新文本文件，或通过 UI 创建。此外，也支持 Markdown 文件。
- **轻量且私密 (Lightweight and Private)** - 无 JavaScript，无 Cookies，无 Composer。现代化的 WAP 界面，应能在任何浏览器中正常运行。无追踪器，无商业化。

## 安装

1. 下载源代码。
2. 在终端中使用命令 `curl -LsSf https://llama.app/install.sh | sh` 安装 llama.cpp，正如[该网站](https://llama.app/)上最初描述的那样。或者，你也可以在[那里](https://github.com/ggml-org/llama.cpp#quick-start)找到更多获取它的选项。
3. 从[官方网站](https://www.php.net/downloads.php)安装 PHP。在 Linux 上，你也可以直接从你的发行版软件仓库中安装 php。
4. 打开包含 Chīsai 的文件夹，然后运行命令 `php -S 127.0.0.1:8666`。
5. 在终端中打开新标签页或新窗口，并运行 `~/.llama-app/llama serve -m /path/to/model.gguf --port 8080`。
6. 打开浏览器，访问 `127.0.0.1:8666/setup.php` 开始使用。
7. 查看 `docs` 文件夹以获取更多信息和附加说明。


## 在不使用 llama.cpp 的情况下进行测试

1. 启动模拟服务器：`php -S 127.0.0.1:8081 test/mock_llamacpp_server.php`
2. 在首次启动时，将 `setup.php` 中的 llama.cpp 服务器 URL 更改为 `http://127.0.0.1:8081/v1/chat/completions`，或者更改 `data/config.php` 中的 `llamacpp_endpoint` 参数。


## 运行要求

- 启用了 `curl` 扩展或 `allow_url_fopen` 的 PHP。
- 你可以访问的 llama.cpp `llama-server`（同一台机器或局域网内）。
- 最低 2GB 内存。**推荐：8GB 及以上内存**，以运行更适合陪伴的高级、更智能的模型。


*本项目在 Claude Sonnet 5、GLM-5.3-Flash 和 ChatGPT 的大力帮助下创建 <3

Translated to Chinese simplified by Qwen3.7-Plus. Check README-EN.md for original version.
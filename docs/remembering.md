## Making sure the model really sees your names and persona

Two tools on the setup.php "already set up" page answer this
question directly, for YOUR server and YOUR model:

1. **"What the model receives"** shows the exact system prompt text
   sent as the FIRST message of every request. This part is
   unconditional: llama.cpp applies the model's chat template to the
   whole message list -- system message included -- so whatever is
   shown there IS in the model's context window on every single turn.
   The app never sends a request without it (the test suite proves
   this with a mock server that echoes the system prompt back).
2. **"Test persona reach"** goes one step further and checks whether
   the model actually FOLLOWS the prompt: it sends your real system
   prompt (names + persona) plus one instruction -- "reply with ONLY
   the user's name" -- and checks whether the reply contains your
   configured user name.

Seeing and following are two different things, and it matters for
small models:

- **Receiving** the prompt is guaranteed by the app (see 1). There is
  no setting in llama-server that drops system messages; every
  message in the list goes through the chat template.
- **Following** the prompt is a model skill, and instruction-
  following is exactly what tiny models are worst at. A 230M-500M
  parameter model may pass the persona-reach test (simple name
  recall) and still drift mid-conversation; raw completion models
  (GPT-2-style, no chat template) fail by design because "system" is
  just more text to them.

If the persona-reach test fails, in order of what usually helps:

1. Switch to a chat-tuned instruct model. Qwen2.5-0.5B-Instruct is a
   reliable floor; SmolLM2-360M-Instruct passes simple name recall;
   LFM2-1.2B and Qwen2.5-3B-Instruct are comfortable.
2. Keep the persona note short. Every extra sentence costs attention
   on a small model; a 230M model with a 500-char persona will start
   ignoring parts of its instructions.
3. Remember long chats dilute small models' attention on the system
   prompt. The app already sends only the last 12 turns to keep the
   window tight; on very small models, starting a new chat resets
   focus.
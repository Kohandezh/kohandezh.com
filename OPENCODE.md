# OPENCODE.md — how opencode is used in this project

> Working notes for driving `opencode` from the CLI on kohandezh.com, plus a
> full index of the models available to this machine. Everything in the
> "Gotchas" section was learned the hard way during real work — read it before
> assuming a model is unavailable.

---

## 1. The two ways to invoke it

```bash
# Non-interactive — what automation should use.
opencode run -m <provider>/<model> "<prompt>"

# Interactive TUI — model picker via /model, session history, etc.
opencode
```

Useful flags on `run`:

| Flag | Purpose |
|---|---|
| `-m, --model` | `provider/model`, e.g. `zai-coding-plan/glm-5.2` |
| `--print-logs` | send logs to stderr (essential for debugging) |
| `--log-level` | `DEBUG` / `INFO` / `WARN` / `ERROR` |
| `--format json` | raw JSON events instead of formatted output |
| `-c, --continue` | continue the previous session |
| `-f, --file` | attach files to the message |
| `--agent` | pick an agent definition |

```bash
opencode models        # print the full catalogue (the index below)
opencode auth list     # which providers hold credentials (no secrets shown)
```

Config lives at `~/.config/opencode/opencode.jsonc`; credentials at
`~/.local/share/opencode/auth.json`. **Never print either file.**

---

## 2. Gotchas that cost real time

### 2.1 `opencode run` only speaks CHAT. Image/TTS/video models are not chat models.

This is the big one. `opencode models` prints a **catalogue**, not a list of
things `run` can drive. `opencode run -m openai/gpt-image-1.5 "..."` fails with:

```
Error: The requested model, 'gpt-image-1.5' was not found.
```

That error is **not** a permissions problem and **not** an unverified
organization. `run` starts an agent loop over `/v1/chat/completions`, and image
models do not exist on that endpoint — so the provider answers "not found".

**Image generation must go to the images API directly:**

```bash
# key is read from auth.json into a header file; never echoed
python3 -c "import json,os;print('Authorization: Bearer '+json.load(open(os.path.expanduser('~/.local/share/opencode/auth.json')))['openai']['key'])" > h.txt
chmod 600 h.txt

# text -> image
curl -s -H @h.txt -H "Content-Type: application/json" \
  -d '{"model":"gpt-image-1.5","prompt":"...","size":"1536x1024","n":1}' \
  https://api.openai.com/v1/images/generations

# image -> image (KEEPS an existing character/subject — use this for sprites)
curl -s -H @h.txt \
  -F model=gpt-image-1.5 -F image=@reference.png \
  -F size=1536x1024 -F background=transparent -F output_format=png \
  -F 'prompt=Keep this exact character unchanged. Change only ...' \
  https://api.openai.com/v1/images/edits
```

Both return `data[0].b64_json` — base64-decode it to a file.

Notes that mattered in practice:
- `background=transparent` with `output_format=png` gives a **real alpha
  channel** (verified ~79% fully-transparent pixels), so no green-screen keying.
- `/v1/images/edits` with a reference is how you keep a character consistent
  across frames. A text description alone reinvents the subject.
- Large reference uploads are slow. A 2.3 MB input exceeded a 280 s timeout and
  returned an EMPTY body; downscaling the reference to ~320 KB fixed it. Always
  check for a zero-byte response before parsing JSON.

The same logic applies to the other non-chat families below: `*-tts-*`,
`veo-*` (video), `lyria-*` (music), `*embedding*`, `gpt-realtime-*`.

### 2.2 Do not run opencode workers in parallel from the same project

Four concurrent `opencode run` processes in one repo sat **27 minutes**, burned
~45 s CPU each and wrote nothing. Six concurrent ones all timed out with empty
output. The same model run **sequentially** answered in ~30 s.

Run them one at a time. If you must parallelise, give each worker its own
working directory and verify output appears early.

### 2.3 Verify a model before trusting a batch to it

`opencode-go/glm-5.3` has twice exited 0 without writing its output file.
`zai-coding-plan/glm-5.2` has been reliable for translation work.
`openai/gpt-5.4-mini` answers a smoke test in ~30 s.

Smoke test before committing a long job to a model:

```bash
opencode run -m <provider>/<model> "Reply with exactly this word: PONG"
```

### 2.4 Always QA-gate generated output

For translation work, gate on: key-set equality with the source, inline-markup
parity tag-for-tag, target-script presence, and survival of protected technical
terms. A gate that rejects a good value is better than one that passes a bad
one — but check the gate itself before "fixing" the content. One Russian
rejection here was the *rule* being wrong (`RSA & Diffie-Hellman` legitimately
carries no Cyrillic), not the translation.

---

## 3. Verified on this machine

| Path | Status | Evidence |
|---|---|---|
| `openai/*` chat via `run` | works | `gpt-5.4-mini` → `PONG` |
| `openai/gpt-image-1.5` via **images API** | works | generated 2.3 MB PNG, real alpha |
| `openai/gpt-image-*` via `opencode run` | fails by design | wrong endpoint (see 2.1) |
| `zai-coding-plan/glm-5.2` via `run` | works | translation batches |
| `google/*-image` | quota `limit: 0` | free tier, needs billing |
| Parallel `run` workers | unreliable | see 2.2 |

The OpenAI key can see these image models (`GET /v1/models`):
`gpt-image-1`, `gpt-image-1-mini`, `gpt-image-1.5`, `gpt-image-2`,
`gpt-image-2-2026-04-21`, `chatgpt-image-latest`.

---

## 4. Model index (120 models)

Capability tags: **chat** = works with `opencode run` · everything else needs its
own endpoint (see §2.1). Full IDs are given so they can be copied verbatim.

### opencode (free tier)  (7 models)

| Model ID | Cap | Note |
|---|---|---|
| `opencode/big-pickle` | chat |  |
| `opencode/deepseek-v4-flash-free` | chat |  |
| `opencode/hy3-free` | chat |  |
| `opencode/laguna-s-2.1-free` | chat |  |
| `opencode/mimo-v2.5-free` | chat |  |
| `opencode/nemotron-3-ultra-free` | chat |  |
| `opencode/nemotron-3.5-lightning-free` | chat |  |

### opencode-go  (19 models)

| Model ID | Cap | Note |
|---|---|---|
| `opencode-go/deepseek-v4-flash` | chat |  |
| `opencode-go/deepseek-v4-pro` | chat |  |
| `opencode-go/glm-5.1` | chat |  |
| `opencode-go/glm-5.2` | chat |  |
| `opencode-go/glm-5.3` | chat |  |
| `opencode-go/gpt-5.6-luna` | chat |  |
| `opencode-go/grok-4.5` | chat |  |
| `opencode-go/hy3` | chat |  |
| `opencode-go/kimi-k2.6` | chat |  |
| `opencode-go/kimi-k2.7-code` | chat |  |
| `opencode-go/kimi-k3` | chat |  |
| `opencode-go/mimo-v2.5` | chat |  |
| `opencode-go/mimo-v2.5-pro` | chat |  |
| `opencode-go/minimax-m2.7` | chat |  |
| `opencode-go/minimax-m3` | chat |  |
| `opencode-go/qwen3.6-plus` | chat |  |
| `opencode-go/qwen3.7-max` | chat |  |
| `opencode-go/qwen3.7-plus` | chat |  |
| `opencode-go/qwen3.8-max` | chat |  |

### google  (38 models)

| Model ID | Cap | Note |
|---|---|---|
| `google/deep-research-max-preview-04-2026` | research | research endpoint |
| `google/deep-research-preview-04-2026` | research | research endpoint |
| `google/gemini-2.5-computer-use-preview-10-2025` | computer-use | computer-use endpoint |
| `google/gemini-2.5-flash` | chat |  |
| `google/gemini-2.5-flash-image` | img | images API — NOT usable with `opencode run` |
| `google/gemini-2.5-flash-lite` | chat |  |
| `google/gemini-2.5-flash-preview-tts` | tts | speech synthesis — not chat |
| `google/gemini-2.5-pro` | chat |  |
| `google/gemini-2.5-pro-preview-tts` | tts | speech synthesis — not chat |
| `google/gemini-3-flash-preview` | chat |  |
| `google/gemini-3-pro-image` | img | images API — NOT usable with `opencode run` |
| `google/gemini-3-pro-image-preview` | img | images API — NOT usable with `opencode run` |
| `google/gemini-3.1-flash-image` | img | images API — NOT usable with `opencode run` |
| `google/gemini-3.1-flash-image-preview` | img | images API — NOT usable with `opencode run` |
| `google/gemini-3.1-flash-lite` | chat |  |
| `google/gemini-3.1-flash-lite-image` | img | images API — NOT usable with `opencode run` |
| `google/gemini-3.1-flash-live-preview` | realtime | realtime/streaming API — not chat |
| `google/gemini-3.1-flash-tts-preview` | tts | speech synthesis — not chat |
| `google/gemini-3.1-pro-preview` | chat |  |
| `google/gemini-3.1-pro-preview-customtools` | chat |  |
| `google/gemini-3.5-flash` | chat |  |
| `google/gemini-3.5-flash-lite` | chat |  |
| `google/gemini-3.5-live-translate-preview` | realtime | realtime/streaming API — not chat |
| `google/gemini-3.6-flash` | chat |  |
| `google/gemini-3.7-flash` | chat |  |
| `google/gemini-embedding-001` | embed | embeddings endpoint — not chat |
| `google/gemini-embedding-2` | embed | embeddings endpoint — not chat |
| `google/gemini-flash-latest` | chat |  |
| `google/gemini-flash-lite-latest` | chat |  |
| `google/gemini-omni-flash-preview` | realtime | realtime/streaming API — not chat |
| `google/gemini-robotics-er-1.6-preview` | chat |  |
| `google/gemma-4-26b-a4b-it` | chat |  |
| `google/gemma-4-31b-it` | chat |  |
| `google/lyria-3-clip-preview` | music | music generation — not chat |
| `google/lyria-3-pro-preview` | music | music generation — not chat |
| `google/veo-3.1-fast-generate-preview` | video | video generation — not chat |
| `google/veo-3.1-generate-preview` | video | video generation — not chat |
| `google/veo-3.1-lite-generate-preview` | video | video generation — not chat |

### openai  (48 models)

| Model ID | Cap | Note |
|---|---|---|
| `openai/chatgpt-image-latest` | img | images API — NOT usable with `opencode run` |
| `openai/gpt-4.1` | chat |  |
| `openai/gpt-4.1-mini` | chat |  |
| `openai/gpt-4o` | chat |  |
| `openai/gpt-4o-2024-08-06` | chat |  |
| `openai/gpt-4o-2024-11-20` | chat |  |
| `openai/gpt-4o-mini` | chat |  |
| `openai/gpt-5` | chat |  |
| `openai/gpt-5-mini` | chat |  |
| `openai/gpt-5-nano` | chat |  |
| `openai/gpt-5-pro` | chat |  |
| `openai/gpt-5.1` | chat |  |
| `openai/gpt-5.2` | chat |  |
| `openai/gpt-5.2-chat-latest` | chat |  |
| `openai/gpt-5.2-pro` | chat |  |
| `openai/gpt-5.3-chat-latest` | chat |  |
| `openai/gpt-5.3-codex` | chat | coding |
| `openai/gpt-5.3-codex-spark` | chat | coding |
| `openai/gpt-5.4` | chat |  |
| `openai/gpt-5.4-fast` | chat |  |
| `openai/gpt-5.4-mini` | chat |  |
| `openai/gpt-5.4-mini-fast` | chat |  |
| `openai/gpt-5.4-nano` | chat |  |
| `openai/gpt-5.4-pro` | chat |  |
| `openai/gpt-5.5` | chat |  |
| `openai/gpt-5.5-fast` | chat |  |
| `openai/gpt-5.5-pro` | chat |  |
| `openai/gpt-5.6` | chat |  |
| `openai/gpt-5.6-fast` | chat |  |
| `openai/gpt-5.6-luna` | chat |  |
| `openai/gpt-5.6-luna-fast` | chat |  |
| `openai/gpt-5.6-luna-pro` | chat |  |
| `openai/gpt-5.6-pro` | chat |  |
| `openai/gpt-5.6-sol` | chat |  |
| `openai/gpt-5.6-sol-fast` | chat |  |
| `openai/gpt-5.6-sol-pro` | chat |  |
| `openai/gpt-5.6-terra` | chat |  |
| `openai/gpt-5.6-terra-fast` | chat |  |
| `openai/gpt-5.6-terra-pro` | chat |  |
| `openai/gpt-image-1-mini` | img | images API — NOT usable with `opencode run` |
| `openai/gpt-image-1.5` | img | images API — NOT usable with `opencode run` |
| `openai/gpt-image-2` | img | images API — NOT usable with `opencode run` |
| `openai/gpt-realtime-2.1` | realtime | realtime/streaming API — not chat |
| `openai/o3` | chat | reasoning |
| `openai/o3-pro` | chat | reasoning |
| `openai/text-embedding-3-large` | embed | embeddings endpoint — not chat |
| `openai/text-embedding-3-small` | embed | embeddings endpoint — not chat |
| `openai/text-embedding-ada-002` | embed | embeddings endpoint — not chat |

### rayen (Sharif — custom baseURL, OpenAI-compatible)  (3 models)

| Model ID | Cap | Note |
|---|---|---|
| `rayen/rayen-deepseek-v4-flash` | chat |  |
| `rayen/rayen-gemma4-31b` | chat |  |
| `rayen/rayen-qwen3.6-27b` | chat |  |

### zai-coding-plan  (5 models)

| Model ID | Cap | Note |
|---|---|---|
| `zai-coding-plan/glm-4.7` | chat |  |
| `zai-coding-plan/glm-5-turbo` | chat |  |
| `zai-coding-plan/glm-5.2` | chat |  |
| `zai-coding-plan/glm-5.2-highspeed` | chat |  |
| `zai-coding-plan/glm-5.3` | chat |  |
---

## 5. Recipe: character-consistent sprite frames

How the Kohan car sprites were produced, as a repeatable pattern:

1. Cut a clean reference pose from the existing atlas and upscale it onto a
   square transparent canvas.
2. `POST /v1/images/edits` with that reference + a prompt that says **"keep this
   exact character unchanged; change only X"**.
3. For the next frame, use the **previous output** as the reference so the new
   subject (the car) also stays consistent, not just the character.
4. Trim all frames to a **shared union bounding box** before export, so the
   subject does not jump between frames when they animate.
5. Export WebP at 2x the intended display width for retina.

---

## 6. Security rules

- Never print `auth.json`, `opencode.jsonc` secrets, or any API key.
- Read keys into a `chmod 600` header file rather than passing them in `argv`
  (argv is visible in `ps`).
- Delete the header file when finished.

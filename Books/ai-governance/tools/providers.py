#!/usr/bin/env python3
"""Unified model clients with policy enforcement, retries, and usage ledger.

  glm_chat(model, system, user, ...)        -> Z.ai coding-plan (OpenAI-compatible), thinking disabled
  gemini_generate(model, system, user, ...) -> google-genai SDK
  claude_agy(model, prompt, schema=None)    -> Antigravity CLI print mode (Sonnet 4.6 / Opus 4.6 Thinking)
Credentials are read at call time from ~/.local/share/opencode/auth.json and never logged.
"""
from __future__ import annotations
import json, os, random, re, subprocess, sys, tempfile, time
from pathlib import Path
import requests

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, policy  # noqa: E402

AUTH_PATH = os.path.expanduser("~/.local/share/opencode/auth.json")
ZAI_BASE = "https://api.z.ai/api/coding/paas/v4"
AGY = os.path.expanduser("~/.local/bin/agy")

class ProviderError(RuntimeError):
    pass

PolicyViolation = policy.PolicyViolation  # re-exported for callers

class QuotaExhausted(ProviderError):
    """Provider quota exhausted for a long period (e.g. Antigravity 'Individual quota reached'); callers should pause, not retry."""
    pass

def _key(provider: str) -> str:
    auth = json.load(open(AUTH_PATH))
    k = auth.get(provider, {}).get("key")
    if not k:
        raise ProviderError(f"no credential for provider {provider}")
    return k

def _backoff(attempt: int, base=5.0, cap=120.0):
    time.sleep(min(cap, base * (2 ** attempt)) * (0.7 + 0.6 * random.random()))

# --------------------------------------------------------------------------- GLM
def glm_chat(model: str, system: str, user: str, *, purpose="translation", temperature=0.2, max_tokens=8000,
             retries=4, timeout=300, job_id=None, json_mode=False) -> dict:
    policy.assert_allowed(purpose, "zai-coding-plan", model)
    key = _key("zai-coding-plan")
    body = {"model": model, "messages": [{"role": "system", "content": system}, {"role": "user", "content": user}],
            "temperature": temperature, "max_tokens": max_tokens, "thinking": {"type": "disabled"}}
    if json_mode:
        body["response_format"] = {"type": "json_object"}
    last = None
    for attempt in range(retries + 1):
        t = time.time()
        try:
            r = requests.post(ZAI_BASE + "/chat/completions", headers={"Authorization": f"Bearer {key}", "Content-Type": "application/json"},
                              json=body, timeout=timeout)
            dt = time.time() - t
            if r.status_code == 200:
                d = r.json()
                msg = d["choices"][0]["message"]
                text = (msg.get("content") or "").strip()
                u = d.get("usage", {})
                usage = {"input_tokens": u.get("prompt_tokens"), "output_tokens": u.get("completion_tokens"), "thinking_tokens": 0}
                db.record_usage(purpose, "zai-coding-plan", model, usage, dt, "ok", job_id)
                if not text:
                    raise ProviderError("empty content")
                return {"text": text, "usage": usage, "latency_s": dt, "model": model, "provider": "zai-coding-plan",
                        "finish_reason": d["choices"][0].get("finish_reason")}
            last = f"HTTP {r.status_code}: {r.text[:300]}"
            db.record_usage(purpose, "zai-coding-plan", model, {}, dt, f"http_{r.status_code}", job_id)
            if r.status_code in (400, 401, 403) and "1311" not in r.text:
                raise ProviderError(last)
        except (requests.RequestException, ProviderError, KeyError, ValueError) as e:
            last = str(e)[:300]
            if isinstance(e, ProviderError) and ("HTTP 40" in last and "429" not in last):
                raise
        _backoff(attempt)
    raise ProviderError(f"glm_chat failed after {retries+1} attempts: {last}")

# --------------------------------------------------------------------------- Gemini
def gemini_generate(model: str, system: str, user: str, *, purpose="translation", temperature=0.2, max_tokens=8000,
                    retries=4, job_id=None, json_schema=None) -> dict:
    policy.assert_allowed(purpose, "google", model)
    from google import genai
    from google.genai import types
    client = genai.Client(api_key=_key("google"))
    cfg = dict(system_instruction=system, temperature=temperature, max_output_tokens=max_tokens)
    if json_schema:
        cfg.update(response_mime_type="application/json", response_schema=json_schema)
    last = None
    for attempt in range(retries + 1):
        t = time.time()
        try:
            resp = client.models.generate_content(model=model, contents=user, config=types.GenerateContentConfig(**cfg))
            dt = time.time() - t
            um = resp.usage_metadata
            usage = {"input_tokens": getattr(um, "prompt_token_count", None), "output_tokens": getattr(um, "candidates_token_count", None),
                     "thinking_tokens": getattr(um, "thoughts_token_count", None)}
            text = (resp.text or "").strip()
            db.record_usage(purpose, "google", model, usage, dt, "ok", job_id)
            if not text:
                raise ProviderError("empty content")
            return {"text": text, "usage": usage, "latency_s": dt, "model": model, "provider": "google", "finish_reason": None}
        except Exception as e:
            last = str(e)[:300]
            db.record_usage(purpose, "google", model, {}, time.time() - t, "error", job_id)
            if "API key" in last or "PERMISSION" in last:
                raise ProviderError(last)
        _backoff(attempt, base=8)
    raise ProviderError(f"gemini_generate failed after {retries+1} attempts: {last}")

# --------------------------------------------------------------------------- Claude via Antigravity
def _claude_agy_route(model: str, prompt: str, *, purpose="review", schema: dict | None = None, retries=3, timeout=900, job_id=None) -> dict:
    """Antigravity route. model: claude-sonnet-4-6 (REVIEW_MAX) | claude-opus-4-6-thinking (REVIEW_ULTRA)."""
    policy.assert_allowed(purpose, "antigravity", model)
    last = None
    for attempt in range(retries + 1):
        t = time.time()
        with tempfile.TemporaryDirectory() as td:
            args = [AGY, "--model", model, "--output-format", "json", "--dangerously-skip-permissions", "--disable-slash-commands",
                    "--print-timeout", f"{timeout}s"]
            if schema:
                sp = Path(td) / "schema.json"; sp.write_text(json.dumps(schema), encoding="utf-8")
                args += ["--json-schema", str(sp)]
            pp = Path(td) / "prompt.txt"; pp.write_text(prompt, encoding="utf-8")
            # pass prompt via -p=@file is not supported; pass content directly (agy handles long args)
            args.append(f"-p={prompt}")
            try:
                p = subprocess.run(args, capture_output=True, text=True, timeout=timeout + 60, cwd=str(td))
                dt = time.time() - t
                out = p.stdout.strip()
                # agy prints one JSON envelope line (with "status"); tolerate stray lines before/after it
                d = {}
                dec = json.JSONDecoder()
                for l in out.splitlines():
                    l = l.strip()
                    if not l.startswith("{"):
                        continue
                    try:
                        obj, _ = dec.raw_decode(l)
                    except Exception:
                        continue
                    if isinstance(obj, dict) and "status" in obj:
                        d = obj
                u = d.get("usage", {})
                usage = {"input_tokens": u.get("input_tokens"), "output_tokens": u.get("output_tokens"), "thinking_tokens": u.get("thinking_tokens")}
                if d.get("status") == "SUCCESS" and (d.get("response") or d.get("structured_output")):
                    db.record_usage(purpose, "antigravity", model, usage, dt, "ok", job_id)
                    resp = d.get("response") or ""
                    parsed = None
                    if schema:
                        parsed = d.get("structured_output")
                        if parsed is None:
                            try:
                                parsed = json.loads(resp)
                            except Exception:
                                s, e = resp.find("{"), resp.rfind("}")
                                try:
                                    parsed = json.loads(resp[s:e + 1]) if s >= 0 and e > s else None
                                except Exception:
                                    parsed = None
                        if isinstance(parsed, dict):
                            parsed.pop("toolAction", None); parsed.pop("toolSummary", None)
                    return {"text": resp, "json": parsed, "usage": usage, "latency_s": dt, "model": model, "provider": "antigravity",
                            "conversation_id": d.get("conversation_id")}
                last = (d.get("error") or p.stderr or out)[:400]
                db.record_usage(purpose, "antigravity", model, usage, dt, "error", job_id)
                if "RESOURCE_EXHAUSTED" in last or "quota reached" in last.lower() or "quota" in last.lower() and "429" in last:
                    db.event("provider_quota_exhausted", {"provider": "antigravity", "model": model, "error": last[:200]})
                    raise QuotaExhausted(f"antigravity quota exhausted for {model}: {last[:200]}")
            except subprocess.TimeoutExpired:
                last = "timeout"
                db.record_usage(purpose, "antigravity", model, {}, time.time() - t, "timeout", job_id)
            except QuotaExhausted:
                raise
            except Exception as e:
                last = str(e)[:400]
        _backoff(attempt, base=10)
    raise ProviderError(f"claude_agy({model}) failed after {retries+1} attempts: {last}")

# --------------------------------------------------------------------------- Claude: alternative routes + dispatcher
import yaml as _yaml
_RCFG = _yaml.safe_load(open(ROOT / "config" / "reviewer.yaml", encoding="utf-8"))

class RouteUnavailable(ProviderError):
    def __init__(self, msg, minutes=360):
        super().__init__(msg); self.minutes = minutes

def _anthropic_key() -> str | None:
    k = os.environ.get("ANTHROPIC_API_KEY")
    if k: return k.strip()
    p = ROOT / "state" / "anthropic.key"
    if p.exists():
        v = p.read_text().strip()
        if v: return v
    try:
        return json.load(open(AUTH_PATH)).get("anthropic", {}).get("key")
    except Exception:
        return None

def _claude_api_route(model: str, prompt: str, *, purpose="review", schema=None, retries=3, timeout=900, job_id=None) -> dict:
    key = _anthropic_key()
    if not key:
        raise RouteUnavailable("no Anthropic API key (set ANTHROPIC_API_KEY or state/anthropic.key)", minutes=120)
    policy.assert_allowed(purpose, "anthropic", model)
    import anthropic
    api_model = _RCFG.get("api_model_ids", {}).get(model, model)
    budget = int(_RCFG.get("api_thinking_budget", {}).get(model, 6000))
    client = anthropic.Anthropic(api_key=key, timeout=timeout, max_retries=0)
    kwargs = dict(model=api_model, max_tokens=budget + 16000, thinking={"type": "enabled", "budget_tokens": budget})
    p = prompt + ("\n\nWhen done, call the `structured_output` tool exactly once with your final answer (JSON matching its schema)." if schema else "")
    kwargs["messages"] = [{"role": "user", "content": p}]
    if schema:
        kwargs["tools"] = [{"name": "structured_output", "description": "Final structured answer.", "input_schema": schema}]
        kwargs["tool_choice"] = {"type": "auto"}
    last = None
    for attempt in range(retries + 1):
        t = time.time()
        try:
            resp = client.messages.create(**kwargs)
            dt = time.time() - t
            usage = {"input_tokens": resp.usage.input_tokens, "output_tokens": resp.usage.output_tokens, "thinking_tokens": None}
            db.record_usage(purpose, "anthropic", api_model, usage, dt, "ok", job_id)
            parsed, text = None, ""
            for b in resp.content:
                if getattr(b, "type", "") == "tool_use" and b.name == "structured_output":
                    parsed = b.input
                elif getattr(b, "type", "") == "text":
                    text += b.text
            if schema and parsed is None:
                s, e = text.find("{"), text.rfind("}")
                try: parsed = json.loads(text[s:e + 1]) if s >= 0 else None
                except Exception: parsed = None
            return {"text": text or json.dumps(parsed, ensure_ascii=False), "json": parsed, "usage": usage, "latency_s": dt, "model": api_model, "provider": "anthropic"}
        except anthropic.AuthenticationError as e:
            raise RouteUnavailable(f"anthropic auth failed: {str(e)[:120]}", minutes=720)
        except anthropic.RateLimitError as e:
            last = str(e)[:200]; db.record_usage(purpose, "anthropic", api_model, {}, time.time() - t, "rate_limited", job_id)
        except anthropic.APIStatusError as e:
            last = str(e)[:200]; db.record_usage(purpose, "anthropic", api_model, {}, time.time() - t, f"http_{e.status_code}", job_id)
            if e.status_code in (400, 403, 404):
                raise RouteUnavailable(f"anthropic API rejected model/request: {last}", minutes=720)
        except Exception as e:
            last = str(e)[:200]
        _backoff(attempt, base=10)
    raise ProviderError(f"anthropic api failed after {retries+1} attempts: {last}")

def _claude_cli_route(model: str, prompt: str, *, purpose="review", schema=None, retries=2, timeout=900, job_id=None) -> dict:
    """Claude Code CLI headless route (needs `claude login`)."""
    policy.assert_allowed(purpose, "claude_cli", model)
    cli_model = _RCFG.get("api_model_ids", {}).get(model, model)
    last = None
    for attempt in range(retries + 1):
        t = time.time()
        args = ["claude", "-p", prompt, "--model", cli_model, "--output-format", "json"]
        if schema:
            args += ["--json-schema", json.dumps(schema)]
        try:
            p = subprocess.run(args, capture_output=True, text=True, timeout=timeout + 60, cwd=tempfile.gettempdir())
            dt = time.time() - t
            d = json.loads(p.stdout.strip().splitlines()[-1]) if p.stdout.strip() else {}
            if d.get("is_error"):
                msg = str(d.get("result", ""))[:200]
                if "authenticate" in msg.lower() or "login" in msg.lower():
                    raise RouteUnavailable(f"claude CLI not authenticated: {msg}", minutes=720)
                if "limit" in msg.lower():
                    raise QuotaExhausted(f"claude CLI usage limit: {msg}")
                last = msg
            else:
                mu = d.get("modelUsage") or {}
                u = next(iter(mu.values()), {}) if mu else {}
                usage = {"input_tokens": u.get("inputTokens"), "output_tokens": u.get("outputTokens"), "thinking_tokens": None}
                db.record_usage(purpose, "claude_cli", cli_model, usage, dt, "ok", job_id)
                parsed = d.get("structured_output")
                text = d.get("result") or ""
                if schema and parsed is None:
                    s, e = text.find("{"), text.rfind("}")
                    try: parsed = json.loads(text[s:e + 1]) if s >= 0 else None
                    except Exception: parsed = None
                return {"text": text, "json": parsed, "usage": usage, "latency_s": dt, "model": cli_model, "provider": "claude_cli"}
        except (RouteUnavailable, QuotaExhausted):
            raise
        except Exception as e:
            last = str(e)[:200]
        _backoff(attempt, base=10)
    raise ProviderError(f"claude CLI failed: {last}")

def _route_down_until(route: str) -> float:
    return float(db.kv_get(f"claude_route_down:{route}", 0) or 0)

def _mark_route_down(route: str, minutes: float, reason: str):
    db.kv_set(f"claude_route_down:{route}", time.time() + minutes * 60)
    db.event("claude_route_down", {"route": route, "minutes": round(minutes), "reason": reason[:200]})

def _reset_minutes(msg: str, default=60.0) -> float:
    m = re.search(r"Resets in (?:(\d+)h)?(?:(\d+)m)?", msg)
    if not m: return default
    return (int(m.group(1) or 0) * 60 + int(m.group(2) or 0)) + 3

def claude_call(model: str, prompt: str, *, purpose="review", schema: dict | None = None, retries=3, timeout=900, job_id=None) -> dict:
    """Dispatch a Claude 4.6 call over the configured routes (config/reviewer.yaml → claude_routes); skips routes marked down."""
    routes = _RCFG.get("claude_routes") or ["antigravity"]
    errors = []
    for route in routes:
        if _route_down_until(route) > time.time():
            continue
        fn = {"anthropic_api": _claude_api_route, "antigravity": _claude_agy_route, "claude_cli": _claude_cli_route}[route]
        try:
            return fn(model, prompt, purpose=purpose, schema=schema, retries=retries, timeout=timeout, job_id=job_id)
        except RouteUnavailable as e:
            _mark_route_down(route, e.minutes, str(e)); errors.append(f"{route}: {str(e)[:100]}")
        except QuotaExhausted as e:
            _mark_route_down(route, _reset_minutes(str(e)), str(e)); errors.append(f"{route}: quota ({str(e)[:80]})")
        except ProviderError as e:
            errors.append(f"{route}: {str(e)[:100]}")
    raise QuotaExhausted("all Claude routes unavailable → " + " | ".join(errors))

claude_agy = claude_call   # backwards-compatible name used across tools

def probe_routes() -> dict:
    out = {}
    for route in _RCFG.get("claude_routes") or []:
        fn = {"anthropic_api": _claude_api_route, "antigravity": _claude_agy_route, "claude_cli": _claude_cli_route}[route]
        t = time.time()
        try:
            r = fn("claude-sonnet-4-6", "Reply with exactly the word OK.", purpose="review", retries=0, timeout=90)
            out[route] = {"ok": True, "latency_s": round(time.time() - t, 1), "reply": r["text"][:20]}
        except Exception as e:
            out[route] = {"ok": False, "error": str(e)[:160]}
    return out

# --------------------------------------------------------------------------- dispatcher
def translate(provider: str, model: str, system: str, user: str, **kw) -> dict:
    if provider == "zai-coding-plan":
        return glm_chat(model, system, user, **kw)
    if provider == "google":
        return gemini_generate(model, system, user, **kw)
    raise ProviderError(f"unknown translation provider {provider}")

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "probe":
        print(json.dumps(probe_routes(), ensure_ascii=False, indent=1))
    else:
        r = glm_chat("glm-5.3-flash", "You are a translator.", "Translate to Persian: 'Risk tolerance'. Reply with Persian only.", max_tokens=100)
        print("GLM:", r["text"], r["latency_s"])

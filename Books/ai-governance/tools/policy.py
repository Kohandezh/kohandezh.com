#!/usr/bin/env python3
"""Model routing policy enforcement. Import and call assert_allowed() before every model call."""
from __future__ import annotations
import fnmatch, json, time
from pathlib import Path
import yaml

ROOT = Path(__file__).resolve().parent.parent
POLICY = yaml.safe_load(open(ROOT / "config" / "model-policy.yaml", encoding="utf-8"))
VIOLATIONS = ROOT / "work" / "logs" / "policy_violations.jsonl"

class PolicyViolation(RuntimeError):
    pass

def _denied_text(provider: str, model: str) -> bool:
    d = POLICY["DENY_TEXT_MODELS"]
    if provider in d.get("providers", []):
        return True
    full = f"{provider}/{model}".lower()
    for pat in d.get("patterns", []):
        p = pat.lower()
        if fnmatch.fnmatch(model.lower(), p) or fnmatch.fnmatch(full, p):
            return True
    return False

def _in_list(lst, provider, model) -> bool:
    for e in lst:
        if isinstance(e, dict) and e.get("provider") == provider and e.get("model") == model:
            return True
    return False

def assert_allowed(purpose: str, provider: str, model: str) -> None:
    """purpose ∈ {translation, review, escalation, image, embedding, orchestration, terminology, editorial, localization, qa}"""
    ok = False
    if purpose == "image":
        ok = _in_list(POLICY["ALLOW_IMAGE_MODELS"], provider, model)
    elif purpose == "embedding":
        ok = _in_list(POLICY["ALLOW_EMBEDDING_MODELS"], provider, model) and provider != "openai"
    elif purpose == "translation":
        ok = _in_list(POLICY["ALLOW_TRANSLATION_MODELS"], provider, model) and not _denied_text(provider, model)
    elif purpose in ("review", "terminology", "localization", "qa", "editorial"):
        r = POLICY["ALLOW_REVIEW_MODELS"]
        provs = {r["primary"]["provider"]} | set(POLICY.get("CLAUDE_ALTERNATE_PROVIDERS", []))
        ok = provider in provs and model in {r["primary"]["model"], "claude-sonnet-4-6"} and not _denied_text(provider, model)
    elif purpose == "escalation":
        r = POLICY["ALLOW_REVIEW_MODELS"]["escalation"]
        provs = {r["provider"]} | set(POLICY.get("CLAUDE_ALTERNATE_PROVIDERS", []))
        ok = provider in provs and model in {r["model"], "claude-opus-4-6", "claude-opus-4-6-thinking"}
    elif purpose == "orchestration":
        ok = not _denied_text(provider, model)
    elif purpose == "prereview":
        # GLM/Gemini pre-review pass: edits become a new TRANSLATION version; Claude REVIEW_MAX still reviews every chunk
        ok = _in_list(POLICY["ALLOW_TRANSLATION_MODELS"], provider, model) and not _denied_text(provider, model)
    elif purpose == "benchmark":
        # offline model comparison only (never writes production tables); any non-denied GLM/Gemini/Claude route
        ok = provider in {"zai-coding-plan", "google", "antigravity", "anthropic", "claude_cli"} and not _denied_text(provider, model)
    elif purpose == "review_alt":
        # non-Claude reviewer — ONLY with an explicit owner override in config/model-policy.yaml (OWNER_OVERRIDE_REVIEW.enabled)
        o = POLICY.get("OWNER_OVERRIDE_REVIEW") or {}
        ok = bool(o.get("enabled")) and _in_list(o.get("models", []), provider, model) and not _denied_text(provider, model)
    if not ok or (purpose != "image" and _denied_text(provider, model)):
        rec = {"ts": time.strftime("%Y-%m-%dT%H:%M:%S%z"), "purpose": purpose, "provider": provider, "model": model}
        VIOLATIONS.parent.mkdir(parents=True, exist_ok=True)
        with open(VIOLATIONS, "a", encoding="utf-8") as f:
            f.write(json.dumps(rec) + "\n")
        raise PolicyViolation(f"MODEL POLICY VIOLATION: purpose={purpose} provider={provider} model={model}")

if __name__ == "__main__":
    # self-test
    tests = [("translation", "zai-coding-plan", "glm-5.3", True), ("translation", "openai", "gpt-5.6", False),
             ("review", "antigravity", "claude-sonnet-4-6", True), ("review", "antigravity", "gpt-oss-120b-medium", False),
             ("image", "openai", "gpt-image-2", True), ("embedding", "openai", "text-embedding-3-large", False),
             ("translation", "opencode-go", "gpt-5.6-luna", False), ("escalation", "antigravity", "claude-opus-4-6-thinking", True),
             ("translation", "google", "gemini-3.8-flash", True), ("translation", "rayen", "rayen-qwen3.6-27b", False)]
    bad = 0
    for purpose, prov, model, expect in tests:
        try:
            assert_allowed(purpose, prov, model); got = True
        except PolicyViolation:
            got = False
        flag = "OK " if got == expect else "BAD"
        bad += got != expect
        print(f"{flag} {purpose:12s} {prov}/{model} -> allowed={got} (expected {expect})")
    print("POLICY_SELFTEST", "PASS" if bad == 0 else f"FAIL({bad})")

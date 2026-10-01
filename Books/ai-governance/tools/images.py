#!/usr/bin/env python3
"""Phases 23–24 — editorial images (MasterPrompt §9, §72–§74).
  plan     : writes images/manifests/planned-images.json (cover + 7 part openers + 1 concept map); prompts authored by Claude Sonnet 4.6 (never a GPT text model)
  generate : renders APPROVED, high-priority images with an allowed OpenAI image model (policy purpose=image); records SHA-256, model, prompt hash, edition
Images are editorial; never presented as NIST originals."""
from __future__ import annotations
import base64, hashlib, json, os, sys, time
from pathlib import Path
import requests, yaml
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, policy, providers  # noqa: E402

PUB = yaml.safe_load(open(ROOT / "config" / "publishing.yaml", encoding="utf-8"))
MAN = ROOT / "images" / "manifests" / "planned-images.json"
GEN = ROOT / "images" / "generated"; COV = ROOT / "images" / "covers"
IMAGE_MODEL = "gpt-image-1.5"   # allowed image-only model (config/model-policy.yaml ALLOW_IMAGE_MODELS); falls back to gpt-image-1-mini

STYLE = ("Editorial, abstract, minimal vector-style illustration for a serious Persian technical reference book on AI governance and security. "
         "Muted palette (deep navy, slate, warm sand accent), geometric forms, subtle grid, generous negative space. NO text, NO letters, NO logos, NO watermarks, NO faces, "
         "no NIST branding. Suitable for print at A4.")

def plan():
    items = [{"image_id": "IMG-COVER", "purpose": "book cover artwork", "chapter": "cover", "type": "cover", "priority": 1, "subject": PUB["book_title_fa"] + " — governance, security and risk management of AI; the seven parts of the book; trust and verification"}]
    for p in PUB["parts"]:
        items.append({"image_id": f"IMG-{p['id']}", "purpose": f"part-divider artwork for {p['id']}", "chapter": p["id"], "type": "part_divider", "priority": 2, "subject": p["title_fa"]})
    items.append({"image_id": "IMG-CONCEPT-LAYERS", "purpose": "diagram of the three knowledge layers of the book (source translation / editorial synthesis / Iranian implementation)", "chapter": "preface", "type": "concept_map", "priority": 2, "subject": "three distinct stacked layers connected by provenance links"})
    schema = {"type": "object", "properties": {"prompts": {"type": "array", "items": {"type": "object", "properties": {"image_id": {"type": "string"}, "prompt_en": {"type": "string"}, "alt_fa": {"type": "string"}}, "required": ["image_id", "prompt_en", "alt_fa"]}}}, "required": ["prompts"]}
    pf = ROOT / "work" / "image_prompts.json"
    if pf.exists():   # prompts authored by an in-session Claude subagent (see tools/images.py docstring)
        by = {p["image_id"]: p for p in json.load(open(pf, encoding="utf-8")).get("prompts", [])}; author = "claude-code-subagent/sonnet"
    else:
        req = ("Write one image-generation prompt (English, ≤ 90 words) per item for an editorial illustration, following this style guide exactly: " + STYLE +
               " Also give a short Persian alt text (alt_fa). Items:\n" + json.dumps([{k: i[k] for k in ("image_id", "type", "subject")} for i in items], ensure_ascii=False))
        r = providers.claude_agy("claude-sonnet-4-6", req, purpose="editorial", schema=schema)
        by = {p["image_id"]: p for p in (r["json"] or {}).get("prompts", [])}; author = "antigravity/claude-sonnet-4-6"
    for i in items:
        p = by.get(i["image_id"], {})
        i.update({"prompt": p.get("prompt_en", ""), "alt_fa": p.get("alt_fa", ""), "prompt_author": author, "generation_model": f"openai/{IMAGE_MODEL}",
                  "status": "APPROVED" if p.get("prompt_en") else "NEEDS_PROMPT", "origin": "editorial_image"})
    MAN.parent.mkdir(parents=True, exist_ok=True)
    MAN.write_text(json.dumps({"edition_id": PUB["edition_id"], "planned": items, "generated_at": db.now(), "note": "editorial images; not NIST originals; generate only APPROVED items"}, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"planned {len(items)} images; prompts by Claude: {len(by)}")

def openai_image(prompt: str, model: str, size="1536x1024", quality="medium") -> bytes:
    policy.assert_allowed("image", "openai", model)
    key = json.load(open(os.path.expanduser("~/.local/share/opencode/auth.json")))["openai"]["key"]
    t = time.time()
    r = requests.post("https://api.openai.com/v1/images/generations", headers={"Authorization": f"Bearer {key}", "Content-Type": "application/json"},
                      json={"model": model, "prompt": prompt, "n": 1, "size": size, "quality": quality, "output_format": "png"}, timeout=300)
    dt = time.time() - t
    if r.status_code != 200:
        db.record_usage("image", "openai", model, {}, dt, f"http_{r.status_code}")
        raise RuntimeError(f"image gen HTTP {r.status_code}: {r.text[:300]}")
    d = r.json()["data"][0]
    db.record_usage("image", "openai", model, {"input_tokens": (r.json().get("usage") or {}).get("input_tokens"), "output_tokens": (r.json().get("usage") or {}).get("output_tokens")}, dt, "ok")
    if d.get("b64_json"):
        return base64.b64decode(d["b64_json"])
    return requests.get(d["url"], timeout=120).content

def generate(max_priority=2):
    man = json.load(open(MAN, encoding="utf-8"))
    GEN.mkdir(parents=True, exist_ok=True); COV.mkdir(parents=True, exist_ok=True)
    for i in man["planned"]:
        if i["status"] != "APPROVED" or i["priority"] > max_priority:
            continue
        out = (COV if i["type"] == "cover" else GEN) / f"{i['image_id']}.png"
        if out.exists():
            continue
        model = IMAGE_MODEL
        try:
            png = openai_image(i["prompt"], model, size="1024x1536" if i["type"] == "cover" else "1536x1024")
        except Exception as e:
            print(f"{i['image_id']} failed with {model}: {str(e)[:120]} → retry gpt-image-1-mini")
            model = "gpt-image-1-mini"
            png = openai_image(i["prompt"], model, size="1024x1536" if i["type"] == "cover" else "1536x1024")
        out.write_bytes(png)
        i.update({"status": "GENERATED", "file": str(out.relative_to(ROOT)), "sha256": hashlib.sha256(png).hexdigest(), "generation_model": f"openai/{model}", "generated_at": db.now(),
                  "prompt_sha256": hashlib.sha256(i["prompt"].encode()).hexdigest(), "edition_id": PUB["edition_id"], "bytes": len(png)})
        MAN.write_text(json.dumps(man, ensure_ascii=False, indent=1), encoding="utf-8")
        db.event("image_generated", {"image_id": i["image_id"], "model": model, "sha256": i["sha256"]})
        print(f"generated {i['image_id']} ({model}, {len(png)} bytes)")
    (ROOT / "images" / "manifests" / "generated-images.json").write_text(json.dumps([i for i in man["planned"] if i["status"] == "GENERATED"], ensure_ascii=False, indent=1), encoding="utf-8")

if __name__ == "__main__":
    {"plan": plan, "generate": generate}[sys.argv[1]]()

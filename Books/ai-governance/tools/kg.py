#!/usr/bin/env python3
"""Phase 17 — Knowledge Graph (MasterPrompt §53, §69, §116). Entity/relation extraction by GLM-5.3 with block-level evidence;
document/publication nodes and cross-document links deterministic. Editorial relations are flagged by origin.
Outputs: knowledge/entities.jsonl, knowledge/relations.jsonl, knowledge/graph.json, knowledge/graph.graphml. Resumable per content unit."""
from __future__ import annotations
import json, re, sys
from pathlib import Path
import networkx as nx
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, providers  # noqa: E402

ENT_TYPES = ["Concept", "Document", "Publication", "Framework", "Risk", "Attack", "Mitigation", "Control", "Property", "EvaluationMethod", "Organization", "ModelType", "LegalConcept",
             "OrganizationalRole", "EvidenceArtifact", "KPI", "KRI", "ImplementationTemplate", "RiskRegisterItem", "Process"]
REL_TYPES = ["DEFINED_IN", "PART_OF", "RELATED_TO", "MITIGATES", "CAUSES", "AFFECTS", "MEASURED_BY", "APPLIES_TO", "REFERENCES", "EXTENDS", "IMPLEMENTS", "CONTRASTS_WITH",
             "IMPLEMENTED_BY", "OWNED_BY", "EVIDENCED_BY", "SUPPORTED_BY", "DERIVED_FROM"]
CACHE = ROOT / "work" / "kg_cache"; CACHE.mkdir(parents=True, exist_ok=True)

def slug(s: str) -> str:
    return re.sub(r"[^a-z0-9]+", "_", s.lower()).strip("_")[:60]

def unit_source_text(unit: dict, blocks_by_doc: dict) -> str:
    parts = []
    for bid in unit["source_blocks"][:80]:
        dk = bid.split("::")[0]
        b = blocks_by_doc.get(dk, {}).get(bid)
        if b and b["block_type"] in ("paragraph", "list_item", "glossary_entry", "heading", "table", "caption"):
            parts.append(f"[{bid.split('::')[-1]}] {b['text'][:900]}")
    return "\n".join(parts)[:14000]

def extract_unit(unit: dict, blocks_by_doc: dict) -> dict:
    cp = CACHE / (unit["content_id"] + ".json")
    if cp.exists():
        return json.load(open(cp, encoding="utf-8"))
    src = unit_source_text(unit, blocks_by_doc)
    editorial = unit["origin"] != "source_translation"
    system = ("You are a knowledge engineer building a source-grounded knowledge graph for NIST AI publications. Extract only what the text supports. "
              "Every relation MUST cite a block marker [Bnnnn] and a short English evidence quote (≤ 25 words) taken from that block. Output strictly valid JSON.")
    user = (f"Entity types: {ENT_TYPES}\nRelation types: {REL_TYPES}\n"
            f"Unit: {unit['structural_id']} · {unit['title_en']} · sources {unit['source_documents']} · origin={unit['origin']}\n"
            "Return JSON {\"entities\":[{\"label_en\":..., \"label_fa\":..., \"type\":..., \"definition_en\":\"(only if the text defines it)\"}],"
            " \"relations\":[{\"source\":\"label_en\", \"relation\":..., \"target\":\"label_en\", \"evidence_block\":\"Bnnnn\", \"evidence_quote\":\"...\"}]}\n"
            "Limit to the 6–20 most important entities and 5–25 relations for this unit. Persian labels must match the Persian text where the term appears.\n\n"
            + ("TEXT (Persian editorial with source markers):\n" + unit["fa_text"][:9000] if editorial or not src else "TEXT (English source blocks with markers):\n" + src)
            + ("\n\nPERSIAN TRANSLATION (for label_fa):\n" + unit["fa_text"][:6000] if src and not editorial else ""))
    try:
        r = providers.glm_chat("glm-5.3", system, user, purpose="translation", temperature=0.0, max_tokens=5000, json_mode=True)
        txt = r["text"]; s, e = txt.find("{"), txt.rfind("}")
        data = json.loads(txt[s:e + 1])
    except Exception as ex:
        data = {"entities": [], "relations": [], "error": str(ex)[:200]}
    data["content_id"] = unit["content_id"]; data["origin"] = unit["origin"]
    cp.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
    return data

def main(limit: int | None = None):
    book = json.load(open(ROOT / "master" / "book.json", encoding="utf-8"))
    src = {d["doc_key"]: d for d in json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]}
    blocks_by_doc = {}
    for dk in src:
        p = ROOT / "sources" / "extracted" / f"{dk}.blocks.jsonl"
        if p.exists():
            blocks_by_doc[dk] = {b["block_id"]: b for b in (json.loads(l) for l in open(p, encoding="utf-8"))}
    units = [S for P in book["parts"] for C in P["chapters"] for S in C["sections"] if S["fa_text"].strip()]
    if limit: units = units[:limit]
    entities, relations = {}, []
    # document / publication nodes
    for dk, d in src.items():
        eid = f"E:Publication:{slug(dk)}"
        entities[eid] = {"entity_id": eid, "type": "Publication", "labels": {"en": d.get("title", dk), "fa": ""}, "doc_key": dk, "series": d.get("series_identifier"), "doi": d.get("doi"), "origin": "source"}
    for i, u in enumerate(units, 1):
        data = extract_unit(u, blocks_by_doc)
        local = {}
        for e in data.get("entities", []):
            t = e.get("type") if e.get("type") in ENT_TYPES else "Concept"
            eid = f"E:{t}:{slug(e.get('label_en',''))}"
            if not slug(e.get("label_en", "")): continue
            local[e.get("label_en", "").lower()] = eid
            ent = entities.setdefault(eid, {"entity_id": eid, "type": t, "labels": {"en": e.get("label_en"), "fa": e.get("label_fa", "")}, "definition_en": "", "mentions": [], "origin": "source" if u["origin"] == "source_translation" else "editorial"})
            if e.get("definition_en") and not ent.get("definition_en"): ent["definition_en"] = e["definition_en"][:400]
            if not ent["labels"].get("fa") and e.get("label_fa"): ent["labels"]["fa"] = e["label_fa"]
            ent.setdefault("mentions", []).append(u["content_id"])
            for dk in u["source_documents"]:
                relations.append({"source": eid, "relation": "DEFINED_IN" if e.get("definition_en") else "REFERENCES", "target": f"E:Publication:{slug(dk)}", "content_id": u["content_id"], "origin": "source" if u["origin"] == "source_translation" else "editorial", "evidence": {"block_id": None, "quote": ""}})
        for r in data.get("relations", []):
            s_id = local.get((r.get("source") or "").lower()); t_id = local.get((r.get("target") or "").lower())
            if not s_id or not t_id or r.get("relation") not in REL_TYPES: continue
            eb = r.get("evidence_block") or ""
            bid = next((b for b in u["source_blocks"] if b.endswith("::" + eb)), None) if eb else None
            relations.append({"source": s_id, "relation": r["relation"], "target": t_id, "content_id": u["content_id"], "origin": "source" if u["origin"] == "source_translation" else "editorial",
                              "evidence": {"block_id": bid, "quote": (r.get("evidence_quote") or "")[:200]}})
        if i % 10 == 0: print(f"kg: {i}/{len(units)} units, {len(entities)} entities, {len(relations)} relations", flush=True)
    # cross-document overlap (§80): concepts mentioned in units from ≥2 documents
    for ent in entities.values():
        docs = {u["source_documents"][0] for u in units if u["content_id"] in ent.get("mentions", []) and u["source_documents"]}
        ent["documents"] = sorted(docs)
    (ROOT / "knowledge").mkdir(exist_ok=True)
    with open(ROOT / "knowledge" / "entities.jsonl", "w", encoding="utf-8") as f:
        for e in entities.values(): f.write(json.dumps(e, ensure_ascii=False) + "\n")
    with open(ROOT / "knowledge" / "relations.jsonl", "w", encoding="utf-8") as f:
        for r in relations: f.write(json.dumps(r, ensure_ascii=False) + "\n")
    G = nx.MultiDiGraph()
    for e in entities.values():
        G.add_node(e["entity_id"], type=e["type"], label_en=e["labels"].get("en") or "", label_fa=e["labels"].get("fa") or "", origin=e.get("origin", "source"))
    for r in relations:
        G.add_edge(r["source"], r["target"], relation=r["relation"], content_id=r["content_id"], origin=r["origin"], evidence_block=r["evidence"].get("block_id") or "", evidence_quote=r["evidence"].get("quote") or "")
    nx.write_graphml(G, ROOT / "knowledge" / "graph.graphml")
    (ROOT / "knowledge" / "graph.json").write_text(json.dumps({"edition": book["edition_id"], "nodes": list(entities.values()), "edges": relations, "entity_types": ENT_TYPES, "relation_types": REL_TYPES}, ensure_ascii=False, indent=1), encoding="utf-8")
    db.event("kg_built", {"entities": len(entities), "relations": len(relations)})
    print(f"KG: {len(entities)} entities, {len(relations)} relations")

if __name__ == "__main__":
    main(int(sys.argv[1]) if len(sys.argv) > 1 else None)

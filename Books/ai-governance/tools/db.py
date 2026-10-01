#!/usr/bin/env python3
"""Persistent project state: SQLite (state/project.db) + append-only JSONL event log.
All pipeline tools import this module. Never rely on model context for state."""
from __future__ import annotations
import json, os, sqlite3, time, hashlib, uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DB_PATH = ROOT / "state" / "project.db"
EVENTS = ROOT / "work" / "logs" / "events.jsonl"

SCHEMA = """
PRAGMA journal_mode=WAL;
PRAGMA synchronous=NORMAL;
CREATE TABLE IF NOT EXISTS documents(
  doc_key TEXT PRIMARY KEY, filename TEXT NOT NULL, sha256 TEXT NOT NULL, size_bytes INTEGER,
  pages INTEGER, words INTEGER, series_id TEXT, title TEXT, subtitle TEXT, authors TEXT, organization TEXT,
  pub_date TEXT, doi TEXT, pub_type TEXT, pub_status TEXT, text_native INTEGER, extraction_quality TEXT,
  part_id TEXT, status TEXT DEFAULT 'INVENTORIED', meta_json TEXT, updated_at TEXT);
CREATE TABLE IF NOT EXISTS pages(
  doc_key TEXT, page_no INTEGER, text_sha256 TEXT, char_count INTEGER, word_count INTEGER,
  PRIMARY KEY(doc_key, page_no));
CREATE TABLE IF NOT EXISTS blocks(
  block_id TEXT PRIMARY KEY, doc_key TEXT NOT NULL, page_start INTEGER, page_end INTEGER,
  section_path TEXT, section_number TEXT, block_type TEXT NOT NULL, order_no INTEGER NOT NULL,
  text TEXT NOT NULL, sha256 TEXT NOT NULL, meta_json TEXT);
CREATE INDEX IF NOT EXISTS idx_blocks_doc ON blocks(doc_key, order_no);
CREATE TABLE IF NOT EXISTS chunks(
  chunk_id TEXT PRIMARY KEY, doc_key TEXT NOT NULL, section_path TEXT, heading TEXT,
  block_ids TEXT NOT NULL, word_count INTEGER, sha256 TEXT NOT NULL, flags TEXT, priority INTEGER DEFAULT 5,
  order_no INTEGER, status TEXT DEFAULT 'PENDING', current_version INTEGER DEFAULT 0, updated_at TEXT);
CREATE INDEX IF NOT EXISTS idx_chunks_status ON chunks(status);
CREATE TABLE IF NOT EXISTS jobs(
  job_id TEXT PRIMARY KEY, job_type TEXT NOT NULL, input_ids TEXT, input_hash TEXT, model TEXT, provider TEXT,
  effort TEXT, prompt_version TEXT, status TEXT NOT NULL, retry_count INTEGER DEFAULT 0,
  started_at TEXT, completed_at TEXT, output_hash TEXT, error TEXT, usage_json TEXT);
CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status, job_type);
CREATE TABLE IF NOT EXISTS translations(
  chunk_id TEXT, version INTEGER, model TEXT, provider TEXT, fa_text TEXT NOT NULL, sha256 TEXT,
  structural_check TEXT, term_lint TEXT, created_at TEXT, PRIMARY KEY(chunk_id, version));
CREATE TABLE IF NOT EXISTS reviews(
  chunk_id TEXT, version INTEGER, reviewer_model TEXT, mode TEXT, verdict TEXT NOT NULL,
  fidelity INTEGER, completeness INTEGER, terminology INTEGER, fluency INTEGER, structure INTEGER,
  issues_json TEXT, edited_fa TEXT, notes TEXT, created_at TEXT, PRIMARY KEY(chunk_id, version, mode));
CREATE TABLE IF NOT EXISTS approved(
  chunk_id TEXT PRIMARY KEY, version INTEGER, fa_text TEXT NOT NULL, sha256 TEXT NOT NULL,
  translator_model TEXT, reviewer_model TEXT, escalated INTEGER DEFAULT 0, approved_at TEXT);
CREATE TABLE IF NOT EXISTS tm(
  source_hash TEXT PRIMARY KEY, en_norm TEXT NOT NULL, fa_text TEXT NOT NULL, translator_model TEXT,
  reviewer_model TEXT, review_effort TEXT, glossary_version TEXT, source_refs TEXT, approved_at TEXT);
CREATE TABLE IF NOT EXISTS terms(
  concept_id TEXT PRIMARY KEY, en TEXT NOT NULL, fa TEXT, alternatives_fa TEXT, rejected_fa TEXT,
  def_en TEXT, def_fa TEXT, acronym TEXT, domain TEXT, source_documents TEXT, frequency INTEGER,
  status TEXT DEFAULT 'candidate', reviewer TEXT, notes TEXT, updated_at TEXT);
CREATE TABLE IF NOT EXISTS content_units(
  content_id TEXT PRIMARY KEY, structural_id TEXT NOT NULL, locale TEXT NOT NULL, part_id TEXT, chapter_id TEXT,
  section_id TEXT, title_fa TEXT, title_en TEXT, origin TEXT NOT NULL, order_no INTEGER,
  canonical_text TEXT, content_hash TEXT, source_blocks TEXT, source_pages TEXT, meta_json TEXT);
CREATE TABLE IF NOT EXISTS events(id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT, kind TEXT, payload TEXT);
CREATE TABLE IF NOT EXISTS kv(key TEXT PRIMARY KEY, value TEXT, updated_at TEXT);
CREATE TABLE IF NOT EXISTS model_usage(
  id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT, job_id TEXT, purpose TEXT, provider TEXT, model TEXT,
  input_tokens INTEGER, output_tokens INTEGER, thinking_tokens INTEGER, latency_s REAL, status TEXT);
"""

def now() -> str:
    return time.strftime("%Y-%m-%dT%H:%M:%S%z")

def sha256_text(s: str) -> str:
    return hashlib.sha256(s.encode("utf-8")).hexdigest()

def connect() -> sqlite3.Connection:
    DB_PATH.parent.mkdir(parents=True, exist_ok=True)
    con = sqlite3.connect(DB_PATH, timeout=60, isolation_level=None)
    con.row_factory = sqlite3.Row
    con.executescript(SCHEMA)
    return con

def event(kind: str, payload: dict, con: sqlite3.Connection | None = None) -> None:
    rec = {"ts": now(), "kind": kind, "payload": payload}
    EVENTS.parent.mkdir(parents=True, exist_ok=True)
    with open(EVENTS, "a", encoding="utf-8") as f:
        f.write(json.dumps(rec, ensure_ascii=False) + "\n")
    c = con or connect()
    c.execute("INSERT INTO events(ts,kind,payload) VALUES(?,?,?)", (rec["ts"], kind, json.dumps(payload, ensure_ascii=False)))

def kv_set(key: str, value, con=None):
    c = con or connect()
    c.execute("INSERT INTO kv(key,value,updated_at) VALUES(?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at",
              (key, json.dumps(value, ensure_ascii=False), now()))

def kv_get(key: str, default=None, con=None):
    c = con or connect()
    r = c.execute("SELECT value FROM kv WHERE key=?", (key,)).fetchone()
    return json.loads(r[0]) if r else default

def new_job(job_type: str, input_ids: list, input_hash: str, model: str, provider: str, effort: str, prompt_version: str, con=None) -> str:
    c = con or connect()
    jid = f"{job_type}-{uuid.uuid4().hex[:12]}"
    c.execute("INSERT INTO jobs(job_id,job_type,input_ids,input_hash,model,provider,effort,prompt_version,status,started_at) VALUES(?,?,?,?,?,?,?,?,?,?)",
              (jid, job_type, json.dumps(input_ids), input_hash, model, provider, effort, prompt_version, "QUEUED", now()))
    return jid

def job_status(job_id: str, status: str, con=None, **fields):
    c = con or connect()
    sets = ["status=?"]; vals = [status]
    for k, v in fields.items():
        sets.append(f"{k}=?"); vals.append(v if not isinstance(v, (dict, list)) else json.dumps(v, ensure_ascii=False))
    if status in ("COMPLETED", "FAILED", "APPROVED", "REVIEWED", "TRANSLATED"):
        sets.append("completed_at=?"); vals.append(now())
    vals.append(job_id)
    c.execute(f"UPDATE jobs SET {', '.join(sets)} WHERE job_id=?", vals)

def record_usage(purpose, provider, model, usage: dict, latency_s: float, status: str, job_id: str | None = None, con=None):
    c = con or connect()
    c.execute("INSERT INTO model_usage(ts,job_id,purpose,provider,model,input_tokens,output_tokens,thinking_tokens,latency_s,status) VALUES(?,?,?,?,?,?,?,?,?,?)",
              (now(), job_id, purpose, provider, model, usage.get("input_tokens"), usage.get("output_tokens"), usage.get("thinking_tokens"), latency_s, status))
    ledger = ROOT / "work" / "logs" / "model_usage.jsonl"
    with open(ledger, "a", encoding="utf-8") as f:
        f.write(json.dumps({"ts": now(), "job_id": job_id, "purpose": purpose, "provider": provider, "model": model,
                            "usage": usage, "latency_s": latency_s, "status": status}, ensure_ascii=False) + "\n")

if __name__ == "__main__":
    con = connect()
    kv_set("schema_version", "1", con)
    kv_set("initialized_at", now(), con)
    event("state_initialized", {"db": str(DB_PATH)}, con)
    print("state initialized:", DB_PATH)

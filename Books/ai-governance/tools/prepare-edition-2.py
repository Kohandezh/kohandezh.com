#!/usr/bin/env python3
"""Prepare traceable E2 inputs without changing the E1 publication or its IDs.

No model calls, translation, canonical replacement or release promotion.
PDF text is unreviewed extraction, never silently treated as publication text.
"""
from __future__ import annotations

import argparse
import copy
import hashlib
import html
import json
import re
import subprocess
import sys
import unicodedata
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT.parents[2] / "kohandezh-archive"


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def save(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def norm(text: str) -> str:
    text = unicodedata.normalize("NFC", text).replace("ي", "ی").replace("ك", "ک")
    return re.sub(r"[\s\u200c\u200e\u200f]+", "", text).casefold()


def check_config(config: dict[str, Any], book: dict[str, Any]) -> None:
    if config["edition_id"] == config["base_edition_id"]:
        raise ValueError("New edition must have its own edition identifier")
    seen: set[str] = set()
    chapters = {(p["part_id"], c["chapter_id"]) for p in book["parts"] for c in p["chapters"]}
    for source in config["sources"]:
        for key in ("filename", "original_filename"):
            name = source[key]
            if Path(name).name != name or not name.endswith(".pdf"):
                raise ValueError("Source filenames must be local PDF basenames")
        if source["filename"] in seen:
            raise ValueError("Duplicate destination filename")
        seen.add(source["filename"])
        if (source["part_id"], source["after_chapter"]) not in chapters:
            raise ValueError("Unknown insertion anchor")
    source_ids = {s["source_id"] for s in config["sources"]}
    if len(source_ids) != len(config["sources"]):
        raise ValueError("Duplicate source identifier")
    for term in config["terminology"]:
        if term["source_id"] not in source_ids or term["pdf_page"] < 1:
            raise ValueError("Term requires a valid source and page")


def prepare(output: Path, rename: bool = False) -> dict[str, Any]:
    output = output.resolve()
    # Audit/preparation artifacts are deliberately kept outside the repository.
    if not output.is_relative_to(ARCHIVE.resolve()):
        raise ValueError(f"Output must be inside {ARCHIVE}")
    config_path = ROOT / "config/edition-2.json"
    config = json.loads(config_path.read_text(encoding="utf-8"))
    book_path = ROOT / "master/book.json"
    glossary_path = ROOT / "translation_memory/glossary.json"
    protected = [book_path, ROOT / "master/book.md", glossary_path,
                 ROOT / "config/publishing.yaml", ROOT / "config/provenance.yaml"]
    before = {str(p.relative_to(ROOT)): digest(p) for p in protected}
    book = json.loads(book_path.read_text(encoding="utf-8"))
    glossary_document = json.loads(glossary_path.read_text(encoding="utf-8"))
    glossary = glossary_document["terms"]
    check_config(config, book)
    directory = ROOT / config["source_directory"]
    # Preflight every target before the first rename.
    resolved: list[tuple[dict[str, Any], Path, Path, str]] = []
    for source in config["sources"]:
        old, new = directory / source["original_filename"], directory / source["filename"]
        if old.exists() and new.exists():
            raise FileExistsError(f"Both old and new names exist: {new.name}")
        current = old if old.exists() else new
        if not current.is_file():
            raise FileNotFoundError(current)
        resolved.append((source, current, new, digest(current)))
    output.mkdir(parents=True, exist_ok=True)
    save(output / "rename-plan.json", [{"old": str(p), "new": str(n), "sha256": h}
                                       for _, p, n, h in resolved])
    documents: list[dict[str, Any]] = []
    for source, current, new, original_hash in resolved:
        if rename and current != new:
            current.rename(new)
            current = new
        if digest(current) != original_hash:
            raise RuntimeError("Source bytes changed")
        raw = subprocess.run(["pdftotext", "-layout", str(current), "-"],
                             check=True, capture_output=True, text=True).stdout
        pages = raw.split("\f")
        if pages and not pages[-1].strip():
            pages.pop()
        source_dir = output / "extracted" / source["source_id"]
        for page_no, text in enumerate(pages, 1):
            save(source_dir / f"page-{page_no:04d}.json", {
                "source_id": source["source_id"], "pdf_page": page_no,
                "source_sha256": original_hash, "text": text,
                "text_sha256": hashlib.sha256(text.encode("utf-8")).hexdigest(),
                "reviewed_for_publication": False,
            })
        documents.append({**source, "actual_path": str(current), "sha256": original_hash,
                          "pages": len(pages), "acquisition": "owner-supplied-local-pdf",
                          "publisher": config["publisher"],
                          "reference_index_url": config["reference_index_url"],
                          "index_role": config["reference_index_role"],
                          "full_text_reviewed": False})
    terms: list[dict[str, Any]] = []
    for term in config["terminology"]:
        matches = [v for k, v in glossary.items()
                   if k.casefold() in {s.casefold() for s in term["lookup_terms"]}]
        source = next(d for d in documents if d["source_id"] == term["source_id"])
        if term["pdf_page"] > source["pages"]:
            raise ValueError("Term page outside source")
        same = bool(matches) and all(norm(v["preferred_fa"]) == norm(term["preferred_fa"]) for v in matches)
        aliases = list(dict.fromkeys(term["search_aliases_fa"] + [v["preferred_fa"] for v in matches]))
        terms.append({**term, "classification": "match" if same else "wording-difference" if matches else "new-entry",
                      "existing_concept_ids": [v["concept_id"] for v in matches],
                      "existing_fa": [v["preferred_fa"] for v in matches],
                      "existing_definitions": [v.get("definition_fa", "") for v in matches],
                      "search_aliases_fa": aliases, "applied_to_published_glossary": False,
                      "source_sha256": source["sha256"], "source_path": source["actual_path"],
                      "reference_index_url": config["reference_index_url"]})
    # Number presence is explicitly a reference occurrence, not standards coverage.
    sections = [s for p in book["parts"] for c in p["chapters"] for s in c["sections"]]
    coverage: list[dict[str, Any]] = []
    for standard in config["requested_standards"]:
        number = re.search(r"\b(\d{4,5}):", standard)
        assert number is not None
        pattern = re.compile(r"(?<!\d)" + number.group(1) + r"(?!\d)")
        references = []
        for section in sections:
            text = section.get("fa_text", "")
            hit = pattern.search(text)
            if hit:
                references.append({"structural_id": section["structural_id"],
                                   "context": text[max(0, hit.start()-100):hit.end()+160]})
        coverage.append({"standard": standard,
                         "local_sources": [d["source_id"] for d in documents if d["international_reference"] == standard],
                         "existing_reference_occurrences": references,
                         "clause_level_coverage_verified": False})
    results = {
        "edition_id": config["edition_id"], "base_edition_id": config["base_edition_id"],
        "phase": "source-and-terminology-preparation", "publication_ready": False,
        "current_publication_changed": False, "configuration_sha256": digest(config_path),
        "base_files": before, "sources": documents, "terminology": terms,
        "requested_standard_inventory": coverage,
        "release_gates": {name: False for name in config["release_gates"]},
    }
    after = {str(p.relative_to(ROOT)): digest(p) for p in protected}
    if before != after:
        raise RuntimeError("Existing publication changed during preparation")
    save(output / "edition-2-inputs.json", results)
    # Candidate glossary stays outside the live bundle. Modified/new entries must
    # not inherit the old reviewer's approval or imply all 435 terms were audited.
    draft = copy.deepcopy(glossary_document)
    draft["glossary_version"] = config["edition_id"] + "-terminology-draft"
    draft["publication_ready"] = False
    draft["officially_compared_entries"] = len(terms)
    for term in terms:
        key = term["lookup_terms"][0]
        original = copy.deepcopy(draft["terms"].get(key, {}))
        entry = draft["terms"].setdefault(key, {
            "concept_id": re.sub(r"[^A-Z0-9]+", "_", term["english"].upper()).strip("_"),
            "english": term["english"], "acronym": "", "definition_en": "",
            "definition_fa": "", "domain": "standards", "source_documents": [],
        })
        entry["previous_edition_entry"] = original or None
        entry["preferred_fa"] = term["preferred_fa"]
        entry["alternatives_fa"] = list(dict.fromkeys(original.get("alternatives_fa", []) + term["search_aliases_fa"]))
        entry["rejected_fa"] = [v for v in original.get("rejected_fa", [])
                                if norm(v) not in {norm(a) for a in entry["alternatives_fa"] + [entry["preferred_fa"]]}]
        entry["source_documents"] = list(dict.fromkeys(entry["source_documents"] + [term["source_id"]]))
        entry["standard_citations"] = [{k: term[k] for k in
                                       ("source_id", "clause", "pdf_page", "printed_page", "source_sha256", "reference_index_url")}]
        entry["status"] = "proposed"
        for inherited in ("reviewer", "review_mode", "reviewed_at", "rationale"):
            entry.pop(inherited, None)
        entry["source_specific_definitions"] = []
        if original.get("definition_fa"):
            entry["source_specific_definitions"].append({"source": "2026E1", "definition_fa": original["definition_fa"]})
        if term.get("definition_fa"):
            entry["definition_fa"] = term["definition_fa"]
            entry["source_specific_definitions"].append({"source": term["source_id"], "clause": term["clause"],
                                                         "definition_fa": term["definition_fa"]})
        entry["requires_contextual_definition"] = term.get("requires_contextual_definition", False)
    save(output / "glossary-e2-draft.json", draft)
    rows = []
    for term in terms:
        citation = f"{term['source_id']}؛ بند {term['clause']}؛ صفحهٔ چاپی {term['printed_page']} / PDF {term['pdf_page']}"
        rows.append("<tr>" + "".join("<td>" + html.escape(value) + "</td>" for value in
                     (term["english"], term["preferred_fa"], "، ".join(term["existing_fa"]) or "—", citation)) + "</tr>")
    # Local inspection view only; no changes to the Reader or site templates.
    preview = ("<!doctype html><html lang='fa' dir='rtl'><meta charset='utf-8'>"
               "<meta name='viewport' content='width=device-width,initial-scale=1'><meta name='robots' content='noindex'>"
               "<title>ویرایش دوم — تطبیق اولیهٔ اصطلاحات</title><style>body{font-family:Tahoma,sans-serif;"
               "max-width:1100px;margin:2rem auto;padding:1rem;line-height:2}table{border-collapse:collapse;width:100%}"
               "th,td{padding:.75rem;border:1px solid #bbb;text-align:start}td:first-child{direction:ltr}"
               ".scroll{overflow:auto}</style><h1>ویرایش دوم — تطبیق اولیهٔ اصطلاحات</h1>"
               "<p>این صفحه فقط ورودی آماده‌سازی است. متن کامل استانداردها و همهٔ واژگان هنوز بازبینی نشده‌اند.</p>"
               "<p>ناشر اسناد: سازمان ملی استاندارد ایران. مرجع دسترسی معرفی‌شده: "
               "<a href='https://services.iranailab.ir/standards'>بخش استاندارد آزمایشگاه هوش مصنوعی ایران</a>.</p>"
               "<div class='scroll'><table><thead><tr><th>اصطلاح</th><th>معادل مستند</th><th>معادل فعلی</th>"
               "<th>ارجاع</th></tr></thead><tbody>" + "".join(rows) + "</tbody></table></div></html>")
    (output / "terminology-preview.html").write_text(preview, encoding="utf-8")
    report = ["# آماده‌سازی ویرایش دوم کتاب حاکمیت هوش مصنوعی", "",
              "این گزارش فهرست منابع، جایگاه پیشنهادی و تطبیق اولیهٔ اصطلاحات است؛ تأیید محتوای کامل یا نسخهٔ منتشرشده نیست.", "",
              "## منابع و محل افزودن در ساختار موجود", "",
              "| منبع ملی | استاندارد مبنا | صفحات PDF | جایگاه پیشنهادی | اولویت |",
              "|---|---|---:|---|---|"]
    for source in documents:
        report.append(f"| {source['national_reference']} | {source['international_reference']} | {source['pages']} | "
                      f"{source['part_id']}، پس از {source['after_chapter']} | {source['priority']} |")
    report += ["", "هفت بخش و شناسه‌های فعلی کتاب در این مرحله تغییر نکرده‌اند. تعیین فصل و زیربخش نهایی، پس از بررسی کامل متن انجام می‌شود.",
               "اسناد core برای پوشش اصلی و supporting برای مطالب تکمیلی مرتبط پیشنهاد شده‌اند؛ این رتبه‌بندی نظر تدوینی اولیه است.", "",
               "## استانداردهای درخواستی که فایلشان موجود نیست", ""]
    report += ["- " + c["standard"] for c in coverage if not c["local_sources"]]
    report += ["", "هیچ‌یک از این اسناد غایب به‌صورت کامل وارد ویرایش دوم نشده و ادعای پوشش بندبه‌بند آن‌ها ثبت نشده است.", "",
               "## اصطلاحات بررسی‌شده", "",
               "| English | معادل مستند | معادل فعلی | نوع تطبیق | ارجاع |", "|---|---|---|---|---|"]
    for term in terms:
        report.append(f"| {term['english']} | {term['preferred_fa']} | {'، '.join(term['existing_fa']) or '—'} | "
                      f"{term['classification']} | {term['source_id']}؛ بند {term['clause']}؛ PDF {term['pdf_page']} |")
    report += ["", "تعریف استاندارد برای Risk: «اثر عدم قطعیت». تعریف NIST موجود در کتاب، به احتمال و پیامد نیز می‌پردازد؛ "
               "این تفاوت دامنه و تعریف است و با تغییر واژهٔ ریسک به مخاطره حل نمی‌شود. هر دو تعریف باید با انتساب روشن به منبع بمانند.",
               "نام‌های رایج به‌صورت alias حفظ می‌شوند. Interested party با Stakeholder ادغام خودکار نمی‌شود.",
               f"تنها {len(terms)} مدخل با منابع مشخص تطبیق اولیه شده است؛ این عدد تأیید کل واژه‌نامهٔ {len(glossary)} مدخلی نیست.", "",
               "## ارجاع‌دهی", "",
               "- ناشر نسخه‌های فارسی: سازمان ملی استاندارد ایران، مطابق شناسنامهٔ PDF.",
               f"- مرجع دسترسی معرفی‌شده توسط مالک: [{config['reference_index_url']}]({config['reference_index_url']}).",
               "- فایل‌ها از پوشهٔ محلی مالک خوانده شده‌اند؛ منشأ دانلود هر فایل از این وبگاه مستقلاً تأیید نشده است.",
               "- پیوند مستقیم PDF فقط پس از تأیید ثبت می‌شود؛ مقدار نامعلوم null است.",
               "- هر نقل یا تعریف: شناسهٔ ملی، شناسهٔ بین‌المللی، سال هر دو ویرایش، بند، صفحهٔ چاپی، صفحهٔ PDF و هش منبع.", "",
               "## SEO و حفظ ساختار", "",
               "معادل رسمی در متن و واژه‌نامه اولویت دارد. AI Governance، حاکمیت هوش مصنوعی، حکمرانی هوش مصنوعی، ریسک و مخاطره "
               "برای جست‌وجوی مرتبط حفظ می‌شوند. صفحهٔ تکراری برای هر مترادف ساخته نمی‌شود. مسیرهای canonical و قالب فعلی حفظ می‌شوند. "
               "نمایش «ویرایش دوم» و dateModified مربوط به زمان انتشار تأییدشده خواهد بود.", "",
               "## مانع‌های ورود به نسخهٔ نهایی", "",
               "- خرابی بخشی از حروف و اعداد در استخراج متن PDF؛ استخراج خام برای انتشار تأیید نشده است.",
               "- OCR فارسی روی یک صفحه آزمایش شده است؛ خطاهای عددی و نوشتاری دارد و جایگزین بازبینی نیست.",
               "- بازبینی محتوایی مطابق config/model-policy.yaml لازم است؛ خروجی این ابزار هیچ تأیید مدلی ایجاد نمی‌کند.",
               "- متن کامل بندها، جدول‌ها، یادداشت‌ها، پیوست‌ها و ارجاعات هر سند هنوز به‌صورت بندبه‌بند تطبیق داده نشده است.",
               "- نگاشت شناسه‌های E1 به E2، بازسازی Search/RAG/KG، provenance، PDF/HTML و آزمون ظاهر پس از پذیرش محتوا انجام می‌شود.", "",
               "## فایل‌های منبع پس از تغییر نام", ""]
    report += [f"- [{d['filename']}]({d['actual_path']}) — SHA-256 `{d['sha256']}`" for d in documents]
    (output / "preparation-review.fa.md").write_text("\n".join(report) + "\n", encoding="utf-8")
    return results


def review_terms(output: Path, result: dict[str, Any]) -> None:
    """One bounded call through the existing allowed reviewer, never a release approval."""
    sys.path.insert(0, str(ROOT / "tools"))
    import providers
    payload = {"task": "Review source-specific term alignment. Do not invent missing definitions or approve the book.",
               "terms": result["terminology"], "instructions": [
                   "Distinguish naming differences from semantic definition differences.",
                   "Risk under 42001 is effect of uncertainty; preserve NIST-context definitions separately.",
                   "Interested party must not be automatically merged with stakeholder.",
                   "Return JSON: findings[], unresolved[], publication_approval=false."]}
    try:
        response = providers.glm_chat("glm-5.3", "You review Persian standards terminology. Return JSON only.",
                                      json.dumps(payload, ensure_ascii=False), purpose="review_alt",
                                      max_tokens=2500, retries=0, timeout=40, json_mode=True,
                                      job_id="edition-2-terminology-initial")
        review = json.loads(response["text"])
        save(output / "terminology-model-review.json", {"provider": response["provider"], "model": response["model"],
                                                        "publication_approval": False, "review": review})
        print("Terminology review received; no publication promotion performed.")
    except Exception as error:
        http = re.search(r"HTTP (\d{3})", str(error))
        save(output / "terminology-model-review.json", {"publication_approval": False,
                                                        "review_completed": False, "error_type": type(error).__name__,
                                                        "http_status": int(http.group(1)) if http else None})
        print(f"Terminology review unavailable: {type(error).__name__}; source preparation preserved.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--rename", action="store_true", help="Rename PDFs; refuse conflicting targets")
    parser.add_argument("--review-terms", action="store_true", help="Use the existing policy-approved GLM review route once")
    args = parser.parse_args()
    result = prepare(args.output, args.rename)
    if args.review_terms:
        review_terms(args.output, result)
    print(json.dumps({"edition": result["edition_id"], "source_count": len(result["sources"]),
                      "pages": sum(s["pages"] for s in result["sources"]),
                      "term_candidates": len(result["terminology"]),
                      "publication_ready": result["publication_ready"], "output": str(args.output)}, ensure_ascii=False))

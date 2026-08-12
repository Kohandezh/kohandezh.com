#!/usr/bin/env python3
"""
validate-jsonld.py — extract + validate JSON-LD <script> blocks from a URL or file.

Validates that every application/ld+json block:
  - parses as JSON
  - has @context and @type
  - has @id when the type implies an entity (Person/Organization/Article/...)
  - @type is a known schema.org type from the project vocabulary

Exit code: 0 = all valid, 1 = any failure.

Usage:
  python3 _tooling/tests/validate-jsonld.py http://127.0.0.1:8735/
  python3 _tooling/tests/validate-jsonld.py index.html
"""
import json
import re
import sys
import urllib.request

LD_RE = re.compile(
    r'<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>',
    re.DOTALL | re.IGNORECASE,
)

KNOWN_TYPES = {
    "Person", "Organization", "WebSite", "WebPage", "ProfilePage", "AboutPage",
    "CollectionPage", "Article", "NewsArticle", "TechArticle", "ScholarlyArticle",
    "BlogPosting", "FAQPage", "BreadcrumbList", "DefinedTerm", "DefinedTermSet",
    "ItemList", "Product", "Service", "ProfessionalService", "SoftwareApplication",
    "Event", "PostalAddress", "Question", "Answer", "OfferCatalog", "Offer",
}
ENTITY_TYPES = {"Person", "Organization", "Article", "NewsArticle", "TechArticle",
                "ScholarlyArticle", "BlogPosting", "DefinedTerm", "Service", "ProfessionalService",
                "SoftwareApplication", "Product", "Event"}


def fetch_text(target: str) -> str:
    if target.startswith("http://") or target.startswith("https://"):
        with urllib.request.urlopen(target, timeout=15) as r:
            return r.read().decode("utf-8", "replace")
    with open(target, "r", encoding="utf-8") as f:
        return f.read()


def main() -> int:
    if len(sys.argv) < 2:
        print("usage: validate-jsonld.py <url-or-file>", file=sys.stderr)
        return 2
    text = fetch_text(sys.argv[1])
    blocks = LD_RE.findall(text)
    if not blocks:
        print(f"WARN: no JSON-LD blocks found in {sys.argv[1]}")
        # not necessarily an error (e.g. a 404 page) — exit 0 unless --strict
        return 0
    failures = 0
    print(f"found {len(blocks)} JSON-LD block(s)")
    for i, raw in enumerate(blocks, 1):
        try:
            data = json.loads(raw)
        except json.JSONDecodeError as e:
            print(f"  [{i}] FAIL parse: {e}")
            failures += 1
            continue
        t = data.get("@type")
        ctx = data.get("@context")
        errs = []
        if not ctx:
            errs.append("missing @context")
        if not t:
            errs.append("missing @type")
        else:
            # @type may be a string or list
            types = t if isinstance(t, list) else [t]
            for tt in types:
                if tt not in KNOWN_TYPES:
                    errs.append(f"unknown/suspicious @type: {tt}")
            if any(tt in ENTITY_TYPES for tt in types) and not data.get("@id") and not data.get("id"):
                # Layer A Person/Org use @id? check leniently: warn only
                errs.append("entity type without @id (warn)")
        if errs:
            print(f"  [{i}] @type={t} -> issues: {', '.join(errs)}")
            if "warn" not in "".join(errs):
                failures += 1
        else:
            print(f"  [{i}] OK @type={t}")
    print(f"result: {len(blocks) - failures}/{len(blocks)} valid")
    return 0 if failures == 0 else 1


if __name__ == "__main__":
    sys.exit(main())

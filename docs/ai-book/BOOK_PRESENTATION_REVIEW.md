# Local book presentation — revision 9

Owner-review candidate, 2026-09-26. No push, merge, deploy or canonical book edits.

## Results

- Local old GOVERN URL: 301 to locale-first Reader; browser preserved S02 fragment.
- Reader direct section links use current site host, not unavailable public corpus URLs.
- English source paragraphs verified LTR/start; Persian remains RTL.
- Related concepts are distinct links; related sections are separate ruled rows. Desktop two columns; 390px single column, no overflow.
- Landing: front/back covers and QR load, logo.webp loads in book header and generated footer. Version 0.4.1 invalidates stale plugin-enqueued assets.
- Original logo is 512×512; adequate for 44px header display, reused rather than regenerated.
- Local article /ai-governance-nist-guide/ renders real H1, description and BlogPosting with named author; mobile no overflow. Uses site's existing permalink structure.
- test:release and test:ai-book PASS; PHP template syntax and git diff --check PASS.
- Public https://kohandezh.com/fa/books/ai-governance/ still 404. Public repair requires a separately authorized deployment. QR intentionally points there, with local preview disclosure.

## Assets and generation

Built-in image generation through imagegen skill/tool; no downloaded generation code.
Front prompt direction: premium charcoal hardcover, mint AI governance network imagery, Persian حاکمیت هوش مصنوعی and English AI GOVERNANCE, matched site identity; final author text on cover/spine Mohammad Kohandezh. No official NIST seal.
Back prompt direction: matching charcoal/mint hardcover back; Persian راهنمای مطالعه و اقدام, سیاست‌گذاری و پاسخگویی, مدیریت ریسک هوش مصنوعی, از مفاهیم تا شواهد; Mohammad Kohandezh and kohandezh.com. No fake ISBN or generated QR.

Generated source directory: /Users/emperor/.codex/generated_images/01a0bb9f-0e97-7281-aa40-5dacdc46a651/
Final front: exec-c62f6938-3f7e-4cd2-a21d-0616aa5f7440.png
Final back: exec-8f8983d2-2992-417a-9771-026b49b94cd3.png
Delivered WebP files: _tooling/wp-theme/kohandezh-knowledge/assets/book-front.webp and book-back.webp (1024×1536, cwebp quality 88).

QR: run python3 _tooling/ai-book/build_book_qr.py; existing OpenCV encoder plus quiet zone, nearest-neighbour scaling, and decoder equality check. Output assets/book-qr.png. No external QR service.

## Editorial import

Source: _tooling/ai-book/ai-governance-nist-guide.html.
Local-only idempotent seed: _tooling/ai-book/seed-local-article.php. Requires WP CLI and exact home http://localhost:8890; preserves existing slug rather than overwriting edits.

Use existing Docker project kohandezh_ai_book_local with _tooling/wp-local/ai-book-compose.yml. Bind this worktree's _tooling/ai-book to /work:ro, run CLI eval-file /work/seed-local-article.php. Do not use the unrelated default compose project. Production article migration is not performed.

Sources for editorial facts: https://www.nist.gov/itl/ai-risk-management-framework and NIST Playbook. SEO scope follows https://developers.google.com/search/docs/appearance/ai-features ; no guaranteed rankings, synthetic endorsements or special GEO claims.

## Next

Owner reviews landing, Reader and article locally. Broader production SEO/security gates must be rechecked at release; these targeted tests are not a deployment certification.

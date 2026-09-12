"""Remove only the retired candidate block from existing ATS PDFs.

The canonical cv-data.json has also been corrected. This preserves the current
PDF layout and all other text instead of silently regenerating unrelated facts.
Requires PyMuPDF. Backups are kept outside the deployable asset directory.
"""
import argparse
import json
import math
from pathlib import Path
import shutil
import tempfile
import fitz
from PIL import Image, ImageChops

ROOT = Path(__file__).resolve().parents[2]
PATTERNS = ('second doctorate', 'zweite Promotion', 'segundo doctorado',
            'second doctorat', 'ikinci doktora', '第二个博士', '2つ目',
            'دکترای دوم', 'دکتری دوم', 'الدكتوراه الثانية')
PROGRESS = ('در حال تحصیل', 'قيد الدراسة')

def render(page):
    """Use the same renderer at 144 dpi for before/after pixel comparison."""
    pixmap = page.get_pixmap(matrix=fitz.Matrix(2, 2), alpha=False)
    return Image.frombytes('RGB', (pixmap.width, pixmap.height), pixmap.samples)


def verify_visual_preservation(before, after, rectangles, label):
    assert before.size == after.size, f'Page dimensions changed: {label}'
    difference = ImageChops.difference(before, after)
    for rectangle in rectangles:
        scaled = rectangle * 2
        # Two pixels permit edge antialiasing, not changes to neighboring lines.
        difference.paste((0, 0, 0), (
            math.floor(scaled.x0) - 2, math.floor(scaled.y0) - 2,
            math.ceil(scaled.x1) + 2, math.ceil(scaled.y1) + 2,
        ))
    assert difference.getbbox() is None, f'Unrelated rendered pixels changed: {label}'


def prepare(file, backup):
    original = fitz.open(file)
    doc = fitz.open(file)
    expected = [page.get_text() for page in original]
    rectangles = {}
    removed_count = 0
    for page in doc:
        blocks = page.get_text('blocks')
        targets = [b for b in blocks if any(s in b[4] for s in PATTERNS)]
        for block in targets:
            selected = [block] + [
                following for following in blocks
                if following[4].strip() in PROGRESS
                and 0 <= following[1] - block[3] < 10
            ]
            for item in selected:
                rectangle = fitz.Rect(item[:4])
                rectangles.setdefault(page.number, []).append(rectangle)
                page.add_redact_annot(rectangle, fill=(1, 1, 1))
                expected[page.number] = expected[page.number].replace(item[4], '', 1)
                removed_count += 1
        if targets:
            page.apply_redactions(images=0, graphics=0)
    if not removed_count:
        original.close()
        doc.close()
        return None

    temp = backup / ('updated-' + file.name)
    doc.save(temp, garbage=4, deflate=True)
    doc.close()
    # Validate the saved/reopened artifact, not just an in-memory preview.
    check = fitz.open(temp)
    assert len(check) == len(original), f'Page count changed: {file}'
    for page_number, (before_page, after_page) in enumerate(zip(original, check)):
        label = f'{file.name}, page {page_number + 1}'
        assert expected[page_number].split() == after_page.get_text().split(), (
            f'Unrelated text changed: {label}'
        )
        assert not any(s in after_page.get_text() for s in PATTERNS), label
        before_image, after_image = render(before_page), render(after_page)
        verify_visual_preservation(
            before_image, after_image, rectangles.get(page_number, []), label,
        )
        if page_number in rectangles:
            before_image.save(backup / f'{file.stem}-page-{page_number + 1}-before.png')
            after_image.save(backup / f'{file.stem}-page-{page_number + 1}-after.png')
    check.close()
    original.close()
    return {
        'file': file.name,
        'removed_blocks': removed_count,
        'changed_pages': [number + 1 for number in rectangles],
        'preserved': ['all other extracted text', 'all pixels outside target rectangles', 'page count'],
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', help='Prepare and verify, but do not replace source PDFs.')
    args = parser.parse_args()
    backup = Path(tempfile.mkdtemp(prefix='kdcv-cv-before-'))
    prepared, unmatched = [], []
    files = sorted((ROOT / 'assets/contact').glob('*-ATS-*.pdf'))
    for file in files:
        result = prepare(file, backup)
        if result:
            prepared.append(result)
            print(f'{file.name}: candidate removed; text and rendered-pixel checks passed', flush=True)
        else:
            unmatched.append(file.name)
            print(f'{file.name}: no extractable candidate match; unchanged, not a completeness claim', flush=True)

    # Nothing in the asset directory changes unless every candidate PDF passes.
    if not args.check:
        for result in prepared:
            file = ROOT / 'assets/contact' / result['file']
            shutil.copy2(file, backup / file.name)
            shutil.copy2(backup / ('updated-' + file.name), file)
    (backup / 'verification.json').write_text(json.dumps({
        'check_only': args.check,
        'pymupdf_version': fitz.VersionBind,
        'verified': prepared,
        'unmatched_requires_review': unmatched,
    }, indent=2), encoding='utf-8')
    print(f'Backup and verification renders: {backup}')

if __name__ == '__main__':
    main()

"""Generate display-sized WebP derivatives; never overwrite source images."""
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
images = ROOT / 'assets/images'
specs = {
    'avatar/avatar-professional.webp': ([480, 800], '(max-width: 767px) calc(100vw - 48px), 388px'),
    'avatar/avatar-thumb-img0031.webp': ([80, 160], '70px'),
    'logo/KohanSystemFarda.webp': ([96, 192], '82px'),
}
for i in range(1, 7):
    specs[f'section/award-{i}.webp'] = ([160, 320], '158px')
for p in (images / 'badges/microsoft').glob('*.webp'):
    if not re.search(r'-w\d+$', p.stem):
        specs[str(p.relative_to(images))] = ([160, 320], '140px')

for name, (widths, sizes) in specs.items():
    src = images / name
    for width in widths:
        dst = src.with_name(f'{src.stem}-w{width}.webp')
        subprocess.run(['cwebp', '-quiet', '-q', '85', '-m', '6', '-resize', str(width), '0', str(src), '-o', str(dst)], check=True)

def attrs(url, name):
    widths, sizes = specs[name]
    clean = url.split('?')[0]
    variants = [clean[:-5] + f'-w{width}.webp' for width in widths]
    return variants[0], ', '.join(f'{variant} {width}w' for variant, width in zip(variants, widths)), sizes

def replace_tag(match):
    tag = match.group(0)
    found = re.search(r'\b(src|href)="([^"]*assets/images/([^"?]+)(?:\?[^" ]*)?)"', tag)
    if not found or found[3] not in specs:
        return tag
    if tag.startswith('<link') and not ('as="image"' in tag and 'avatar-professional' in tag):
        return tag
    if 'srcset=' in tag:
        return tag
    url, candidates, sizes = attrs(found[2], found[3])
    # Portrait retains the full-size source as a high-density candidate.
    if found[3] == 'avatar/avatar-professional.webp':
        candidates += ', ' + found[2].split('?')[0] + ' 1086w'
    prefix = 'image' if tag.startswith('<link') else ''
    return tag.replace(found[0], f'{found[1]}="{url}" {prefix}srcset="{candidates}" {prefix}sizes="{sizes}"')

pages = list(ROOT.glob('*.html')) + list((ROOT/'blog').glob('*.html')) + [ROOT/'portfolio/index.html']
for page in pages:
    old = page.read_text()
    new = re.sub(r'<(?:img|link)\b[^>]*>', replace_tag, old)
    if new != old:
        page.write_text(new)
print(f'Generated derivatives for {len(specs)} originals; originals preserved.')

#!/usr/bin/env python3
"""Turn a generated pose sheet into a Kohan act strip and register it.

The avatar's art is an 8x11 atlas of 192x208 cells (spritesheet.webp) plus
horizontal strips of the same cell size. New poses (for example a sheet made
in ChatGPT from the reference images) arrive as one big picture: several
poses on a plain, checkerboard or transparent background, at an arbitrary
size. This script makes them indistinguishable in placement from the atlas:

  1. background -> transparent (true alpha, a flat colour, or the
     grey-white checkerboard image generators draw for "transparent");
  2. one figure per pose (a grid you give, or blobs found automatically;
     a detached hand or prop joins the nearest figure);
  3. ONE scale for the whole strip, so a standing frame is 198 px tall like
     every atlas frame (or --ref-height for sheets with no standing pose);
  4. feet on the atlas baseline (y=202 in the 208 px cell), centred;
  5. a WebP strip + an entry in assets/kohan/acts.json (name, group,
     timing), and a contact-sheet preview PNG to check by eye.

The identity rule still holds: this only crops, scales and places. It never
redraws a face. Reject a sheet whose character drifts from the atlas.

Usage:
  build-act-strip.py SHEET.png --name wait-watch --group wait \
      [--frames 4] [--grid 1x4] [--order 0,1,2,3,2,1] [--frame-ms 220] \
      [--loops 2] [--motion sway] [--ref-height 198] [--preview out.png]
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from collections import deque
from pathlib import Path

import numpy as np
from PIL import Image, ImageFilter

CELL_W, CELL_H = 192, 208
STAND_H = 198      # standing character height in an atlas cell
BASELINE = 202     # y of the lowest opaque pixel (the soles) in a cell
GROUPS = ("click", "idle", "wait", "drag", "drop", "talk", "think")
HERE = Path(__file__).resolve().parent
KOHAN = HERE.parent / "assets" / "kohan"


def to_alpha(img: Image.Image, tol: int) -> Image.Image:
    """Return RGBA with the background made transparent."""
    im = img.convert("RGBA")
    a = np.asarray(im).astype(np.int16)
    alpha = a[..., 3]
    border = np.concatenate([a[0], a[-1], a[:, 0], a[:, -1]])
    if (border[:, 3] < 200).mean() > 0.5:
        return im  # already transparent
    rgb = a[..., :3]
    # Background palette: the 1-2 most common border colours (two for a
    # checkerboard). Every border-connected pixel near one of them goes.
    cols, counts = np.unique(border[:, :3] // 8 * 8, axis=0, return_counts=True)
    order = np.argsort(-counts)
    palette = [cols[i] + 4 for i in order[:2] if counts[i] > len(border) * 0.08]
    near = np.zeros(alpha.shape, bool)
    for c in palette:
        near |= np.abs(rgb - c).max(axis=2) <= tol
    h, w = near.shape
    bg = np.zeros_like(near)
    q = deque()
    for x in range(w):
        for y in (0, h - 1):
            if near[y, x] and not bg[y, x]:
                bg[y, x] = True
                q.append((y, x))
    for y in range(h):
        for x in (0, w - 1):
            if near[y, x] and not bg[y, x]:
                bg[y, x] = True
                q.append((y, x))
    while q:
        y, x = q.popleft()
        for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            ny, nx = y + dy, x + dx
            if 0 <= ny < h and 0 <= nx < w and near[ny, nx] and not bg[ny, nx]:
                bg[ny, nx] = True
                q.append((ny, nx))
    out = np.asarray(im).copy()
    out[..., 3] = np.where(bg, 0, 255).astype(np.uint8)
    res = Image.fromarray(out, "RGBA")
    # Soften the cut edge by one pixel so the figure does not look stencilled.
    a2 = res.getchannel("A").filter(ImageFilter.MinFilter(3)).filter(ImageFilter.GaussianBlur(0.7))
    res.putalpha(a2)
    return res


def blobs(mask: np.ndarray, min_frac: float = 0.01) -> list[tuple[int, int, int, int]]:
    """Bounding boxes of connected figures (on a 1/4 downscale for speed)."""
    small = mask[::4, ::4]
    h, w = small.shape
    lab = np.zeros((h, w), np.int32)
    boxes = []
    n = 0
    for y in range(h):
        for x in range(w):
            if small[y, x] and not lab[y, x]:
                n += 1
                q = deque([(y, x)])
                lab[y, x] = n
                y0 = y1 = y
                x0 = x1 = x
                area = 0
                while q:
                    cy, cx = q.popleft()
                    area += 1
                    y0, y1, x0, x1 = min(y0, cy), max(y1, cy), min(x0, cx), max(x1, cx)
                    for dy in (-1, 0, 1):
                        for dx in (-1, 0, 1):
                            ny, nx = cy + dy, cx + dx
                            if 0 <= ny < h and 0 <= nx < w and small[ny, nx] and not lab[ny, nx]:
                                lab[ny, nx] = n
                                q.append((ny, nx))
                boxes.append([area, x0 * 4, y0 * 4, (x1 + 1) * 4, (y1 + 1) * 4])
    if not boxes:
        return []
    biggest = max(b[0] for b in boxes)
    figs = [b for b in boxes if b[0] >= biggest * 0.25]
    bits = [b for b in boxes if b[0] < biggest * 0.25 and b[0] >= biggest * min_frac]
    for b in bits:  # a detached hand / prop joins the nearest figure
        cx, cy = (b[1] + b[3]) / 2, (b[2] + b[4]) / 2
        f = min(figs, key=lambda f: abs((f[1] + f[3]) / 2 - cx) + abs((f[2] + f[4]) / 2 - cy))
        f[1], f[2], f[3], f[4] = min(f[1], b[1]), min(f[2], b[2]), max(f[3], b[3]), max(f[4], b[4])
    # Reading order: rows (by vertical centre, banded), then left to right.
    figs.sort(key=lambda f: (round(((f[2] + f[4]) / 2) / (mask.shape[0] / 6)), f[1]))
    return [tuple(f[1:]) for f in figs]


def grid_boxes(w: int, h: int, rows: int, cols: int) -> list[tuple[int, int, int, int]]:
    return [(c * w // cols, r * h // rows, (c + 1) * w // cols, (r + 1) * h // rows)
            for r in range(rows) for c in range(cols)]


def tight(im: Image.Image) -> Image.Image:
    bbox = im.getchannel("A").point(lambda v: 255 if v > 24 else 0).getbbox()
    return im.crop(bbox) if bbox else im


def build(args: argparse.Namespace) -> int:
    src = Image.open(args.sheet)
    rgba = to_alpha(src, args.tol)
    alpha = np.asarray(rgba.getchannel("A")) > 24
    if args.grid:
        rows, cols = (int(v) for v in args.grid.lower().split("x"))
        boxes = grid_boxes(rgba.width, rgba.height, rows, cols)
    else:
        boxes = blobs(alpha)
    figures = [tight(rgba.crop(b)) for b in boxes]
    figures = [f for f in figures if f.width > 8 and f.height > 8]
    if args.frames and len(figures) != args.frames:
        print(f"error: found {len(figures)} figures, expected {args.frames} "
              f"(pass --grid RxC if poses touch each other)", file=sys.stderr)
        return 2
    if not figures:
        print("error: no figures found", file=sys.stderr)
        return 2
    tallest = max(f.height for f in figures)
    ref = args.ref_height or STAND_H
    scale = ref / tallest
    strip = Image.new("RGBA", (CELL_W * len(figures), CELL_H), (0, 0, 0, 0))
    for i, f in enumerate(figures):
        w, h = round(f.width * scale), round(f.height * scale)
        if w > CELL_W - 4 or h > BASELINE:  # an outstretched pose: fit the cell
            k = min((CELL_W - 4) / w, BASELINE / h)
            w, h = round(w * k), round(h * k)
            print(f"note: frame {i} is wider/taller than a cell; fitted at {k:.2f}x")
        fr = f.resize((max(1, w), max(1, h)), Image.LANCZOS)
        x = i * CELL_W + (CELL_W - w) // 2
        y = BASELINE - h + 1
        strip.alpha_composite(fr, (x, max(0, y)))
    out_dir = args.out_dir or KOHAN / "acts"
    out_dir.mkdir(parents=True, exist_ok=True)
    out = out_dir / f"{args.name}.webp"
    strip.save(out, "WEBP", quality=args.quality, method=6, exact=True)
    print(f"wrote {out}  {len(figures)} frames  "
          f"{out.stat().st_size // 1024} KB  scale {scale:.3f}")

    if args.preview:
        prev = Image.new("RGBA", (strip.width, CELL_H * 2), (255, 255, 255, 255))
        dark = Image.new("RGBA", (strip.width, CELL_H), (14, 20, 22, 255))
        prev.alpha_composite(strip, (0, 0))
        dark.alpha_composite(strip)
        prev.alpha_composite(dark, (0, CELL_H))
        prev.convert("RGB").save(args.preview)
        print(f"preview {args.preview}")

    manifest = args.manifest or KOHAN / "acts.json"
    data = json.loads(manifest.read_text()) if manifest.exists() else {"version": 1, "acts": []}
    entry = {"name": args.name, "file": out.name, "frames": len(figures), "group": args.group}
    if args.order:
        entry["order"] = [int(v) for v in args.order.split(",")]
    if args.frame_ms:
        ms = [int(v) for v in args.frame_ms.split(",")]
        entry["frameMs"] = ms[0] if len(ms) == 1 else ms
    for k in ("loops", "motion", "blend", "hold"):
        v = getattr(args, k)
        if v:
            entry[k] = v
    if args.source:
        entry["source"] = args.source
    data["acts"] = [a for a in data.get("acts", []) if a.get("name") != args.name] + [entry]
    manifest.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n")
    print(f"registered '{args.name}' in group '{args.group}' -> {manifest}")
    return 0


def main() -> int:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("sheet", type=Path)
    p.add_argument("--name", required=True)
    p.add_argument("--group", required=True, choices=GROUPS)
    p.add_argument("--frames", type=int)
    p.add_argument("--grid", help="RxC when poses touch, e.g. 2x4")
    p.add_argument("--order", help="frame order, e.g. 0,1,2,1")
    p.add_argument("--frame-ms", help="one value or one per order step")
    p.add_argument("--loops", type=int)
    p.add_argument("--motion", choices=["talk", "hop", "land", "sway", "shake", "dizzy", "bounce"])
    p.add_argument("--blend", type=int)
    p.add_argument("--hold", type=int)
    p.add_argument("--ref-height", type=int, help="px the TALLEST pose should be (default 198 = standing)")
    p.add_argument("--tol", type=int, default=26, help="background colour tolerance")
    p.add_argument("--quality", type=int, default=90)
    p.add_argument("--source", help="where the sheet came from (kept in acts.json)")
    p.add_argument("--preview", type=Path)
    p.add_argument("--out-dir", type=Path, help="default: assets/kohan/acts")
    p.add_argument("--manifest", type=Path, help="default: assets/kohan/acts.json")
    args = p.parse_args()
    if not re.fullmatch(r"[a-z][a-z0-9-]{1,40}", args.name):
        p.error("--name: lowercase letters, digits and dashes")
    return build(args)


if __name__ == "__main__":
    sys.exit(main())

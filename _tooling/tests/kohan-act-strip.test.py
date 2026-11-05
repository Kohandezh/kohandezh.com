#!/usr/bin/env python3
"""build-act-strip.py places generated poses exactly where the atlas puts them.

A pose sheet is faked from four atlas frames (scaled 3x, on the grey/white
checkerboard image generators draw), run through the builder into a temp
dir, and every output cell is compared with its atlas original: same
height (198), same sole line (y=202), same horizontal span (+-2 px), and a
clean, fully transparent background. acts.json must register the act.
"""
import json
import subprocess
import sys
import tempfile
from pathlib import Path

import numpy as np
from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
PLUGIN = ROOT / "_tooling/wp-theme/kohan-avatar"
SCRIPT = PLUGIN / "scripts/build-act-strip.py"
ATLAS = PLUGIN / "assets/kohan/spritesheet.webp"
checks = 0


def check(ok, label):
    global checks
    checks += 1
    if not ok:
        print("FAIL " + label, file=sys.stderr)
        sys.exit(1)


at = Image.open(ATLAS).convert("RGBA")
with tempfile.TemporaryDirectory() as tmp:
    tmp = Path(tmp)
    W, H = 1536, 1024
    yy, xx = np.mgrid[0:H, 0:W]
    bg = np.zeros((H, W, 4), np.uint8)
    bg[..., :3] = np.where(((yy // 24 + xx // 24) % 2 == 0)[..., None], 255, 205)
    bg[..., 3] = 255
    sheet = Image.fromarray(bg, "RGBA")
    for i in range(4):
        f = at.crop((i * 192, 3 * 208, (i + 1) * 192, 4 * 208)).resize((576, 624), Image.LANCZOS)
        f = f.crop(f.getchannel("A").getbbox())
        sheet.alpha_composite(f, (40 + i * 370, 980 - f.height))
    src = tmp / "sheet.png"
    sheet.convert("RGB").save(src)
    manifest = tmp / "acts.json"
    r = subprocess.run([sys.executable, str(SCRIPT), str(src), "--name", "test-wave", "--group", "talk",
                        "--frames", "4", "--frame-ms", "180", "--out-dir", str(tmp), "--manifest", str(manifest)],
                       capture_output=True, text=True)
    check(r.returncode == 0, "builder runs: " + r.stderr)
    strip = np.asarray(Image.open(tmp / "test-wave.webp").convert("RGBA"))
    check(strip.shape[:2] == (208, 768), "strip is 4 cells of 192x208")
    atl = np.asarray(at)
    for i in range(4):
        built = strip[:, i * 192:(i + 1) * 192, 3]
        orig = atl[3 * 208:4 * 208, i * 192:(i + 1) * 192, 3]
        by, bx = np.where(built > 24)
        oy, ox = np.where(orig > 24)
        check(abs((by.max() - by.min()) - (oy.max() - oy.min())) <= 2, f"frame {i}: same height as the atlas")
        check(abs(by.max() - oy.max()) <= 1, f"frame {i}: soles on the atlas baseline")
        check(abs(bx.min() - ox.min()) <= 2 and abs(bx.max() - ox.max()) <= 2, f"frame {i}: same horizontal span")
        corners = [built[0, 0], built[0, -1], built[-1, 0], built[-1, -1], built[100, 2]]
        check(max(corners) == 0, f"frame {i}: background fully transparent")
    data = json.loads(manifest.read_text())
    act = data["acts"][0]
    check(act == {"name": "test-wave", "file": "test-wave.webp", "frames": 4, "group": "talk", "frameMs": 180}, "acts.json entry")
    r = subprocess.run([sys.executable, str(SCRIPT), str(src), "--name", "test-x", "--group", "talk", "--frames", "5",
                        "--out-dir", str(tmp), "--manifest", str(manifest)], capture_output=True, text=True)
    check(r.returncode == 2 and "found 4 figures" in r.stderr, "a frame-count mismatch is refused")

shipped = json.loads((PLUGIN / "assets/kohan/acts.json").read_text())
check(isinstance(shipped.get("acts"), list), "shipped acts.json parses")
for a in shipped["acts"]:
    check((PLUGIN / "assets/kohan/acts" / a["file"]).is_file(), f"shipped act {a['name']} has its strip")
print(f"PASS kohan-act-strip: generated poses land exactly on the atlas grid ({checks} checks)")

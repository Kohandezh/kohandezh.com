#!/usr/bin/env python3
"""Print-ready Persian book DOCX for the AI Governance edition.

Successor of build_docx.py: applies master/reader-overlay.json (omits, titles,
cleaned text, recovered footnotes), renders real Word footnotes, a TOC field plus
a static TOC, part openers, running headers (STYLEREF), mirrored A4 margins and a
full-page front and back cover. stdlib + python-docx (+ Pillow for the cover).

Output: docx/AIGovernance.docx and a byte-identical docx/AIGovernance.doc
Deterministic: identical inputs give identical bytes (fixed zip timestamps).
"""
from __future__ import annotations

import datetime as dt
import io
import json
import re
import shutil
import sys
import zipfile
from pathlib import Path
from xml.sax.saxutils import escape

from docx import Document
from docx.opc.constants import RELATIONSHIP_TYPE as RT
from docx.opc.packuri import PackURI
from docx.opc.part import Part
from docx.oxml import parse_xml
from docx.oxml.ns import qn
from docx.shared import Mm, Pt, RGBColor

ROOT = Path(__file__).resolve().parent.parent          # Books/ai-governance
REPO = ROOT.parent.parent
COVER = REPO / "_tooling/wp-theme/kohandezh-knowledge/assets/books/ai-governance-cover-w640.webp"
OUT = ROOT / "docx" / "AIGovernance.docx"

# fonts as in build_docx.py — do not introduce new ones
FA_FONT, LATIN_FONT, MONO_FONT = "Vazirmatn", "Inter", "Noto Sans Mono"
W = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
R = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
NS = f'xmlns:w="{W}" xmlns:r="{R}"'
BOX = {"editorial_synthesis": ("FDFBF4", "E6CF8A"), "editorial_localization_ir": ("F5FBF7", "9CCFB0")}
NIST_DISCLAIMER = ("این اثر ترجمهٔ رسمی NIST نمی‌باشد و توسط محمدعلی کهن‌دژ جهت آموزش و آگاهی‌رسانی "
                   "حاکمیت هوش مصنوعی طراحی و توسعه شده است.")
ORDINALS = ["یکم", "دوم", "سوم", "چهارم", "پنجم", "ششم", "هفتم", "هشتم", "نهم", "دهم"]
FA_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
TO_LATIN = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
LATIN_RUN = re.compile(r"[A-Za-z][A-Za-z0-9\-._/:&+'’()%#@=]*(?:[ \t,]+[A-Za-z0-9][A-Za-z0-9\-._/:&+'’()%#@=]*)*")
PERSIAN = re.compile(r"[؀-ۿ]")


def fa_num(n) -> str:
    return str(n).translate(FA_DIGITS)


def fa_digits_outside_latin(text: str) -> str:
    """Persian digits for free-standing numbers; identifiers (SP 800-53, KDJ-…) keep Latin."""
    out, pos = [], 0
    for m in LATIN_RUN.finditer(text):
        out.append(text[pos:m.start()].translate(FA_DIGITS)); out.append(m.group(0)); pos = m.end()
    out.append(text[pos:].translate(FA_DIGITS))
    return "".join(out)


# --------------------------------------------------------------------------- XML builders
def rpr(*, bold=False, italic=False, mono=False, latin=False, size=None, color=None, sup=False, underline=False, style=None):
    x = []
    if style: x.append(f'<w:rStyle w:val="{style}"/>')
    f_lat = MONO_FONT if mono else LATIN_FONT
    f_cs = MONO_FONT if mono else FA_FONT
    x.append(f'<w:rFonts w:ascii="{f_lat}" w:hAnsi="{f_lat}" w:eastAsia="{f_lat}" w:cs="{f_cs}"/>')
    if bold: x.append("<w:b/><w:bCs/>")
    if italic: x.append("<w:i/><w:iCs/>")
    if color: x.append(f'<w:color w:val="{color}"/>')
    if size: x.append(f'<w:sz w:val="{int(size * 2)}"/><w:szCs w:val="{int(size * 2)}"/>')
    if underline: x.append('<w:u w:val="single"/>')
    if sup: x.append('<w:vertAlign w:val="superscript"/>')
    if not (latin or mono): x.append("<w:rtl/>")
    x.append('<w:lang w:val="en-US" w:bidi="fa-IR"/>')
    return "<w:rPr>" + "".join(x) + "</w:rPr>"


def t(text: str) -> str:
    return f'<w:t xml:space="preserve">{escape(text)}</w:t>'


def runs(text: str, **kw) -> str:
    """Plain text → runs, isolating Latin segments as LTR runs in the Latin font."""
    if not text: return ""
    if kw.get("mono"):
        return f"<w:r>{rpr(**kw)}{t(text)}</w:r>"
    out, pos = [], 0
    for m in LATIN_RUN.finditer(text):
        if m.start() > pos: out.append(f"<w:r>{rpr(**kw)}{t(text[pos:m.start()])}</w:r>")
        out.append(f"<w:r>{rpr(latin=True, **{k: v for k, v in kw.items() if k != 'latin'})}{t(m.group(0))}</w:r>")
        pos = m.end()
    if pos < len(text): out.append(f"<w:r>{rpr(**kw)}{t(text[pos:])}</w:r>")
    return "".join(out)


def ppr(*, style=None, jc="both", rtl=True, before=None, after=None, line=None, keep_next=False, page_break=False,
        ind_start=None, hanging=None, fill=None, border=None, border_side=None, sect=""):
    x = []
    if style: x.append(f'<w:pStyle w:val="{style}"/>')
    if keep_next: x.append("<w:keepNext/>")
    if page_break: x.append("<w:pageBreakBefore/>")
    if border:
        sides = border_side or ("top", "left", "bottom", "right")
        x.append("<w:pBdr>" + "".join(f'<w:{s} w:val="single" w:sz="{12 if s == "right" else 4}" w:space="4" w:color="{border}"/>' for s in ("top", "left", "bottom", "right") if s in sides) + "</w:pBdr>")
    if fill: x.append(f'<w:shd w:val="clear" w:color="auto" w:fill="{fill}"/>')
    x.append(f'<w:bidi w:val="{1 if rtl else 0}"/>')
    if before is not None or after is not None or line is not None:
        a = ""
        if before is not None: a += f' w:before="{int(before * 20)}"'
        if after is not None: a += f' w:after="{int(after * 20)}"'
        if line is not None: a += f' w:line="{int(line * 240)}" w:lineRule="auto"'
        x.append(f"<w:spacing{a}/>")
    if ind_start is not None:
        h = f' w:hanging="{int(hanging * 567)}"' if hanging else ""
        x.append(f'<w:ind w:start="{int(ind_start * 567)}"{h}/>')   # cm
    if jc: x.append(f'<w:jc w:val="{jc}"/>')
    return "<w:pPr>" + "".join(x) + sect + "</w:pPr>"


def P(content: str = "", **kw) -> str:
    return f"<w:p>{ppr(**kw)}{content}</w:p>"


def field(instr: str, cached: str = "", **kw) -> str:
    return (f'<w:r>{rpr(**kw)}<w:fldChar w:fldCharType="begin"/></w:r>'
            f'<w:r>{rpr(**kw)}<w:instrText xml:space="preserve"> {escape(instr)} </w:instrText></w:r>'
            f'<w:r>{rpr(**kw)}<w:fldChar w:fldCharType="separate"/></w:r>'
            + (runs(cached, **kw) if cached else "")
            + f'<w:r>{rpr(**kw)}<w:fldChar w:fldCharType="end"/></w:r>')


# --------------------------------------------------------------------------- inline markdown
INLINE = re.compile(
    r"(?P<code>`[^`\n]+`)"
    r"|(?P<link>\[(?P<ltext>[^\]\n]+)\]\((?P<url>https?://[^)\s]+)\))"
    r"|(?P<fn>\[\^(?P<fid>[^\]\s]+)\])"
    r"|(?P<fig>\[FIGURE[^\]]*\])"
    r"|(?P<bold>\*\*(?P<btext>.+?)\*\*)"
    r"|(?P<ital>(?<![*\w])\*(?=\S)(?P<itext>[^*\n]+?)(?<=\S)\*(?!\*))"
)


class Ctx:
    """Per-part state: relationships for links, footnote store of the document."""

    def __init__(self, book):
        self.book = book
        self.notes: list[str] = []          # footnote XML bodies (id = index + 1)
        self.part = None                    # document part (for hyperlinks)
        self.fn_part_links: list[tuple[str, str]] = []
        self.defs: dict[str, str] = {}
        self.chars_out = 0

    def link_rid(self, url, in_note):
        if in_note:
            rid = f"rIdL{len(self.fn_part_links) + 1}"
            self.fn_part_links.append((rid, url))
            return rid
        return self.part.relate_to(url, RT.HYPERLINK, is_external=True)


def inline(ctx: Ctx, text: str, in_note=False, **kw) -> str:
    out, pos = [], 0
    for m in INLINE.finditer(text):
        if m.start() > pos: out.append(runs(text[pos:m.start()], **kw))
        pos = m.end()
        if m.group("code"):
            out.append(runs(m.group("code")[1:-1], **{**kw, "mono": True, "size": (kw.get("size") or 11) - 1.5}))
        elif m.group("link"):
            rid = ctx.link_rid(m.group("url"), in_note)
            out.append(f'<w:hyperlink r:id="{rid}" w:history="1">'
                       + runs(m.group("ltext"), **{**kw, "color": "1F5FAD", "underline": True}) + "</w:hyperlink>")
        elif m.group("fn"):
            if in_note: continue
            for lab in m.group("fid").translate(TO_LATIN).split(","):
                body = ctx.defs.get(lab.strip())
                if body is None: continue                      # reader drops undefined refs too
                nid = len(ctx.notes) + 1
                ctx.notes.append(f'<w:footnote w:id="{nid}">'
                                 + P(f'<w:r>{rpr(sup=True, latin=True, size=9)}<w:footnoteRef/></w:r>'
                                     + runs(" ") + inline(ctx, body, in_note=True, size=9),
                                     style="KDJFootnoteText", after=0, line=1.0)
                                 + "</w:footnote>")
                out.append(f'<w:r>{rpr(sup=True, latin=True)}<w:footnoteReference w:id="{nid}"/></w:r>')
        elif m.group("fig"):
            continue                                           # inline figure marker: dropped
        elif m.group("bold"):
            out.append(inline(ctx, m.group("btext"), in_note, **{**kw, "bold": True}))
        elif m.group("ital"):
            out.append(inline(ctx, m.group("itext"), in_note, **{**kw, "italic": True}))
    if pos < len(text): out.append(runs(text[pos:], **kw))
    return "".join(out)


def plain(text: str) -> str:
    """Markup-free text (for headings / TOC)."""
    text = re.sub(r"\[\^[^\]]*\]", "", text)
    text = re.sub(r"\[([^\]]+)\]\(https?://[^)]+\)", r"\1", text)
    return re.sub(r"\*\*|`", "", text).replace("\\|", "|").strip()


# --------------------------------------------------------------------------- tables
SPLIT = re.compile(r"(?<!\\)\|")
SEP = re.compile(r"^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?\s*$")


def cells(line: str) -> list[str]:
    s = line.strip()
    if s.startswith("|"): s = s[1:]
    if s.endswith("|") and not s.endswith("\\|"): s = s[:-1]
    return [c.strip().replace("\\|", "|") for c in SPLIT.split(s)]


def table(ctx: Ctx, rows: list[list[str]], header: bool, text_w_twips=9300, size=9.5, fill_head="E4E8EE") -> str:
    n = max(len(r) for r in rows)
    rows = [r + [""] * (n - len(r)) for r in rows]
    cw = text_w_twips // n
    x = [f'<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:bidiVisual/><w:tblW w:w="5000" w:type="pct"/>'
         '<w:tblBorders>' + "".join(f'<w:{s} w:val="single" w:sz="4" w:space="0" w:color="B8C0CC"/>' for s in ("top", "left", "bottom", "right", "insideH", "insideV")) + "</w:tblBorders>"
         '<w:tblLayout w:type="autofit"/><w:tblCellMar><w:top w:w="40" w:type="dxa"/><w:left w:w="80" w:type="dxa"/>'
         '<w:bottom w:w="40" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar>'
         '<w:tblLook w:val="04A0" w:firstRow="1" w:lastRow="0" w:firstColumn="0" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/></w:tblPr>'
         "<w:tblGrid>" + "".join(f'<w:gridCol w:w="{cw}"/>' for _ in range(n)) + "</w:tblGrid>"]
    for i, row in enumerate(rows):
        head = header and i == 0
        zebra = (not head) and (i % 2 == (0 if header else 1))
        x.append("<w:tr>" + ("<w:trPr><w:tblHeader/></w:trPr>" if head else ""))
        for c in row:
            fill = fill_head if head else ("F6F8FA" if zebra else None)
            tc = f'<w:tcPr><w:tcW w:w="{cw}" w:type="dxa"/>' + (f'<w:shd w:val="clear" w:color="auto" w:fill="{fill}"/>' if fill else "") + "</w:tcPr>"
            x.append(f"<w:tc>{tc}" + P(inline(ctx, c, size=size, bold=head) if c else "", jc="start", after=0, line=1.15) + "</w:tc>")
        x.append("</w:tr>")
    x.append("</w:tbl>")
    return "".join(x) + P(after=2, line=1.0)


# --------------------------------------------------------------------------- block markdown
HEAD = re.compile(r"^(#{1,6})\s+(.*)$")
UL = re.compile(r"^\s*[-*]\s+(.*)$")
OL = re.compile(r"^\s*(?:-\s+)?([0-9۰-۹]+)[.)]\s+(.*)$")
FIGLINE = re.compile(r"^\[FIGURE[^\]]*\]\s*(.*)$")
FNDEF = re.compile(r"^\[\^([^\]]+)\]:\s?(.*)$")


def block_start(line: str) -> bool:
    s = line.strip()
    return bool(HEAD.match(s) or s.startswith("|") or UL.match(line) or OL.match(line) or s.startswith(">") or FIGLINE.match(s) or FNDEF.match(s))


def section_body(ctx: Ctx, md: str, box=None) -> str:
    fill, border = box if box else (None, None)
    bx = dict(fill=fill, border=border) if box else {}
    lines = md.split("\n")
    ctx.defs = {}
    kept = []
    for ln in lines:
        m = FNDEF.match(ln.strip())
        if m: ctx.defs[m.group(1).translate(TO_LATIN).strip()] = m.group(2).strip()
        else: kept.append(ln)
    levels = sorted({len(m.group(1)) for ln in kept if (m := HEAD.match(ln.strip()))})
    rank = {lv: min(6, 3 + i) for i, lv in enumerate(levels)}
    out, i = [], 0
    while i < len(kept):
        line = kept[i].rstrip(); i += 1
        s = line.strip()
        if not s: continue
        if (m := HEAD.match(s)):
            out.append(P(runs(fa_digits_outside_latin(plain(m.group(2)))), style=f"Heading{rank[len(m.group(1))]}", jc="start"))
            continue
        if s.startswith("|"):
            tl = [s]
            while i < len(kept) and kept[i].strip().startswith("|"):
                tl.append(kept[i].strip()); i += 1
            header = len(tl) > 1 and bool(SEP.match(tl[1]))
            rows = [cells(l) for k, l in enumerate(tl) if not (k == 1 and header) and not SEP.match(l)]
            if rows: out.append(table(ctx, rows, header))
            continue
        if (m := FIGLINE.match(s)):
            cap = m.group(1).strip()
            if cap: out.append(P(runs("شکل: ", italic=True, size=9.5, color="555E6A") + inline(ctx, cap, italic=True, size=9.5, color="555E6A"), jc="center", after=8))
            continue
        if s.startswith(">"):
            out.append(P(inline(ctx, s.lstrip(">").strip(), italic=True), ind_start=0.8, border="9AA5B1", border_side=("right",), fill="F7F8FA"))
            continue
        if (m := OL.match(line)):
            out.append(P(runs(fa_num(m.group(1).translate(TO_LATIN)) + ". ") + inline(ctx, m.group(2)), ind_start=0.9, hanging=0.6, after=3, **bx))
            continue
        if (m := UL.match(line)):
            out.append(P(runs("• ") + inline(ctx, m.group(1)), ind_start=0.9, hanging=0.5, after=3, **bx))
            continue
        buf = [s]
        while i < len(kept) and kept[i].strip() and not block_start(kept[i]):
            buf.append(kept[i].strip()); i += 1
        out.append(P(inline(ctx, " ".join(buf)), **bx))
    return "".join(out)


# --------------------------------------------------------------------------- styles / settings
def setup_styles(doc):
    st = doc.styles
    # docDefaults: fonts + bidi language
    dd = st.element.find(qn("w:docDefaults"))
    rd = dd.find(qn("w:rPrDefault")).find(qn("w:rPr"))
    for el in list(rd):
        if el.tag in (qn("w:rFonts"), qn("w:lang")): rd.remove(el)
    rd.insert(0, parse_xml(f'<w:rFonts {NS} w:ascii="{LATIN_FONT}" w:hAnsi="{LATIN_FONT}" w:eastAsia="{LATIN_FONT}" w:cs="{FA_FONT}"/>'))
    rd.append(parse_xml(f'<w:lang {NS} w:val="en-US" w:eastAsia="en-US" w:bidi="fa-IR"/>'))
    normal = st["Normal"]; normal.font.size = Pt(11)
    normal.element.get_or_add_rPr().append(parse_xml(f'<w:szCs {NS} w:val="22"/>'))
    pf = normal.paragraph_format; pf.line_spacing = 1.45; pf.space_after = Pt(6); pf.widow_control = True
    sizes = {1: 20, 2: 15, 3: 13, 4: 12, 5: 11.5, 6: 11}
    for lvl, sz in sizes.items():
        h = st[f"Heading {lvl}"]
        h.font.size = Pt(sz); h.font.bold = True; h.font.italic = False; h.font.color.rgb = RGBColor(0x1B, 0x1F, 0x24)
        rp = h.element.get_or_add_rPr()
        for el in list(rp):
            if el.tag in (qn("w:rFonts"), qn("w:szCs"), qn("w:bCs")): rp.remove(el)
        rp.insert(0, parse_xml(f'<w:rFonts {NS} w:ascii="{LATIN_FONT}" w:hAnsi="{LATIN_FONT}" w:eastAsia="{LATIN_FONT}" w:cs="{FA_FONT}"/>'))
        rp.append(parse_xml(f'<w:bCs {NS}/>')); rp.append(parse_xml(f'<w:szCs {NS} w:val="{int(sz * 2)}"/>'))
        h.paragraph_format.space_before = Pt({1: 0, 2: 18}.get(lvl, 12)); h.paragraph_format.space_after = Pt(8 if lvl <= 2 else 4)
        h.paragraph_format.keep_with_next = True
        h.paragraph_format.page_break_before = lvl == 1
    # custom styles (XML, so the ids are stable): part title (TOC level 1), front title, footnote text
    extra = [
        ("KDJPartTitle", "KDJ Part Title", '<w:pPr><w:keepNext/><w:pageBreakBefore/><w:spacing w:before="4800" w:after="480"/><w:jc w:val="center"/><w:outlineLvl w:val="0"/></w:pPr>',
         f'<w:rPr><w:rFonts w:ascii="{LATIN_FONT}" w:hAnsi="{LATIN_FONT}" w:cs="{FA_FONT}"/><w:b/><w:bCs/><w:color w:val="1B1F24"/><w:sz w:val="52"/><w:szCs w:val="52"/></w:rPr>'),
        ("KDJFrontTitle", "KDJ Front Title", '<w:pPr><w:keepNext/><w:spacing w:before="240" w:after="240"/><w:jc w:val="start"/></w:pPr>',
         f'<w:rPr><w:rFonts w:ascii="{LATIN_FONT}" w:hAnsi="{LATIN_FONT}" w:cs="{FA_FONT}"/><w:b/><w:bCs/><w:sz w:val="36"/><w:szCs w:val="36"/></w:rPr>'),
        ("KDJFootnoteText", "KDJ Footnote Text", '<w:pPr><w:bidi/><w:spacing w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="both"/></w:pPr>',
         '<w:rPr><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr>'),
        ("KDJHeaderText", "KDJ Header Text", '<w:pPr><w:bidi/><w:pBdr><w:bottom w:val="single" w:sz="4" w:space="2" w:color="B8C0CC"/></w:pBdr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/></w:pPr>',
         '<w:rPr><w:color w:val="5B6470"/><w:sz w:val="17"/><w:szCs w:val="17"/></w:rPr>'),
    ]
    for sid, name, p_, r_ in extra:
        st.element.append(parse_xml(f'<w:style {NS} w:type="paragraph" w:customStyle="1" w:styleId="{sid}"><w:name w:val="{name}"/>'
                                    f'<w:basedOn w:val="Normal"/><w:qFormat/>{p_}{r_}</w:style>'))


SETTINGS_ORDER = ["writeProtection", "view", "zoom", "removePersonalInformation", "removeDateAndTime", "doNotDisplayPageBoundaries",
                  "displayBackgroundShape", "printPostScriptOverText", "printFractionalCharacterWidth", "printFormsData", "embedTrueTypeFonts",
                  "embedSystemFonts", "saveSubsetFonts", "saveFormsData", "mirrorMargins", "alignBordersAndEdges", "bordersDoNotSurroundHeader",
                  "bordersDoNotSurroundFooter", "gutterAtTop", "hideSpellingErrors", "hideGrammaticalErrors", "activeWritingStyle", "proofState",
                  "formsDesign", "attachedTemplate", "linkStyles", "stylePaneFormatFilter", "stylePaneSortMethod", "documentType", "mailMerge",
                  "revisionView", "trackRevisions", "doNotTrackMoves", "doNotTrackFormatting", "documentProtection", "autoFormatOverride",
                  "styleLockTheme", "styleLockQFSet", "defaultTabStop", "autoHyphenation", "consecutiveHyphenLimit", "hyphenationZone",
                  "doNotHyphenateCaps", "showEnvelope", "summaryLength", "clickAndTypeStyle", "defaultTableStyle", "evenAndOddHeaders",
                  "bookFoldRevPrinting", "bookFoldPrinting", "bookFoldPrintingSheets", "drawingGridHorizontalSpacing", "drawingGridVerticalSpacing",
                  "displayHorizontalDrawingGridEvery", "displayVerticalDrawingGridEvery", "doNotUseMarginsForDrawingGridOrigin",
                  "drawingGridHorizontalOrigin", "drawingGridVerticalOrigin", "doNotShadeFormData", "noPunctuationKerning", "characterSpacingControl",
                  "printTwoOnOne", "strictFirstAndLastChars", "noLineBreaksAfter", "noLineBreaksBefore", "savePreviewPicture",
                  "doNotValidateAgainstSchema", "saveInvalidXml", "ignoreMixedContent", "alwaysShowPlaceholderText", "doNotDemarcateInvalidXml",
                  "saveXmlDataOnly", "useXSLTWhenSaving", "saveThroughXslt", "showXMLTags", "alwaysMergeEmptyNamespace", "updateFields",
                  "hdrShapeDefaults", "footnotePr", "endnotePr", "compat", "docVars", "rsids", "mathPr", "attachedSchema", "themeFontLang",
                  "clrSchemeMapping", "doNotIncludeSubdocsInStats", "doNotAutoCompressPictures", "forceUpgrade", "captions", "readModeInkLockDown",
                  "smartTagType", "schemaLibrary", "shapeDefaults", "doNotEmbedSmartTags", "decimalSymbol", "listSeparator"]


def settings_add(doc, xml):
    root = doc.settings.element
    el = parse_xml(xml)
    name = el.tag.split("}")[1]
    for old in root.findall(qn("w:" + name)): root.remove(old)
    idx = SETTINGS_ORDER.index(name)
    for child in root:
        cn = child.tag.split("}")[1]
        if cn in SETTINGS_ORDER and SETTINGS_ORDER.index(cn) > idx:
            child.addprevious(el); return
    root.append(el)


def sect_xml(*, margins="normal", header_refs="", pgnum="", titlepg=False) -> str:
    if margins == "zero":
        mar = '<w:pgMar w:top="0" w:right="0" w:bottom="0" w:left="0" w:header="0" w:footer="0" w:gutter="0"/>'
    else:   # mirrored: inside 25 mm (+ 8 mm gutter handled by inside value), outside 18 mm
        mar = (f'<w:pgMar w:top="{int(25 * 56.7)}" w:right="{int(18 * 56.7)}" w:bottom="{int(22 * 56.7)}" w:left="{int(25 * 56.7)}" '
               f'w:header="{int(12 * 56.7)}" w:footer="{int(10 * 56.7)}" w:gutter="{int(6 * 56.7)}"/>')
    return (f"<w:sectPr {NS}>{header_refs}<w:type w:val=\"nextPage\"/><w:pgSz w:w=\"11906\" w:h=\"16838\"/>{mar}{pgnum}"
            f"{'<w:titlePg/>' if titlepg else ''}<w:bidi/><w:rtlGutter/></w:sectPr>")


# --------------------------------------------------------------------------- package helpers
def add_part(doc, uri, ctype, rel, xml: bytes):
    part = Part(PackURI(uri), ctype, xml, doc.part.package)
    doc.part.relate_to(part, rel)
    return part


def header_part(doc, kind, xml_body):
    ctype = f"application/vnd.openxmlformats-officedocument.wordprocessingml.{kind}+xml"
    rel = RT.HEADER if kind == "header" else RT.FOOTER
    tag = "hdr" if kind == "header" else "ftr"
    n = len([p for p in doc.part.package.iter_parts() if p.partname.startswith(f"/word/{kind}")]) + 1
    part = Part(PackURI(f"/word/{kind}{n}.xml"), ctype,
                f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:{tag} {NS}>{xml_body}</w:{tag}>'.encode(), doc.part.package)
    return doc.part.relate_to(part, rel)


def cover_paragraph(doc, sect: str) -> str:
    try:
        from PIL import Image
    except ImportError:
        return P(runs(""), sect=sect)
    if not COVER.exists(): return P(runs(""), sect=sect)
    im = Image.open(COVER).convert("RGB"); buf = io.BytesIO(); im.save(buf, "PNG", optimize=False); buf.seek(0)
    w_px, h_px = im.size
    h_mm = 296.0; w_mm = h_mm * w_px / h_px
    if w_mm > 210: w_mm, h_mm = 210.0, 210.0 * h_px / w_px
    rid, image = doc.part.get_or_add_image(buf)
    cx, cy = int(Mm(w_mm)), int(Mm(h_mm))
    doc._kdj_pic = getattr(doc, "_kdj_pic", 0) + 1
    pid = doc._kdj_pic
    drawing = (f'<w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0">'
               f'<wp:extent cx="{cx}" cy="{cy}"/><wp:docPr id="{pid}" name="Cover {pid}" descr="جلد کتاب"/>'
               '<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
               '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
               f'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="{pid}" name="cover.png"/><pic:cNvPicPr/></pic:nvPicPr>'
               f'<pic:blipFill><a:blip r:embed="{rid}"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
               f'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="{cx}" cy="{cy}"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
               '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r>')
    return P(drawing, jc="center", before=0, after=0, line=1.0, sect=sect)


# --------------------------------------------------------------------------- build
def load():
    book = json.load(open(ROOT / "master" / "book.json", encoding="utf-8"))
    ov = json.load(open(ROOT / "master" / "reader-overlay.json", encoding="utf-8"))
    src = {d["doc_key"]: d for d in json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]}
    titles = json.load(open(ROOT / "taxonomy" / "doc_titles_fa.json", encoding="utf-8"))
    gloss = json.load(open(ROOT / "release" / "09_glossary.json", encoding="utf-8"))["terms"]
    return book, ov, src, titles, gloss


def structure(book, ov):
    """Overlay-applied parts → chapters → sections (omitted sections dropped)."""
    parts = []
    for p in book["parts"]:
        chs = []
        for c in p["chapters"]:
            secs = []
            for s in c["sections"]:
                o = ov["sections"].get(s["structural_id"], {})
                if o.get("omit"): continue
                secs.append({**s, "title_fa": o.get("title_fa") or s["title_fa"], "fa_text": o.get("fa_text", s["fa_text"])})
            if secs:
                chs.append({**c, "title_fa": (ov["chapters"].get(c["chapter_id"]) or {}).get("title_fa") or c["title_fa"], "sections": secs})
        parts.append({**p, "chapters": chs})
    return parts


def build():
    book, ov, src, titles, gloss = load()
    parts = structure(book, ov)
    doc = Document()
    setup_styles(doc)
    ctx = Ctx(book); ctx.part = doc.part
    body = doc.element.body
    for el in list(body): body.remove(el)
    X: list[str] = []
    title_fa, title_en = book["title_fa"], book["title_en"]
    edition = book["edition_id"]
    built = book["built_at"][:10]

    # 1. front cover — own zero-margin section
    X.append(cover_paragraph(doc, sect_xml(margins="zero")))

    # 2. title page
    X.append(P(runs(title_fa, bold=True, size=28), jc="center", before=150, after=18, line=1.2))
    X.append(P(runs(title_en, latin=True, size=12, color="5B6470"), jc="center", rtl=False, after=60))
    X.append(P(runs("ترجمه، تدوین و بازآرایی فارسی", size=12, color="5B6470"), jc="center", after=2))
    X.append(P(runs(book["compiler"], bold=True, size=16), jc="center", after=40))
    X.append(P(runs(f"ویراست {edition}", size=12), jc="center", after=2))
    X.append(P(runs("kohandezh.com", latin=True, size=12, color="1F5FAD"), jc="center", after=0))

    # copyright / attribution page
    X.append(P(runs("شناسنامهٔ کتاب"), style="KDJFrontTitle", page_break=True))
    rows = [["عنوان", title_fa], ["عنوان انگلیسی", title_en], ["ترجمه، تدوین و بازآرایی", book["compiler"]],
            ["ویراست", edition], ["تاریخ ساخت", built], ["وب‌سایت", "kohandezh.com"],
            ["ریشهٔ مرکل", book["merkle_root"]], ["مشخصات هش", book["hash_spec"]],
            ["تعداد بخش‌های محتوایی", fa_num(sum(len(c["sections"]) for p in parts for c in p["chapters"]))]]
    X.append(table(ctx, rows, header=False, size=10))
    X.append(P(inline(ctx, book["attribution"], size=10.5), before=6))
    X.append(P(runs(NIST_DISCLAIMER, bold=True, size=10.5), fill="F6F8FA", border="B8C0CC"))
    X.append(P(inline(ctx, "متن اصلی هر سند به نویسندگان و ناشر اصلی آن تعلق دارد. بخش‌های تدوینگر با کادر و برچسب مشخص از متن ترجمه‌شده متمایز شده‌اند.", size=10)))
    X.append(P(inline(ctx, "هر بخش این کتاب یک شناسهٔ محتوا (Content ID) و هش SHA-256 دارد که در درخت مرکل این ویراست ثبت شده است. "
                           "اصالت هر نسخه را در [kohandezh.com/ai-book/verify](https://kohandezh.com/ai-book/verify/) بررسی کنید.", size=10)))

    # 3. table of contents: real field + static list
    X.append(P(runs("فهرست مطالب"), style="KDJFrontTitle", page_break=True))
    X.append(P(field('TOC \\t "KDJ Part Title,1,Heading 1,2,Heading 2,3" \\h \\z',
                     "برای دیدن شمارهٔ صفحه‌ها، فهرست را به‌روزرسانی کنید (کلیک راست ← به‌روزرسانی فیلد، یا F9).", size=10, color="5B6470")))
    X.append(P(runs("ساختار کتاب"), style="KDJFrontTitle", page_break=True))
    chap_no = 0
    for pi, p in enumerate(parts):
        X.append(P(runs(f"بخش {ORDINALS[pi]} — " + fa_digits_outside_latin(p["title_fa"]), bold=True, size=11.5), before=8, after=2, keep_next=True))
        for c in p["chapters"]:
            chap_no += 1
            X.append(P(runs(f"فصل {fa_num(chap_no)} — " + fa_digits_outside_latin(plain(c["title_fa"])), size=10.5), ind_start=0.8, after=0, line=1.25))

    # end of front matter section (no headers, no page numbers)
    X.append(P(sect=sect_xml()))

    # 4. body
    chap_no = 0
    for pi, p in enumerate(parts):
        X.append(P(runs(f"بخش {ORDINALS[pi]}", size=16, color="5B6470") + '<w:r><w:br/></w:r>' + runs(fa_digits_outside_latin(p["title_fa"])), style="KDJPartTitle"))
        X.append(P(runs(p["title_en"], latin=True, size=11, color="5B6470"), jc="center", rtl=False, after=24))
        srcs = [titles.get(d, {}).get("title_fa") or src.get(d, {}).get("title", d) for d in p.get("sources", [])]
        if srcs:
            X.append(P(runs("اسناد پشتوانه", bold=True, size=10, color="5B6470"), jc="center", after=2))
            for d in p.get("sources", []):
                X.append(P(runs((titles.get(d, {}).get("title_fa") or "") + " — ", size=9.5, color="5B6470")
                           + runs(src.get(d, {}).get("series_identifier", d), latin=True, size=9.5, color="5B6470"), jc="center", after=0, line=1.2))
        for c in p["chapters"]:
            chap_no += 1
            X.append(P(runs(f"فصل {fa_num(chap_no)} — " + fa_digits_outside_latin(plain(c["title_fa"]))), style="Heading1", jc="start"))
            if c.get("source_doc"):
                d = src.get(c["source_doc"], {})
                X.append(P(runs("منبع اصلی: ", bold=True, size=9.5, color="5B6470")
                           + runs((titles.get(c["source_doc"], {}).get("title_fa") or "") + " — ", size=9.5, color="5B6470")
                           + runs(f'{d.get("series_identifier", "")} · {d.get("title", "")}', latin=True, size=9.5, color="5B6470"), after=10))
            elif c.get("origin", "").startswith("editorial"):
                X.append(P(runs("این فصل محتوای تدوینگر است و متن NIST نیست.", italic=True, size=9.5, color="5B6470"), after=10))
            for s in c["sections"]:
                X.append(P(runs(fa_digits_outside_latin(plain(s["title_fa"]))), style="Heading2", jc="start"))
                X.append(P(runs(s["citation_id"], latin=True, mono=False, size=7.5, color="8A94A0"), jc="start", after=4, rtl=True))
                if s["origin"] == "source_translation":
                    X.append(section_body(ctx, s["fa_text"]))
                else:
                    fill, border = BOX.get(s["origin"], BOX["editorial_synthesis"])
                    X.append(P(runs(s.get("label_fa") or "یادداشت تدوینگر", bold=True, size=9.5, color="5B4A10"), fill=fill, border=border, after=0, keep_next=True))
                    X.append(section_body(ctx, s["fa_text"], box=(fill, border)))

    # 5. back matter
    X.append(P(runs("واژه‌نامه"), style="Heading1", jc="start"))
    X.append(P(runs("برابرنهاده‌های فارسی اصطلاحات کلیدی که در سراسر کتاب به کار رفته‌اند.", size=10)))
    terms = sorted(gloss.values(), key=lambda g: (g.get("preferred_fa") or "", g.get("english") or ""))
    rows = [["اصطلاح فارسی", "English", "تعریف"]]
    for g in terms:
        if not g.get("preferred_fa"): continue
        en = g.get("english", "") + (f" ({g['acronym']})" if g.get("acronym") else "")
        rows.append([g["preferred_fa"], en, g.get("definition_fa") or ""])
    X.append(table(ctx, rows, header=True, size=9))

    X.append(P(runs("منابع"), style="Heading1", jc="start"))
    X.append(P(runs("اسناد اصلی‌ای که این کتاب بر پایهٔ آن‌ها ترجمه و تدوین شده است.", size=10)))
    rows = [["عنوان فارسی", "عنوان اصلی", "شناسه", "تاریخ"]]
    for key in sorted(src, key=lambda k: src[k].get("seq", 0)):
        d = src[key]
        rows.append([titles.get(key, {}).get("title_fa", ""), d.get("title", ""), d.get("series_identifier", key), str(d.get("publication_date") or "")])
    X.append(table(ctx, rows, header=True, size=9))

    X.append(P(runs("دربارهٔ تدوینگر"), style="Heading1", jc="start"))
    X.append(P(runs(book["compiler"], bold=True, size=14), after=4))
    X.append(P(inline(ctx, book["attribution"])))
    X.append(P(runs("وب‌سایت: ") + inline(ctx, f"[kohandezh.com]({book['website']})")))
    X.append(P(runs(NIST_DISCLAIMER, size=10, color="5B6470")))

    # body/back-matter section: running headers, Persian page numbers
    h_even = header_part(doc, "header", P(runs(title_fa, size=8.5), style="KDJHeaderText"))
    h_odd = header_part(doc, "header", P(field('STYLEREF "Heading 1"', "", size=8.5), style="KDJHeaderText"))
    f_all = header_part(doc, "footer", P(field("PAGE", "", latin=False, size=10), jc="center", after=0, line=1.0))
    refs = (f'<w:headerReference w:type="even" r:id="{h_even}"/><w:headerReference w:type="default" r:id="{h_odd}"/>'
            f'<w:footerReference w:type="even" r:id="{f_all}"/><w:footerReference w:type="default" r:id="{f_all}"/>')
    X.append(P(sect=sect_xml(header_refs=refs, pgnum='<w:pgNumType w:fmt="hindiNumbers" w:start="1"/>')))

    # 6. back cover — empty headers so the body headers are not inherited
    e_h = header_part(doc, "header", P())
    e_f = header_part(doc, "footer", P())
    empty = (f'<w:headerReference w:type="even" r:id="{e_h}"/><w:headerReference w:type="default" r:id="{e_h}"/>'
             f'<w:footerReference w:type="even" r:id="{e_f}"/><w:footerReference w:type="default" r:id="{e_f}"/>')
    last = cover_paragraph(doc, "")
    X.append(last)
    # final section properties live on the body
    xml = f"<w:body {NS}>" + "".join(X) + sect_xml(margins="zero", header_refs=empty) + "</w:body>"
    new_body = parse_xml(xml)
    for el in list(new_body): body.append(el)

    # footnotes part
    fx = (f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:footnotes {NS}>'
          f'<w:footnote w:type="separator" w:id="-1"><w:p><w:pPr><w:bidi/><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr><w:r><w:separator/></w:r></w:p></w:footnote>'
          f'<w:footnote w:type="continuationSeparator" w:id="0"><w:p><w:pPr><w:bidi/><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr><w:r><w:continuationSeparator/></w:r></w:p></w:footnote>'
          + "".join(ctx.notes) + "</w:footnotes>")
    fpart = add_part(doc, "/word/footnotes.xml", "application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml", RT.FOOTNOTES, fx.encode())
    for rid, url in ctx.fn_part_links:
        fpart.rels.add_relationship(RT.HYPERLINK, url, rid, is_external=True)

    # settings
    settings_add(doc, f'<w:mirrorMargins {NS}/>')
    settings_add(doc, f'<w:evenAndOddHeaders {NS}/>')
    settings_add(doc, f'<w:updateFields {NS} w:val="true"/>')
    settings_add(doc, f'<w:footnotePr {NS}><w:footnote w:id="-1"/><w:footnote w:id="0"/></w:footnotePr>')
    settings_add(doc, f'<w:themeFontLang {NS} w:val="en-US" w:bidi="fa-IR"/>')

    # properties (fixed timestamps → deterministic)
    cp = doc.core_properties
    stamp = dt.datetime.strptime(book["built_at"][:19], "%Y-%m-%dT%H:%M:%S")
    cp.title = title_fa; cp.subject = title_en; cp.author = book["compiler"]; cp.last_modified_by = book["compiler"]
    cp.language = "fa-IR"; cp.category = edition; cp.revision = 1
    cp.keywords = "حاکمیت هوش مصنوعی; مدیریت ریسک; امنیت هوش مصنوعی; NIST; AI RMF; کهن‌دژ"
    cp.comments = f"Edition {edition}; merkle root {book['merkle_root']}; verify at https://kohandezh.com/ai-book/verify/"
    cp.created = stamp; cp.modified = stamp; cp.last_printed = stamp

    OUT.parent.mkdir(parents=True, exist_ok=True)
    buf = io.BytesIO(); doc.save(buf)
    # rewrite the zip with fixed member timestamps and order
    zin = zipfile.ZipFile(io.BytesIO(buf.getvalue()))
    with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED) as zout:
        for name in zin.namelist():
            zi = zipfile.ZipInfo(name, date_time=stamp.timetuple()[:6]); zi.compress_type = zipfile.ZIP_DEFLATED
            zout.writestr(zi, zin.read(name))
    shutil.copyfile(OUT, OUT.with_suffix(".doc"))
    Document(str(OUT))   # re-open check
    print(f"built {OUT} ({OUT.stat().st_size} bytes); parts={len(parts)} chapters={chap_no} "
          f"sections={sum(len(c['sections']) for p in parts for c in p['chapters'])} footnotes={len(ctx.notes)}")


if __name__ == "__main__":
    sys.exit(build())

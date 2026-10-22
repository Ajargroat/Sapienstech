#!/usr/bin/env python3
"""
Extract one textbook PDF into JSON + PNGs for the Laravel lesson library.

    python scripts/pdf/extract.py --in job.json --out result.json

Both files are UTF-8 JSON. NOTHING but ASCII diagnostic lines ever crosses
stdout/stderr: a Windows console codepage mangles Persian, and this worker is
called from PHP through Symfony Process with an args array (the repo's
file->file bridge discipline, scripts/build-contract.mjs).

Exit codes: 0 = every PDF extracted, 2 = at least one PDF failed (see its
`error` in result.json), 3 = usage error.

Deliberately NOT in v1: OCR. `--ocr-engine` is listed in --help and refuses,
because there is no engine binary on this machine and a pipeline that silently
returns empty text is worse than one that says "needs_ocr" honestly.
"""

import argparse
import json
import sys
import traceback

TEXT_MIN_CHARS = 40
LOW_TEXT_CHARS = 800
MODE_DISAGREEMENT_RATIO = 0.15
FIGURE_MIN_AREA_RATIO = 0.02
FIGURE_MIN_PX = (120, 80)
FIGURE_MAX_ASPECT = 8.0
FIGURE_MAX_XREF_REPEAT = 6
FIGURE_BACKGROUND_TEXT_RATIO = 0.6
# Below the hard ratio above, but still heavily text-covered: accept the crop
# yet mark it `low` confidence so the editor shows the figure with a warning
# instead of silently trusting it.
FIGURE_BACKGROUND_TEXT_SOFT = 0.35
# Share of an image that must be STRONGLY SATURATED colour before it is treated
# as designed artwork (a letterhead) rather than a figure.
#
# Measured on one Persian histology handout, three images on the same book:
#   page-1 teal letterhead  vivid 0.220   <- chrome
#   page-1 histology photo  vivid 0.018   <- figure
#   page-6 glomerulus chart vivid 0.097   <- figure
# 0.15 sits between them with ~1.5x headroom on both sides. Two rules were
# tried first and thrown away because the measurements refuted them: (a) the
# dominant colour band is 0.753 for the banner ONLY because 75% of it is white
# paper -- excluding white leaves 0.091, below every figure; (b) text coverage
# inside the crop read 0.06 for the banner, because a banner carries its own
# baked-in text.
FIGURE_VIVID_RATIO = 0.15
# A pixel is "vivid" when a channel spread this wide shows up on a colour that
# is not near-black. Saturation alone is not enough: grey/sepia photography has
# low spread even where its tones are dark.
FIGURE_VIVID_SPREAD = 90
FIGURE_VIVID_LEVEL = 60
# An embedded image smaller than this (in PDF points on the page) is chrome
# -- an icon, a rule, a bullet glyph -- not a figure worth showing.
FIGURE_MIN_POINT_SIDE = 48.0
VECTOR_MIN_AREA_RATIO = 0.04


def _log(msg):
    """ASCII-only progress line. Never Persian (see module docstring)."""
    try:
        sys.stderr.write("[extract] %s\n" % msg.encode("ascii", "replace").decode("ascii"))
        sys.stderr.flush()
    except Exception:
        pass


# ---------------------------------------------------------------------------
# Text normalisation (PREFERENCE §7.14's measured traps)
# ---------------------------------------------------------------------------

_ARABIC_TO_PERSIAN = {
    0x064A: 0x06CC,  # ARABIC YEH -> FARSI YEH
    0x0649: 0x06CC,  # ALEF MAKSURA -> FARSI YEH
    0x0643: 0x06A9,  # ARABIC KAF -> KEHEH
}
_HARAKAT = set(range(0x064B, 0x0653)) | {0x0670, 0x0653, 0x0654, 0x0655}
_ZERO_WIDTH = {0x200B, 0x200E, 0x200F, 0x202A, 0x202B, 0x202C, 0x202D, 0x202E}


def _classify_letter(ch):
    """Persian letters are strong RTL, Latin/digits strong LTR. Rest neutral."""
    o = ord(ch)
    if 0x0600 <= o <= 0x06FF or 0xFB50 <= o <= 0xFBFF or 0xFE70 <= o <= 0xFEFF:
        return -1
    if ("A" <= ch <= "Z") or ("a" <= ch <= "z") or ("0" <= ch <= "9"):
        return 1
    return 0


def normalise(text):
    """
    NFKC, unify Persian yeh/kaf, drop harakat and stray bidi marks, collapse
    spaces. Never "fix" what the book says: a faithful record of a misspelling
    stays, and the block's confidence flag is what tells a human to compare it
    with the page image.
    """
    import unicodedata

    text = unicodedata.normalize("NFKC", text)
    out = []
    for ch in text:
        o = ord(ch)
        if o in _HARAKAT or o in _ZERO_WIDTH:
            continue
        out.append(chr(_ARABIC_TO_PERSIAN.get(o, o)))
    text = "".join(out)
    text = " ".join(text.split())
    return text.strip()


def rtl_score(text):
    rtl = sum(1 for ch in text if _classify_letter(ch) < 0)
    ltr = sum(1 for ch in text if _classify_letter(ch) > 0)
    return rtl - ltr


# ---------------------------------------------------------------------------
# Page text reconstruction
# ---------------------------------------------------------------------------

def _spans(page):
    """Both dict modes, so a mode that silently drops a line is caught."""
    data = page.get_text("rawdict")
    spans = []
    for block in data.get("blocks", []):
        if block.get("type") != 0:
            continue
        for line in block.get("lines", []):
            chars = []
            for span in line.get("spans", []):
                for ch in span.get("chars", []):
                    chars.append(ch)
            if not chars:
                continue
            x0 = min(c["bbox"][0] for c in chars)
            y0 = min(c["bbox"][1] for c in chars)
            x1 = max(c["bbox"][2] for c in chars)
            y1 = max(c["bbox"][3] for c in chars)
            text = "".join(c["c"] for c in chars)
            size = max((span.get("size") or 0) for span in line.get("spans", [])) or (y1 - y0)
            spans.append({"text": text, "x0": x0, "y0": y0, "x1": x1, "y1": y1, "size": size})
    return spans


def _words_chars(page):
    words = page.get_text("words")
    return sum(len(w[4]) for w in words)


def _axis_of(spans):
    """
    Which direction does the writer's line run on THIS page? Compare the
    extraction order's horizontal drift with the line direction: a Persian
    line, read left-to-right in the raw order, drifts leftwards.
    """
    if len(spans) < 4:
        return "rtl"
    firsts, lasts = [], []
    for s in spans:
        rtl_heavy = rtl_score(s["text"]) > 0
        firsts.append(s["x1"] if rtl_heavy else s["x0"])
        lasts.append(s["x0"] if rtl_heavy else s["x1"])
    n = min(len(firsts), 30)
    drift_first = sum(firsts[:n]) / n
    drift_last = sum(lasts[:n]) / n
    # If the "first" end sits to the right of the "last" end on average and the
    # index grows leftwards, the writer runs right-to-left.
    return "rtl" if drift_first >= drift_last else "ltr"


def _rows(spans):
    """Cluster spans into visual rows by y, then order each row by the axis."""
    if not spans:
        return []
    heights = sorted(s["y1"] - s["y0"] for s in spans)
    median_h = heights[len(heights) // 2] or 10.0
    tol = 0.6 * median_h
    axis = _axis_of(spans)

    ordered = sorted(spans, key=lambda s: (s["y0"], -(s["x1"] if axis == "rtl" else -s["x0"])))
    rows = []
    for span in ordered:
        placed = False
        for row in rows:
            if abs(row["y"] - span["y0"]) <= tol:
                row["spans"].append(span)
                row["y"] = (row["y"] * (len(row["spans"]) - 1) + span["y0"]) / len(row["spans"])
                placed = True
                break
        if not placed:
            rows.append({"y": span["y0"], "spans": [span]})

    rows.sort(key=lambda r: r["y"])
    for row in rows:
        row["spans"].sort(key=lambda s: (-s["x1"]) if axis == "rtl" else s["x0"])
    return [(row["y"], row["spans"]) for row in rows], axis


def _row_text(spans, axis):
    if not spans:
        return ""
    widths = [s["x1"] - s["x0"] for s in spans if s["x1"] > s["x0"]]
    median_w = (sorted(widths)[len(widths) // 2] / 8.0) if widths else 3.0
    gap_needed = max(1.5, median_w * 0.12)

    parts = []
    prev = None
    for span in spans:
        if prev is not None:
            gap = (prev["x0"] - span["x1"]) if axis == "rtl" else (span["x0"] - prev["x1"])
            if gap > gap_needed * 3:
                parts.append("  ")
            elif gap > gap_needed:
                parts.append(" ")
        parts.append(span["text"])
        prev = span
    return normalise("".join(parts))


def _paragraphs(rows, axis):
    paras = []
    current = []
    prev_y = None
    gaps = []
    for i in range(1, len(rows)):
        gaps.append(max(0.0, rows[i][0] - rows[i - 1][0]))
    sorted_gaps = sorted(g for g in gaps if g > 0)
    median_gap = sorted_gaps[len(sorted_gaps) // 2] if sorted_gaps else 0.0

    for y, spans in rows:
        text = _row_text(spans, axis)
        if text == "":
            if current:
                paras.append(" ".join(current))
                current = []
            prev_y = y
            continue
        if prev_y is not None and current:
            gap = y - prev_y
            if median_gap > 0 and gap > 1.6 * median_gap:
                paras.append(" ".join(current))
                current = []
        current.append(text)
        prev_y = y
    if current:
        paras.append(" ".join(current))

    return [p for p in (normalise(p) for p in paras) if p]


def read_page_text(page):
    spans = _spans(page)
    chars_raw = len(normalise(" ".join(s["text"] for s in spans)))
    chars_words = _words_chars(page)
    flags = []

    if chars_raw == 0 and chars_words == 0:
        return None, 0, ["no_text_layer"]

    if chars_words and chars_raw:
        diff = abs(chars_words - chars_raw) / max(chars_words, chars_raw)
        if diff > MODE_DISAGREEMENT_RATIO:
            flags.append("mode_disagreement")

    if not spans:
        # `words` found text that `rawdict` did not (or the reverse) -- rebuild
        # from words so the page is not silently empty.
        spans = [
            {"text": w[4], "x0": w[0], "y0": w[1], "x1": w[2], "y1": w[3], "size": w[3] - w[1] or 10.0}
            for w in page.get_text("words")
        ]

    built = _rows(spans)
    if isinstance(built, tuple):
        rows, axis = built
    else:
        rows, axis = [], "rtl"
    paras = _paragraphs(rows, axis)
    text = normalise("\n\n".join(paras))

    if len(rows) == 1:
        flags.append("single_line")

    return ({"paragraphs": paras, "text": text, "axis": axis}, len(text), flags)


# ---------------------------------------------------------------------------
# Figures
# ---------------------------------------------------------------------------

def _page_image_area(page):
    rect = page.rect
    return max(1.0, rect.width * rect.height)


def find_figures(doc, page, page_area):
    """
    Candidates -> reject rules -> accepted crops. Returns (accepted, rejected)
    where each accepted item carries a page clip rect ready to render.
    """
    import pymupdf

    accepted = []
    rejected = []

    xref_counts = {}
    info = []
    try:
        info = page.get_image_info(xrefs=True)
    except Exception:
        info = []

    def background_text_ratio(rect):
        """
        How much of a large image is covered by the page's own text.

        A full-width banner/letterhead sits BEHIND body text for most of its
        height; a real figure sits beside or after the text. This is what
        separates the two -- size alone cannot. Measured case: a 593x48pt
        teal letterhead banner on page 1 of a Persian histology handout was
        accepted as a figure because it passed the area and aspect rules.
        """
        spans = _spans(page)
        if not spans:
            return 0.0
        covered = 0.0
        total = max(1e-6, rect.width * rect.height)

        for span in spans:
            sr = pymupdf.Rect(span["x0"], span["y0"], span["x1"], span["y1"]) & rect
            if not sr.is_empty:
                covered += sr.width * sr.height

        return min(1.0, covered / total)

    for entry in info:
        xref = entry.get("xref")
        if not xref:
            continue
        xref_counts[xref] = xref_counts.get(xref, 0) + 1
        bbox = pymupdf.Rect(entry["bbox"]) & page.rect
        info_width = entry.get("width") or bbox.width
        info_height = entry.get("height") or bbox.height
        if bbox.is_empty or bbox.width < 1 or bbox.height < 1:
            rejected.append({"xref": xref, "reason": "empty_bbox"})
            continue
        if min(bbox.width, bbox.height) < FIGURE_MIN_POINT_SIDE:
            rejected.append({"xref": xref, "reason": "too_small_on_page"})
            continue
        accepted.append({
            "xref": str(xref),
            "bbox": [round(v, 2) for v in (bbox.x0, bbox.y0, bbox.x1, bbox.y1)],
            "width": int(info_width or bbox.width),
            "height": int(info_height or bbox.height),
            "confidence": "high",
        })

    # A repeating icon (a logo in the header of every page) is chrome.
    deduped = []
    for item in accepted:
        if xref_counts.get(int(item["xref"]), 1) > FIGURE_MAX_XREF_REPEAT:
            rejected.append({"xref": item["xref"], "reason": "repeated_xref"})
            continue
        deduped.append(item)

    # Vector figures: large clusters of drawings not covered by text.
    try:
        drawings = page.get_drawings()
    except Exception:
        drawings = []
    text_rects = [pymupdf.Rect(s["x0"], s["y0"], s["x1"], s["y1"]) for s in _spans(page)]
    vector_boxes = []
    for drawing in drawings:
        rect = pymupdf.Rect(drawing["rect"]) & page.rect
        if rect.is_empty:
            continue
        area_ratio = (rect.width * rect.height) / page_area
        if area_ratio < VECTOR_MIN_AREA_RATIO:
            continue
        covered = 0
        for tr in text_rects:
            if tr.intersects(rect):
                covered += 1
        if text_rects and covered / max(1, len(text_rects)) > 0.5:
            continue
        vector_boxes.append(rect)

    if vector_boxes:
        # Union of nearby vector boxes = one figure.
        union = vector_boxes[0]
        for rect in vector_boxes[1:]:
            union |= rect
        if (union.width * union.height) / page_area >= VECTOR_MIN_AREA_RATIO:
            deduped.append({
                "xref": None,
                "bbox": [round(v, 2) for v in (union.x0, union.y0, union.x1, union.y1)],
                "width": int(union.width),
                "height": int(union.height),
                "confidence": "low",
            })

    final = []
    for item in deduped:
        x0, y0, x1, y1 = item["bbox"]
        area_ratio = ((x1 - x0) * (y1 - y0)) / page_area
        aspect = (x1 - x0) / max(1.0, (y1 - y0))
        if area_ratio < FIGURE_MIN_AREA_RATIO:
            rejected.append({"xref": item["xref"], "reason": "area_below_threshold"})
            continue
        if aspect > FIGURE_MAX_ASPECT or aspect < (1.0 / FIGURE_MAX_ASPECT):
            rejected.append({"xref": item["xref"], "reason": "aspect"})
            continue
        # Text coverage is only advisory here: a banner carries its own text
        # INSIDE the image, so coverage measured this way can read as ~0.06
        # for pure chrome. It still marks a heavily captioned crop as `low`.
        ratio = background_text_ratio(pymupdf.Rect(x0, y0, x1, y1))
        if ratio >= FIGURE_BACKGROUND_TEXT_RATIO:
            rejected.append({
                "xref": item["xref"],
                "reason": "background_text",
                "text_ratio": round(ratio, 3),
            })
            continue
        if ratio >= FIGURE_BACKGROUND_TEXT_SOFT:
            item = dict(item, confidence="low", text_ratio=round(ratio, 3))
        final.append(item)

    return final, rejected


def vivid_share(doc, item):
    """
    Share of an image painted in strongly saturated colour.

    This is the chrome test that separates a letterhead from a figure. A
    designed banner is built from flat corporate colours, so a fifth of it is
    vividly saturated; a photograph, scan or histological slide spreads its
    tones and stays under a tenth. Measured on one Persian handout: banner
    0.220, histology photo 0.018, glomerulus chart 0.097.

    Returns (share, has_detail). has_detail is False for an all-white/blank
    image; (None, False) means "could not read", which callers must treat as
    unknown rather than as chrome.

    The pixmap is sampled on a grid (~64x64 taps) instead of every pixel: at
    200 dpi a full page figure is millions of pixels and the decision does not
    become more true with more of them.
    """
    # This module has no pymupdf at module scope (each worker imports it
    # locally so the file stays importable without the dependency), so the
    # name must be bound here or every pixel read raises and is swallowed
    # into a silent `None` -- the exact bug that let a letterhead through.
    import pymupdf

    xref = item.get("xref")

    if not xref:
        return None, False

    try:
        pix = pymupdf.Pixmap(doc, int(xref))
        if pix.n >= 4:
            pix = pymupdf.Pixmap(pymupdf.csRGB, pix)
        width, height = pix.width, pix.height
        step_x = max(1, width // 64)
        step_y = max(1, height // 64)
        vivid = 0
        total = 0
        for y in range(0, height, step_y):
            for x in range(0, width, step_x):
                pixel = pix.pixel(x, y)
                total += 1
                high = max(pixel[0], pixel[1], pixel[2])
                low = min(pixel[0], pixel[1], pixel[2])
                if high >= FIGURE_VIVID_LEVEL and (high - low) >= FIGURE_VIVID_SPREAD:
                    vivid += 1
        if total == 0:
            return None, False

        return vivid / total, vivid > 0
    except Exception:
        return None, False

def drop_flat_chrome(doc, accepted, rejected):
    """Reject letterheads/logos, keep photography, scans, diagrams and plots."""
    kept = []

    for item in accepted:
        vivid, has_detail = vivid_share(doc, item)

        if vivid is None:
            # Unreadable pixmap: never guess. Keep it and let a human decide.
            kept.append(item)
            continue

        if not has_detail:
            rejected.append({
                "xref": item.get("xref"),
                "reason": "blank_image",
            })
            continue

        if vivid >= FIGURE_VIVID_RATIO:
            rejected.append({
                "xref": item.get("xref"),
                "reason": "flat_chrome",
                "vivid_ratio": round(vivid, 3),
            })
            continue

        kept.append(item)

    return kept


# ---------------------------------------------------------------------------
# One PDF
# ---------------------------------------------------------------------------

def extract_pdf(pdf_path, pages_dir, figures_dir, page_dpi, figure_dpi, max_pages):
    import pymupdf

    result = {
        "pages": [],
        "figures": [],
        "structure": "unknown",
        "page_count": 0,
        "flags": [],
        "figure_stats": {"accepted": 0, "rejected": 0},
        "error": None,
    }

    doc = pymupdf.open(pdf_path)  # read-only open; never edits the source
    try:
        total = doc.page_count
        result["page_count"] = total
        limit = min(total, max_pages) if max_pages else total
        if limit < total:
            result["flags"].append("truncated_max_pages")

        no_text_pages = 0
        per_page_chars = []

        for index in range(limit):
            number = index + 1
            page = doc.load_page(index)
            page_area = _page_image_area(page)
            entry = {"page_number": number, "char_count": 0, "flags": [], "image_path": None,
                     "width": None, "height": None, "paragraphs": []}

            # 1. page render (works with or without a text layer)
            try:
                pix = page.get_pixmap(dpi=page_dpi, alpha=False)
                path = "%s/%d.png" % (pages_dir, number)
                pix.save(path)
                entry["image_path"] = path
                entry["width"], entry["height"] = pix.width, pix.height
            except Exception as exc:  # noqa: BLE001 - reported, never raised
                entry["flags"].append("render_failed")
                _log("page %d render failed: %s" % (number, exc))

            # 2. text
            try:
                text, char_count, text_flags = read_page_text(page)
            except Exception as exc:  # noqa: BLE001
                text, char_count, text_flags = None, 0, ["text_failed"]
                _log("page %d text failed: %s" % (number, exc))
            entry["char_count"] = char_count
            entry["flags"].extend(text_flags)
            per_page_chars.append(char_count)
            if char_count == 0:
                no_text_pages += 1
            if 0 < char_count < TEXT_MIN_CHARS:
                entry["flags"].append("low_text")
            elif TEXT_MIN_CHARS <= char_count <= LOW_TEXT_CHARS and info_has_images(page):
                entry["flags"].append("low_text")
            if text is not None:
                entry["paragraphs"] = text["paragraphs"]
                entry["axis"] = text["axis"]
                if text["axis"] == "ambiguous":
                    entry["flags"].append("axis_ambiguous")

            # 3. figures (rendered from the page, so scanned PDFs still get them)
            try:
                accepted, rejected = find_figures(doc, page, page_area)
                accepted = drop_flat_chrome(doc, accepted, rejected)
            except Exception as exc:  # noqa: BLE001
                accepted, rejected = [], []
                _log("page %d figures failed: %s" % (number, exc))
            for i, fig in enumerate(accepted):
                try:
                    clip = pymupdf.Rect(fig["bbox"])
                    pix = page.get_pixmap(clip=clip, dpi=figure_dpi, alpha=False)
                    path = "%s/%d-%d.png" % (figures_dir, number, i + 1)
                    pix.save(path)
                    fig.update({"page_number": number, "image_path": path,
                                "crop_width": pix.width, "crop_height": pix.height})
                    result["figures"].append(fig)
                    result["figure_stats"]["accepted"] += 1
                except Exception as exc:  # noqa: BLE001
                    _log("page %d figure crop failed: %s" % (number, exc))
            result["figure_stats"]["rejected"] += len(rejected)

            result["pages"].append(entry)

        # Document-level structure: >=80% of pages without a text layer = scanned.
        if per_page_chars and no_text_pages / max(1, len(per_page_chars)) >= 0.8:
            result["structure"] = "scanned"
            result["flags"].append("needs_ocr")
        elif any(c >= TEXT_MIN_CHARS for c in per_page_chars):
            result["structure"] = "wrapped"
        else:
            result["structure"] = "unknown"
    finally:
        doc.close()

    return result


def info_has_images(page):
    try:
        return len(page.get_images(full=True)) > 0
    except Exception:
        return False


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main(argv):
    parser = argparse.ArgumentParser(
        description="Extract a textbook PDF into lesson JSON + PNGs (PyMuPDF).",
    )
    parser.add_argument("--in", dest="job", required=True, help="job JSON file")
    parser.add_argument("--out", dest="out", required=True, help="result JSON file to write")
    parser.add_argument("--ocr-engine", choices=["tesseract"], default=None,
                        help="NOT AVAILABLE: no OCR engine is installed; this exits 3.")
    args = parser.parse_args(argv)

    if args.ocr_engine:
        _log("error: --ocr-engine is unavailable in this build (no binary installed)")
        return 3

    with open(args.job, "r", encoding="utf-8") as handle:
        job = json.load(handle)

    page_dpi = int(job.get("page_dpi") or 150)
    figure_dpi = int(job.get("figure_dpi") or 200)
    max_pages = int(job.get("max_pages") or 0)

    out = {"pdfs": [], "ocr": "unavailable"}
    failures = 0

    for entry in job.get("pdfs", []):
        pdf_path = entry["path"]
        try:
            extracted = extract_pdf(
                pdf_path,
                entry["pages_dir"],
                entry["figures_dir"],
                page_dpi,
                figure_dpi,
                max_pages,
            )
        except Exception as exc:  # noqa: BLE001 - one PDF failing must not fail the batch
            failures += 1
            extracted = {"pages": [], "figures": [], "structure": "unknown", "page_count": 0,
                         "flags": [], "figure_stats": {"accepted": 0, "rejected": 0},
                         "error": "%s: %s" % (type(exc).__name__, exc)}
            _log("pdf %s failed: %s" % (entry.get("key", "?"), exc))
            _log(traceback.format_exc().splitlines()[-1])
        extracted["key"] = entry.get("key")
        out["pdfs"].append(extracted)
        _log("pdf %s: %d pages, %d figures, structure=%s" % (
            entry.get("key", "?"), len(extracted.get("pages", [])),
            len(extracted.get("figures", [])), extracted.get("structure")))

    with open(args.out, "w", encoding="utf-8") as handle:
        json.dump(out, handle, ensure_ascii=False)

    return 2 if failures else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

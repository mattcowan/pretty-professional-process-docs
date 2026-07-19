#!/usr/bin/env python3
"""Migrate PPPD section content from raw HTML to core Gutenberg block markup.

Dry-run first, always:

    python3 tools/migrate-to-blocks.py --reports 46,47                # dry-run (default)
    python3 tools/migrate-to-blocks.py --reports 46,47 --apply --backup tools/backups/<file>.json
    python3 tools/migrate-to-blocks.py --rollback tools/backups/<file>.json
    python3 tools/migrate-to-blocks.py --reports 46,47 --census      # element census only

Behavior:
  * Requires the admin credentials (PPPD_ADMIN_USER / PPPD_ADMIN_APP_PASS) from
    pppd-agent.env — published sections reject updates from the agent role
    (403 pppd_approval_required).
  * Every run writes a timestamped JSON backup of all target sections' raw
    content to tools/backups/ (gitignored). --apply refuses to run unless the
    given backup's hashes match the live content.
  * Sections whose signoff.state is "approved" are skipped and reported.
  * Updates send {"content": ...} ONLY — meta, menu_order, and parent are
    never in the payload.

Conversion rules (fidelity over cleverness):
  * <p> (no attrs)            -> wp:paragraph
  * <h2>..<h6> (id attr ok)   -> wp:heading (class="wp-block-heading" added)
  * <ul>/<ol> (no attrs)      -> wp:list + nested wp:list-item blocks
  * <pre><code>               -> wp:code   (class="wp-block-code" added)
  * <table>, and anything else (attrs, unknown tags, loose text with markup)
                              -> wp:html   (byte-preserving)
    Tables stay wp:html deliberately: the core table block cannot represent
    <caption> or scoped row headers, which this plugin's accessibility
    contract requires.
  * Loose top-level text      -> wp:paragraph (wpautop would have wrapped it)
"""

import argparse
import base64
import difflib
import hashlib
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path

PLUGIN_DIR = Path(__file__).resolve().parent.parent
# plugin -> plugins -> wp-content -> <webroot> -> <site>/app; env sits beside the webroot.
DEFAULT_ENV = PLUGIN_DIR.parents[3] / "pppd-agent.env"
BACKUP_DIR = PLUGIN_DIR / "tools" / "backups"

VOID_TAGS = {"br", "hr", "img", "input", "meta", "link", "area", "base", "col", "embed", "source", "track", "wbr"}
ALL_STATUSES = "publish,draft,pending,future,private"


def read_env(path):
    env = {}
    for line in Path(path).read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            key, value = line.split("=", 1)  # app passwords contain spaces — never word-split
            env[key] = value
    return env


class Api:
    def __init__(self, base_url, user, password):
        self.base = base_url.rstrip("/")
        self.auth = base64.b64encode(f"{user}:{password}".encode()).decode()

    def request(self, method, path, body=None):
        url = self.base + "/wp-json" + path
        data = None
        headers = {"Authorization": "Basic " + self.auth}
        if body is not None:
            data = json.dumps(body).encode("utf-8")  # UTF-8 payload, never shell-inlined
            headers["Content-Type"] = "application/json; charset=utf-8"
        req = urllib.request.Request(url, data=data, headers=headers, method=method)
        try:
            with urllib.request.urlopen(req) as resp:
                return json.load(resp)
        except urllib.error.HTTPError as err:
            detail = err.read().decode("utf-8", "replace")[:400]
            raise SystemExit(f"HTTP {err.code} on {method} {path}: {detail}") from err

    def get(self, path):
        return self.request("GET", path)

    def post(self, path, body):
        return self.request("POST", path, body)


# ---------------------------------------------------------------------------
# Top-level element splitting (raw source slices, so fallbacks are byte-exact)
# ---------------------------------------------------------------------------

class TopLevelSplitter(HTMLParser):
    """Split raw HTML into top-level chunks: (kind, tag, start_tag_text, outer, inner)."""

    def __init__(self, raw):
        super().__init__(convert_charrefs=False)
        self.raw = raw
        self.line_offsets = [0]
        for match in re.finditer(r"\n", raw):
            self.line_offsets.append(match.end())
        self.depth = 0
        self.chunks = []
        self.open_start = None
        self.open_tag = None
        self.open_start_tag_text = None
        self.inner_start = None
        self.last_end = 0

    def raw_offset(self):
        # NB: named raw_offset — HTMLParser itself keeps an int in self.offset.
        line, col = self.getpos()
        return self.line_offsets[line - 1] + col

    def flush_text(self, upto):
        text = self.raw[self.last_end:upto]
        if text.strip():
            self.chunks.append(("text", None, None, text, None))
        self.last_end = upto

    def handle_starttag(self, tag, attrs):
        if tag in VOID_TAGS:
            return
        if self.depth == 0:
            start = self.raw_offset()
            self.flush_text(start)
            self.open_start = start
            self.open_tag = tag
            self.open_start_tag_text = self.get_starttag_text()
            self.inner_start = start + len(self.get_starttag_text())
        self.depth += 1

    def handle_startendtag(self, tag, attrs):
        if self.depth == 0:
            start = self.raw_offset()
            self.flush_text(start)
            end = start + len(self.get_starttag_text())
            self.chunks.append(("element", tag, self.get_starttag_text(), self.raw[start:end], ""))
            self.last_end = end

    def handle_endtag(self, tag):
        if tag in VOID_TAGS:
            return
        self.depth = max(0, self.depth - 1)
        if self.depth == 0 and self.open_tag is not None:
            end_tag_start = self.raw_offset()
            end = self.raw.find(">", end_tag_start) + 1
            self.chunks.append((
                "element",
                self.open_tag,
                self.open_start_tag_text,
                self.raw[self.open_start:end],
                self.raw[self.inner_start:end_tag_start],
            ))
            self.last_end = end
            self.open_tag = None

    def close(self):
        super().close()
        if self.open_tag is not None:
            # Unclosed element — treat the rest as one raw chunk.
            self.chunks.append(("text", None, None, self.raw[self.open_start:], None))
            self.last_end = len(self.raw)
            self.open_tag = None
        self.flush_text(len(self.raw))


def split_top_level(raw):
    parser = TopLevelSplitter(raw)
    parser.feed(raw)
    parser.close()
    return parser.chunks


class _StartTagAttrs(HTMLParser):
    """Capture the attribute list of the first start tag fed in."""

    def __init__(self):
        super().__init__()
        self.found = None

    def handle_starttag(self, tag, attrs):
        if self.found is None:
            self.found = attrs


def tag_attrs(start_tag_text):
    """Attributes present in a raw start tag (name -> value; '' for bare attrs).

    Parsed with HTMLParser rather than a regex so nothing slips through: names
    with digits/underscores/colons/dots (data-foo1, aria-describedby, xml:lang)
    and valueless boolean attributes (hidden) all count. Missing an attribute
    here would let the converter rebuild the tag without it.
    """
    parser = _StartTagAttrs()
    parser.feed(start_tag_text)
    parser.close()

    if not parser.found:
        return {}

    return {name.lower(): ("" if value is None else value) for name, value in parser.found}


# ---------------------------------------------------------------------------
# Conversion
# ---------------------------------------------------------------------------

def wp_html(outer, stats):
    stats["wp:html"] += 1
    return f"<!-- wp:html -->\n{outer}\n<!-- /wp:html -->"


def convert_list(outer, stats):
    """<ul>/<ol> -> wp:list with wp:list-item inner blocks (recursive)."""
    chunks = split_top_level(outer)
    if len(chunks) != 1 or chunks[0][0] != "element":
        return None
    _, tag, start_text, _, inner = chunks[0]
    if tag_attrs(start_text):
        return None
    ordered = tag == "ol"

    items = []
    for kind, li_tag, li_start, li_outer, li_inner in split_top_level(inner):
        if kind == "text":
            if li_outer.strip():
                return None  # stray content between <li>s — punt to wp:html
            continue
        if li_tag != "li" or tag_attrs(li_start):
            return None
        items.append(li_inner)

    rendered_items = []
    for li_inner in items:
        # Nested lists live INSIDE the <li> as inner blocks per core serialization.
        parts = []
        nested_blocks = []
        for kind, sub_tag, sub_start, sub_outer, sub_inner in split_top_level(li_inner):
            if kind == "element" and sub_tag in ("ul", "ol"):
                nested = convert_list(sub_outer, stats)
                if nested is None:
                    return None
                nested_blocks.append(nested)
            else:
                parts.append(sub_outer)
        text = "".join(parts).strip()
        body = text + "".join(nested_blocks)
        rendered_items.append(f"<!-- wp:list-item -->\n<li>{body}</li>\n<!-- /wp:list-item -->")

    list_tag = "ol" if ordered else "ul"
    attrs_json = ' {"ordered":true}' if ordered else ""
    stats["wp:list"] += 1
    stats["wp:list-item"] += len(rendered_items)
    joined = "\n\n".join(rendered_items)
    return (
        f"<!-- wp:list{attrs_json} -->\n<{list_tag} class=\"wp-block-list\">{joined}</{list_tag}>\n<!-- /wp:list -->"
    )


def convert_chunk(kind, tag, start_text, outer, inner, stats):
    if kind == "text":
        # Any '<' at all (tags, comments like <!-- marker -->, doctypes, even a
        # stray unescaped less-than) keeps the RAW slice — including its
        # leading/trailing whitespace — so the wp:html fallback stays
        # byte-preserving. Only pure prose becomes a paragraph.
        if "<" in outer:
            return wp_html(outer, stats)
        # Bare prose only: wpautop would have wrapped it; trimming here is the
        # deliberate wpautop-equivalent, not a fidelity loss.
        stats["wp:paragraph"] += 1
        return f"<!-- wp:paragraph -->\n<p>{outer.strip()}</p>\n<!-- /wp:paragraph -->"

    attrs = tag_attrs(start_text)

    if tag == "p" and not attrs:
        stats["wp:paragraph"] += 1
        return f"<!-- wp:paragraph -->\n<p>{inner}</p>\n<!-- /wp:paragraph -->"

    if tag in ("h2", "h3", "h4", "h5", "h6") and set(attrs) <= {"id"}:
        level = int(tag[1])
        json_attrs = "" if level == 2 else f' {{"level":{level}}}'
        id_attr = f' id="{attrs["id"]}"' if "id" in attrs else ""
        stats["wp:heading"] += 1
        return (
            f"<!-- wp:heading{json_attrs} -->\n"
            f"<{tag}{id_attr} class=\"wp-block-heading\">{inner}</{tag}>\n"
            f"<!-- /wp:heading -->"
        )

    if tag in ("ul", "ol"):
        converted = convert_list(outer, stats)
        if converted is not None:
            return converted
        return wp_html(outer, stats)

    if tag == "pre" and not attrs:
        code_chunks = split_top_level(inner)
        if (
            len(code_chunks) == 1
            and code_chunks[0][0] == "element"
            and code_chunks[0][1] == "code"
            and not tag_attrs(code_chunks[0][2])
        ):
            stats["wp:code"] += 1
            return (
                f"<!-- wp:code -->\n"
                f"<pre class=\"wp-block-code\"><code>{code_chunks[0][4]}</code></pre>\n"
                f"<!-- /wp:code -->"
            )
        return wp_html(outer, stats)

    # Tables (caption/scope cannot round-trip through core/table), attributed
    # elements, and anything unrecognized: byte-preserving HTML block.
    return wp_html(outer, stats)


def convert_content(raw, stats):
    blocks = []
    for chunk in split_top_level(raw):
        blocks.append(convert_chunk(*chunk, stats))
    return "\n\n".join(blocks)


def census(raw):
    counts = {}
    for kind, tag, start_text, outer, inner in split_top_level(raw):
        key = tag if kind == "element" else "(text)"
        if kind == "element" and tag_attrs(start_text):
            key += "[attrs:" + ",".join(sorted(tag_attrs(start_text))) + "]"
        counts[key] = counts.get(key, 0) + 1
    return counts


# ---------------------------------------------------------------------------
# Pipeline
# ---------------------------------------------------------------------------

def collect_sections(api, report_ids, allow_minting):
    """Outline rows + raw content for every non-skipped section."""
    sections = []
    skipped = []
    for report_id in report_ids:
        outline = api.get(f"/pppd/v1/reports/{report_id}/outline?status={ALL_STATUSES}")
        for row in outline["sections"]:
            if row.get("signoff", {}).get("state") == "approved":
                skipped.append((row["id"], row["title"], "signoff approved — never touched"))
                continue
            sections.append((report_id, row))

    minting_risk = [
        row for _, row in sections
        if row.get("type") == "requirement" and row.get("post_status") == "publish" and not row.get("req_id")
    ]
    if minting_risk and not allow_minting:
        ids = ", ".join(str(r["id"]) for r in minting_risk)
        raise SystemExit(
            f"ABORT: published requirement sections without a req_id would have IDs minted on update: {ids}. "
            f"Re-run with --allow-minting to accept that."
        )

    # One paged collection fetch beats 48 sequential per-section GETs.
    posts_by_id = {}
    page = 1
    while True:
        batch = api.get(
            f"/wp/v2/pppd-sections?context=edit&status={ALL_STATUSES}&per_page=100&page={page}"
        )
        for post in batch:
            posts_by_id[post["id"]] = post
        if len(batch) < 100:
            break
        page += 1

    detailed = []
    for report_id, row in sections:
        post = posts_by_id.get(row["id"]) or api.get(f"/wp/v2/pppd-sections/{row['id']}?context=edit")
        detailed.append({
            "section_id": row["id"],
            "report_id": report_id,
            "title": row["title"],
            "req_id": row.get("req_id", ""),
            "modified_gmt": post["modified_gmt"],
            "content_raw": post["content"]["raw"],
        })
    return detailed, skipped


def sha(text):
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


def write_backup(detailed, site):
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    path = BACKUP_DIR / f"pppd-content-backup-{stamp}.json"
    payload = {
        "generated_at_utc": stamp,
        "site": site,
        "sections": [dict(s, content_sha256=sha(s["content_raw"])) for s in detailed],
    }
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=1), encoding="utf-8")
    return path


def check_backup_matches(backup_path, detailed):
    backup = json.loads(Path(backup_path).read_text(encoding="utf-8"))
    by_id = {s["section_id"]: s for s in backup["sections"]}
    mismatches = []
    for section in detailed:
        saved = by_id.get(section["section_id"])
        if saved is None or saved["content_sha256"] != sha(section["content_raw"]):
            mismatches.append(section["section_id"])
    if mismatches:
        raise SystemExit(
            f"ABORT: backup {backup_path} does not match live content for sections {mismatches}. "
            f"Re-run the dry-run to produce a fresh backup."
        )


def outline_fingerprint(api, report_ids):
    """req_id + signoff state per section — must be identical before/after."""
    finger = {}
    for report_id in report_ids:
        outline = api.get(f"/pppd/v1/reports/{report_id}/outline?status={ALL_STATUSES}")
        for row in outline["sections"]:
            finger[row["id"]] = (row.get("req_id", ""), row.get("signoff", {}).get("state"))
    return finger


def revision_count(api, section_id):
    revs = api.get(f"/wp/v2/pppd-sections/{section_id}/revisions?per_page=100")
    return len(revs)


def run(argv=None):
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--reports", default="", help="Comma-separated report IDs (e.g. 46,47)")
    parser.add_argument("--apply", action="store_true", help="Write converted content (requires --backup)")
    parser.add_argument("--backup", default="", help="Backup JSON (from a prior dry-run) hashes must match live content")
    parser.add_argument("--rollback", default="", help="Restore content from this backup JSON")
    parser.add_argument("--census", action="store_true", help="Print element census only")
    parser.add_argument("--sections", default="", help="Limit to these section IDs (comma-separated)")
    parser.add_argument("--allow-minting", action="store_true")
    parser.add_argument("--env", default=str(DEFAULT_ENV), help="Path to pppd-agent.env")
    args = parser.parse_args(argv)

    env = read_env(args.env)
    for key in ("PPPD_URL", "PPPD_ADMIN_USER", "PPPD_ADMIN_APP_PASS"):
        if key not in env:
            raise SystemExit(
                f"Missing {key} in {args.env}. The migration must run with admin credentials: "
                f"published sections return 403 pppd_approval_required for the agent role."
            )
    api = Api(env["PPPD_URL"], env["PPPD_ADMIN_USER"], env["PPPD_ADMIN_APP_PASS"])

    me = api.get("/wp/v2/users/me?context=edit")
    if not me.get("capabilities", {}).get("pppd_approve_changes"):
        raise SystemExit(
            f"User '{me.get('slug')}' lacks pppd_approve_changes — updates to published sections would 403. "
            f"Use the admin application password."
        )

    if args.rollback:
        backup = json.loads(Path(args.rollback).read_text(encoding="utf-8"))
        finger_before = None
        restored = 0
        for section in backup["sections"]:
            live = api.get(f"/pppd/v1/reports/{section['report_id']}/outline?status={ALL_STATUSES}")
            row = next((r for r in live["sections"] if r["id"] == section["section_id"]), None)
            if row and row.get("signoff", {}).get("state") == "approved":
                print(f"SKIP {section['section_id']} ({section['title']}): signoff approved")
                continue
            api.post(f"/wp/v2/pppd-sections/{section['section_id']}", {"content": section["content_raw"]})
            restored += 1
            print(f"restored {section['section_id']} ({section['title']})")
        print(f"\nRollback complete: {restored}/{len(backup['sections'])} sections restored from {args.rollback}")
        return

    if not args.reports:
        raise SystemExit("--reports is required (e.g. --reports 46,47)")
    report_ids = [int(r) for r in args.reports.split(",") if r.strip()]

    detailed, skipped = collect_sections(api, report_ids, args.allow_minting)
    if args.sections:
        wanted = {int(s) for s in args.sections.split(",")}
        detailed = [s for s in detailed if s["section_id"] in wanted]

    for section_id, title, reason in skipped:
        print(f"SKIP {section_id} ({title}): {reason}")

    if args.census:
        totals = {}
        for section in detailed:
            for key, count in census(section["content_raw"]).items():
                totals[key] = totals.get(key, 0) + count
        print(f"\nElement census across {len(detailed)} sections:")
        for key in sorted(totals):
            print(f"  {key:40s} {totals[key]}")
        return

    backup_path = write_backup(detailed, env["PPPD_URL"])
    print(f"Backup written: {backup_path} ({len(detailed)} sections)\n")

    stats = {"wp:paragraph": 0, "wp:heading": 0, "wp:list": 0, "wp:list-item": 0, "wp:code": 0, "wp:html": 0}
    conversions = []
    already_blocks = []
    for section in detailed:
        raw = section["content_raw"]
        if "<!-- wp:" in raw:
            already_blocks.append(section["section_id"])
            continue
        converted = convert_content(raw, stats)
        conversions.append((section, converted))

    if already_blocks:
        print(f"Already block markup, untouched: {already_blocks}\n")

    if not args.apply:
        for section, converted in conversions:
            diff = difflib.unified_diff(
                section["content_raw"].splitlines(keepends=True),
                converted.splitlines(keepends=True),
                fromfile=f"section-{section['section_id']}.before",
                tofile=f"section-{section['section_id']}.after",
            )
            print(f"=== {section['section_id']}: {section['title']} "
                  f"({section['req_id'] or 'no req id'}) ===")
            sys.stdout.write("".join(diff))
            print()
        print(f"\nDRY RUN — nothing written. {len(conversions)} sections would convert.")
        print(f"Block totals: {stats}")
        print(f"To apply: --apply --backup {backup_path}")
        return

    if not args.backup:
        raise SystemExit("--apply requires --backup <file> from a reviewed dry-run.")
    check_backup_matches(args.backup, detailed)

    finger_before = outline_fingerprint(api, report_ids)

    applied = 0
    server_rewrites = []
    for section, converted in conversions:
        revs_before = revision_count(api, section["section_id"])
        api.post(f"/wp/v2/pppd-sections/{section['section_id']}", {"content": converted})
        stored = api.get(f"/wp/v2/pppd-sections/{section['section_id']}?context=edit")["content"]["raw"]
        if stored != converted:
            server_rewrites.append(section["section_id"])
        revs_after = revision_count(api, section["section_id"])
        if revs_after <= revs_before:
            print(f"  WARNING: no new revision recorded for {section['section_id']}")
        applied += 1
        print(f"applied {section['section_id']} ({section['title']})")

    finger_after = outline_fingerprint(api, report_ids)
    drifted = {
        sid: (finger_before.get(sid), finger_after.get(sid))
        for sid in finger_before
        if finger_before.get(sid) != finger_after.get(sid)
    }

    print(f"\nApplied {applied}/{len(conversions)} sections. Block totals: {stats}")
    if server_rewrites:
        print(f"Server adjusted content on save (content-quality filters; expected for empty-th fixes): {server_rewrites}")
    if drifted:
        print(f"!! req_id/signoff CHANGED (should never happen): {drifted}")
        sys.exit(1)
    print("req_id + signoff fingerprints identical before/after. Rollback available via "
          f"--rollback {args.backup}")


if __name__ == "__main__":
    run()

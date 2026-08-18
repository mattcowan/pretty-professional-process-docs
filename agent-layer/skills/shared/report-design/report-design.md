# Report Design Language

The shared contract for every generated report (audit reports, FRD exports, and
the PPPD plugin front-end). Any skill or tool that renders a report consumes
this file plus `report.css` / `print.css` / `export-pdf.mjs` from this
directory. `sample.html` is the canonical example and test fixture; for
maintainability it links the local `report.css` / `print.css` rather than
inlining them. The self-contained rule below governs generated *deliverables*,
not this fixture.

Goals, in order: WCAG 2.1 AA, print/PDF parity, zero build step, client-agnostic.

## Document structure

- One `<h1>` per document. Heading levels never skip (h1 → h2 → h3).
- Landmarks: `<header>`, `<nav aria-label="Contents">` (the TOC), `<main id="main">`,
  `<footer>`. Exactly one `<main>`.
- First focusable element is a skip link: `<a class="skip-link" href="#main">Skip to content</a>`.
- Every report section is a `<section aria-labelledby="{heading-id}">` with a
  stable, human-readable `id` (used for TOC links, comments, deep links).
- `<html lang="en">`, `<title>` = report title, `<meta name="viewport" content="width=device-width, initial-scale=1">`.
- Self-contained (generated deliverables): CSS inlined in `<style>` (screen + print), no external requests,
  no JavaScript required to read the document. JS, when present, is progressive
  enhancement only.

## Table of contents

- `<nav aria-label="Contents">` containing a single `<ol>` (nested `<ol>` for
  subsections) of same-page links.
- Wide screens: sidebar column. Narrow screens and print: in-flow after the header.

## Status vocabulary

Fixed set, shared with the PPPD plugin taxonomy:

| Status | Symbol | Meaning |
|---|---|---|
| Draft | ◌ | Written, not yet reviewed |
| Agreed | ● | Reviewed and accepted by both sides |
| At risk | ▲ | Expectation mismatch / open question / friction flag |
| Built | ■ | Implemented, not yet verified |
| Verified | ✔ | Implemented and verified against acceptance criteria |

Status is always conveyed as **text + symbol + shape** (a bordered badge) —
never color alone. Badge markup:

```html
<span class="badge badge--at-risk"><span aria-hidden="true">▲</span> At risk</span>
```

## Color tokens (all AA-checked on white)

| Token | Value | Contrast on #fff | Use |
|---|---|---|---|
| `--ink` | `#1f2328` | 15.80:1 | Body text |
| `--ink-soft` | `#59626b` | 6.21:1 | Secondary text, captions |
| `--link` | `#0b57d0` | 6.39:1 | Links (underlined, always) |
| `--rule` | `#d0d7de` | 1.45:1 (non-text) | Borders, rules — decorative dividers only |
| `--wash` | `#f6f8fa` | 1.06:1 (background) | Section tint backgrounds |
| `--ok` | `#0f6f2f` | 6.30:1 | Verified badge text/border |
| `--info` | `#0b57d0` | 6.39:1 | Agreed badge |
| `--warn` | `#8a4600` | 7.10:1 | At-risk badge |
| `--built` | `#5a3ea6` | 7.85:1 | Built badge |
| `--muted` | `#59626b` | 6.21:1 | Draft badge |

Every text token clears 4.5:1 (AA normal text); `--ink`, `--warn`, and `--built`
also clear 7:1 (AAA). Body text on `--wash` backgrounds still clears 4.5:1.

**Never introduce a color without recording its measured contrast here, and
measure it — don't estimate.** Ratios are computed with the WCAG 2.x
relative-luminance formula against `#ffffff`; sRGB channels are linearised
(`c/12.92` below 0.04045, else `((c+0.055)/1.055)^2.4`), weighted
`0.2126R + 0.7152G + 0.0722B`, and the ratio is `(L_lighter + 0.05) /
(L_darker + 0.05)`. Sanity-check any implementation against two known values:
`#000000` on white is exactly 21.00:1, and `#767676` on white is 4.54:1 — the
canonical "just passes AA" grey.

## Tables

- Real `<table>` with `<caption>`, `<thead>`, `<th scope="col">` /
  `<th scope="row">`. No layout tables, no divs pretending.
- Wide tables wrap in `<div class="table-scroll" role="region" aria-label="{caption}" tabindex="0">`
  so keyboard users can scroll them; the page body never scrolls horizontally.
- Any table offered as CSV keeps identical column headers in both formats.

## Callouts and internal-only content

Two callout variants exist in `report.css`, and no others:

| Class | Use |
|---|---|
| `.callout--risk` | An unresolved risk or open question. |
| `.callout--evidence` | A citation, a source quote, or supporting detail. |

Each is a `<div class="callout callout--{variant}">` wrapping block content, and
each opens with a `<strong>` label naming what it is. The label is the meaning
— the border and tint are decoration, so a callout must read correctly with CSS
off.

**Internal-only blocks.** Some content renders for the team but never for the
client — implementation notes are the standing example (`_pppd_impl_notes`;
the older client-facing `_pppd_dev_prompt` was retired in plugin 0.3.0 and must
not be used). Mark these with `pppd-internal-only` alongside the callout
classes, and state the visibility in the label itself:

```html
<div class="callout callout--evidence pppd-internal-only">
  <p><strong>Implementation notes (internal — not shown to clients)</strong></p>
  <pre tabindex="0" role="region" aria-label="Implementation notes"><code>…</code></pre>
</div>
```

Two things this depends on, both deliberate:

- **The visibility is carried by the label text, not by the class.**
  `pppd-internal-only` is currently a semantic hook with no styling — a team
  viewer sees an ordinary callout. The words are what tell them it is internal,
  which also means it survives CSS being off, a print stylesheet, and a
  screen reader. Never rely on a visual treatment to signal "internal".
- **Gating is the server's job, not the stylesheet's.** The block must not be
  emitted at all for a client — see `templates/partials/requirement-meta.php`,
  which reads the meta only when `pppd_is_team_viewer()`. A CSS-hidden internal
  block is still in the HTML, and still in the exported PDF.

Long `<pre>` blocks scroll horizontally, so they take `tabindex="0"` plus a
`role="region"` and an accessible name — keyboard users must be able to reach
and scroll them (WCAG 2.1.1).

## Interaction rules (screen only)

- `:focus-visible` outline: 2px solid `--link`, 2px offset. Never `outline: none`.
- Touch targets ≥ 44×44 CSS px for real controls (print/PDF buttons, uploads).
- `prefers-reduced-motion: reduce` disables all transitions/animations and
  smooth scrolling.
- Links are underlined; color is never the only affordance.
- Form-control boundaries (inputs, textareas) use `--ink-soft` or darker —
  WCAG 1.4.11 needs ≥ 3:1 for the field's extent; `--rule` (1.45:1) is for
  decorative dividers only, never form borders.

## Print / PDF rules (see print.css)

- `@page` margin 20mm; A4-friendly (also fine on US Letter).
- `.no-print` hides interactive chrome (buttons, upload slots, comment forms).
- `h2/h3` avoid page-break after; sections, tables, figures, badges avoid
  break-inside where reasonable.
- External link URLs are printed after the link text via `::after`.
- Black-on-white text; wash backgrounds drop to white with borders retained.

## PDF export recipe

Semantic HTML is the accessibility source; the PDF inherits it as tags.

1. Render/save the final self-contained HTML.
2. `node export-pdf.mjs <input.html|url> <output.pdf> [--login user:apppass]`
   — Chromium `page.pdf({ tagged: true, outline: true })`.
3. The script fails loudly unless the output contains `/StructTreeRoot` and
   `/Marked true` (tag structure present) **and** the page it rendered was
   actually a report. Chromium tags any HTML it renders, so the tag check
   alone would pass on a login screen or a 404 — a "successful" accessible
   PDF of an error page. Exit codes: `4` unreachable/bad status, `5` login
   page, `6` not a report, `7` refused to send credentials insecurely.
4. **Credentials and transport.** `--login` puts a WordPress application
   password in the `Authorization` header of every request. TLS verification
   stays ON for anything that isn't a local dev host (`localhost`, `127.x`,
   `::1`, `*.local`, `*.test`, `*.localhost`), and credentials are refused
   over plain `http` to a remote host. `--insecure` overrides both and prints
   a warning — use it only when you own the risk. Never disable verification
   globally to "make it work".
4. Acceptance bar: screen-reader-navigable tagged PDF (headings, reading order,
   alt text, table structure). Not certified PDF/UA.

### The "Download PDF" control must download the tagged file — never print

`window.print()` is NOT an accessible-PDF path: the browser's print-to-PDF is
not guaranteed to emit a tag tree, so a screen-reader user gets an untagged,
non-navigable document — the opposite of the point.

- **Primary action**: a real download link to the exporter's tagged PDF
  (`<a class="btn" href="report.pdf" download>Download accessible PDF</a>`).
  Generate that sibling PDF with step 2 above.
- **Fallback only**: if no tagged PDF exists yet, you may offer a
  `window.print()` button, but label it honestly ("Print / Save as PDF") —
  never call it "Download PDF" or imply accessibility it can't deliver.
- Standalone generated reports link to their sibling `*.pdf`. The PPPD plugin
  front-end links to the report's stored `_pppd_pdf_url` when set, and shows
  the honest print fallback otherwise.

Anything visual with meaning needs a text equivalent (`alt`, or `figcaption` +
`aria-hidden` on decorative SVG). Decorative symbols inside badges are
`aria-hidden` because the status name is already text.

## Writing style (applies to generated report prose)

- Short, concrete sentences. As simple as possible, and no simpler.
- Client names may appear in report CONTENT only — never in tooling, code,
  file names, or this design system.
- Every claim in a generated report traces to a source (git, doc, meeting,
  interview answer); mark inference as inference.

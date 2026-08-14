---
name: content-strategy
description: Design an AI-era content strategy for a website or brand and author it as a living content-strategy report in the Pretty Professional Process Docs (PPPD) WordPress plugin. Use when the user runs /content-strategy, mentions a content strategy or content plan, wants audience segmentation, a page-to-audience map, an editorial/content backlog, a portfolio or proof catalog, AI-disclosure or GEO/LLM-retrieval-era content guidance, a competitive/market landscape, a per-audience messaging/positioning matrix, or a channel and outreach plan (owned publishing plus community/earned and partnership/amplification outreach, e.g. LinkedIn, Reddit/community, directory positioning, PR). Subcommands - init (sources to draft strategy + create DRAFT report), interview (gap-driven markdown interview the human answers inline), synthesize (answered interview + sources to full draft sections), export (tagged PDF + HTML).
---

# Content Strategy — an AI-era content plan as a living report

A content strategy is not a one-off deck. It is a living plan for *what a site
says, to whom, and why* — hosted in the Pretty Professional Process Docs (PPPD)
WordPress plugin as an editable, statused report (report type
`content-strategy`, requirement prefix `CS`). Agents propose; humans approve.
The strategy stays reconciled with the site instead of aging into a stale file.

It answers four questions the AI era sharpened: which audiences each page
serves (not "everyone"), what proof demonstrates rather than claims expertise,
how content is shaped to be retrieved and answered by both search engines and
LLMs, and **how the brand gets discovered and discussed off its own site** —
because in the AI era a brand's own pages are the least-trusted layer and
recommendations are won in community/UGC and independent coverage (the
**outreach** capability, per `references/outreach.md`). Section types are
`narrative` (positioning, plans, guidance) and `decision` (a strategic choice
with owner + date).

**Client-agnostic rule:** every project specific — the brand, its URLs, its
people, its niche — arrives as an argument or lives in the report's meta and
content. Never write a client specific into this skill or its references. The
only proper noun allowed is the client/brand name, and only in report content.

**Roles, never names, in generated content.** Refer to people by ROLE — "the
founder", "the practice lead", "the hiring manager we want to reach" — never by
name, in positioning, audience segments, proof entries, decisions, rationale,
and interview docs alike. This holds even for people the user names or has a
personal relationship with; a relationship is the case to guard hardest, not an
exception. The single named subject allowed is the client/brand, in content
only.

**Write discipline:** `init` may CREATE (the report and its sections, all
`status: draft`). Everything after `init` — every synthesized section, every
edit — lands as `status: draft` on a new section, or as a pending `pppd_change`
against an existing one. Never publish. Never PUT/PATCH a published section
directly. **Never touch a section whose outline `signoff.state` is `approved`**
— it is a client-signed record; skip it or file a change knowing approval flips
to "re-approval required". The approve capability is deliberately human-only.

## Connection

Read `../frd/references/rest-api.md` first — it is the frozen PPPD REST v1
contract, shared verbatim with this skill; do not duplicate it here. Auth = a
WordPress application password. Resolve credentials in this order: env vars
`PPPD_URL` / `PPPD_USER` / `PPPD_APP_PASS`; else a `pppd-agent.env` file one
level above the target site's WP webroot; else ask the user. **Never echo
credentials** into output, logs, file names, or report content.

Two contract points this skill leans on: the report type is
`content-strategy` (set the `pppd_report_type` term on the report); the report
prefix is `CS`. Fetch taxonomy term IDs once per session
(`GET wp/v2/pppd_section_type`, `pppd_status`, `pppd_report_type`) — never
hardcode them. `content-strategy` requires plugin ≥ 0.2.0 (check
`pppd_contract_version` on the outline response).

**Status axes + draft visibility:** the contract's "The three status axes"
section (in `../frd/references/rest-api.md`) is the source of truth — WP
`post_status` is not the `pppd_status` workflow term. The outline route
defaults to published sections; verify or enumerate draft work with
`GET pppd/v1/reports/{id}/outline?status=publish,draft` (plugin ≥0.4.0,
team-gated — the agent qualifies). On older plugins fall back to
`GET wp/v2/pppd-sections?status=draft&context=edit` filtered on
`meta._pppd_report_id`. Sections attach to a report via the `_pppd_report_id`
meta; `post_parent` is only for section-tree nesting.

## Subcommands

### `content-strategy init <sources> [--repo <path>] [--url <site-url>] [--granola <folder>] [--title <report title>] [--client <name>]`

Mine what exists; draft an outline; create the DRAFT report.

1. **Mine the sources** (all read-only): read every provided document/note;
   if `--url`, crawl the live site read-only (existing pages, titles, nav,
   what each page currently tries to do); if `--repo`, inventory the codebase
   for content surfaces (post types, templates, existing copy); if `--granola`,
   mine that folder via the granola MCP tools; ingest any prior PPPD reports
   (a companion FRD especially — cross-reference it, do not restate it). If the
   Granola MCP is unavailable, say so and continue with file sources — never
   block on it.
2. **Draft the outline** per `references/cs-outline.md`. Derive audience
   segments and a provisional page-to-audience map using
   `references/audience-segmentation.md`; shape every content and page plan by
   `references/ai-era-guidance.md`. **Run the outreach research** per
   `references/outreach.md` Step 1 — audience, channel, competitive-landscape,
   and prompt-research (ask the LLMs the audience's core questions; record what
   they recommend and cite). This seeds the competitive-landscape, messaging-
   matrix, and channel-&-outreach sections. If web/LLM access is unavailable,
   say so and mark those findings `at-risk` rather than inventing them. Show the
   user the outline; iterate until approved. Flag every "this page tries to
   speak to everyone," every unproven expertise claim, and every unverified
   channel as an open item — do not smooth them over.
3. **Create the report via REST**, all as DRAFT: one `pppd-reports` post (title,
   executive summary as content, `pppd_report_type` = `content-strategy`,
   `pppd_client` if `--client`, `_pppd_project_slug`, `_pppd_req_prefix` = `CS`,
   `_pppd_repo_paths` if `--repo`), then the outline's `pppd-sections` in order
   (`_pppd_report_id`, `menu_order`, `parent`, section type narrative/decision,
   **`pppd_status` = draft**). Full content is written in `synthesize`; `init`
   may create sections as thin drafts (title + one-line intent) or defer them.
4. Print the report URL and an outline summary with each section's status.

### `content-strategy interview <report-id-or-slug>`

Write a gap-driven markdown interview the user answers inline. The file on disk
IS the state — resumable across sessions.

1. GET the outline + sections. Collect gaps: audience segments with no evidence
   of who they are or what they need; pages mapped to "everyone" or to no
   audience; proof/portfolio entries with unresolved disclosure/NDA status;
   expertise claimed but not demonstrated; missing required pages (e.g. an
   AI-disclosure page); a value prop in the messaging matrix with no backing
   proof; channels/outreach targets named with no convention or unverified as
   active; a directory listing with no positioning plan; decisions with no
   owner. Cross-check any companion FRD for contradictions.
2. Write `interview.md` to the reports output directory
   (`...\reports\<project-slug>\<date>-content-strategy\interview.md`),
   following the project-audit interview pattern:
   - Open with the blunt-honesty preamble (see below), then the brand name.
   - **Part A — from the evidence:** 5–12 questions, each citing what raised it
     ("The site has 6 pages but every one pitches the same generic CTA — who is
     each page actually FOR?"). Prefer questions that distinguish competing
     strategies over yes/no confirmations. Every question ends with a
     `> **Answer:**` marker on its own line.
   - **Part B — standard bank** (audience, proof/disclosure, backlog priority,
     required pages, voice, success metric, and **outreach**): the outreach
     questions cover which communities/venues the brand will *actually*
     participate in (capacity is the constraint for a solo maker), disclosure of
     affiliation norms they're comfortable with, partnership/integration targets,
     PR-worthy story angles, and — if the product lives in a directory — the
     review-solicitation and listing-positioning plan. For anything the user
     already answered in this conversation or the sources, do NOT re-ask —
     reproduce it as `> **Answer (previously provided):** …` and add at most one
     follow-up.
3. Stop after writing the file. The user answers inline. Do not invent answers,
   and do not modify the report in this stage.

Preamble to include verbatim:

> **Please be as blunt and honest as possible.** This document is raw input,
> not the strategy. Nothing here is quoted directly; answers are paraphrased
> and de-identified, and anything about a person becomes a statement about a
> role. Candor here is what makes the strategy worth shipping. Fragments are
> fine — speed over polish. If a question misses the real issue, ignore it and
> write what actually matters. Write "n/a" if it doesn't apply.

### `content-strategy synthesize <report-id-or-slug>`

Answered interview + sources → full report sections. Resumable: if
`interview.md` is answered, run this; if it exists unanswered, remind the user
it is waiting.

1. Read the answered `interview.md` plus all `init` evidence and any companion
   FRD.
2. Write the full sections per `references/cs-outline.md`, applying
   `references/audience-segmentation.md` (finalize the page-to-audience map;
   one primary audience + one CTA per page), `references/ai-era-guidance.md`
   (answer-shaped content, retrieval-friendly titles, AI-disclosure page,
   demonstrate-don't-claim proof), and `references/outreach.md` for the
   outreach sections: the **competitive & market landscape** (grounded in the
   Step-1 research + prompt-research findings), the **messaging & positioning
   matrix** (one row per segment; every value prop references a proof entry),
   and the **channel & outreach plan** (three lanes — owned / community-earned /
   partnership-amplification — each entry with lane, target, evidence-backed
   why, format, cadence, norm/etiquette, and a first action; plus directory
   positioning if the product lives in a directory). Portfolio/proof entries
   carry angle + disclosure/NDA status + an invoke-prompt (skill + agent tier +
   effort). Backlog entries carry a brief outline + a starter prompt invoking an
   authoring skill + agent/effort estimate, and include answer pages for the
   prompt-research gaps. Strategic choices are `decision` sections with owner +
   date. Obey the outreach evidence & ethics rules: no astroturfing or
   undisclosed promotion, verify channels are active, roles never names.
3. **Land everything as DRAFT.** For a NEW section: POST `pppd-sections` with
   `pppd_status` = draft. For an EXISTING published or signed-off section: POST
   a `pppd_change` (status pending) with the proposed content,
   `_pppd_target_section`, `_pppd_source` ("interview" or the source name),
   `_pppd_rationale`. Never publish; never write to an `approved` section.
4. Generated content is core block markup per the cheat sheet in
   `../frd/references/rest-api.md` (§ "Section content is core block markup"):
   one idea per element, correct heading hierarchy (no skipped levels), real
   `<ul>`/`<table>` (with `scope`; tables go in `wp:html`), no color-only
   meaning, alt-text guidance noted for any image the plan calls for. Follow
   `../shared/report-design/report-design.md` for anything rendered.
5. Summarize what was drafted, what awaits human review in the Review Queue,
   and link wp-admin.

### `content-strategy export <report-id-or-slug>`

**Publish prerequisite:** the report frontend renders **published sections
only**, and a draft *report* post 404s. Since this skill authors everything as
draft, export produces meaningful output only after the human publishes the
report and sections in wp-admin. Confirm the report resolves (HTTP 200) with a
non-zero published-section count before exporting; if not, tell the user it is
still in draft and stop — never publish to force a render. The draft content can
still be accessibility-checked at the source level (semantic-HTML lint of the
section bodies) before publish.

1. Fetch the rendered report page with the shared exporter (authenticated):
   `node ~/.claude/skills/shared/report-design/export-pdf.mjs <report-url> content-strategy.pdf --login "$PPPD_USER:$PPPD_APP_PASS"`
   — must print `OK: tagged PDF` (it fails loudly unless the PDF carries
   `/StructTreeRoot` + `/Marked true`).
2. Save the self-contained HTML alongside it. Both follow
   `../shared/report-design/report-design.md` (skip link, landmarks, one
   `<main>`, TOC, text+symbol+shape status badges, table structure, print
   rules; `sample.html` is the pattern reference).
3. Deliver to `...\reports\<project-slug>\<YYYY-MM-DD>-content-strategy\`.

## After any subcommand

Report what changed, what awaits human review, and the single most important
open strategic risk (usually: a page still aimed at "everyone", or an expertise
claim with no proof to back it). Confirm any read repo's working tree is
untouched. Before delivering an export, grep the output for every person-name
that appeared in the sources or answers — expect zero hits; a slipped name is a
defect, fix and re-export.

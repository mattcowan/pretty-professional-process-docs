---
name: frd
description: Create and maintain living FRDs (functional requirements documents) hosted in the Pretty Professional Process Docs WordPress plugin. Use when the user runs /frd, mentions an FRD or functional requirements document, wants to generate requirements from notes or meetings, generate stakeholder interview questions, ingest a meeting into a spec, check spec drift against a codebase, or export an FRD to PDF/CSV. Subcommands - init (sources to draft FRD), interview (gap-driven stakeholder questions), ingest-meeting (meeting to proposed changes), drift (code vs spec report), export (tagged PDF + CSV).
---

# FRD — living functional requirements documents

The FRD is a living source of truth, not a signed PDF that rots after the
first client meeting. It lives in the Pretty Professional Process Docs (PPPD)
WordPress plugin as an editable, wiki-style report with revision history.
Agents propose; humans approve. The spec and the code are continuously
reconciled instead of drifting apart silently.

**Client-agnostic rule:** every project specific (names, paths, URLs, Granola
folders) arrives as an argument or lives in the plugin's report meta. Never
write client specifics into this skill or its references. Client names may
appear only inside report content.

**No personal names in report content.** When ingesting meetings or notes,
refer to people by ROLE, never by name — in requirements, decisions, rationale,
proposed-change `_pppd_source`/rationale, and interview docs alike. "The
product owner requested X", not a person's name. This holds even for people the
user names or has a personal relationship with; it keeps the spec reusable and
safe. The only proper noun allowed is the client/project name, in content only.

**Write discipline:** `init` may CREATE (the report and its sections, all
`status: draft`). Drafts remain directly editable by the agent; **publishing is
a human act in wp-admin** — that is when FR IDs are assigned and the approval
lock arms. Once a section is published, every edit goes through the
proposed-changes queue (`pppd_change`, status pending) so a human approves it
in the Review Queue. Never publish sections yourself; never edit published
sections directly.

## Connection

Read `references/rest-api.md` first. Auth = WordPress application password.
Resolve credentials in this order: env vars `PPPD_URL` / `PPPD_USER` /
`PPPD_APP_PASS`; else a `pppd-agent.env` file at or above the WP webroot
(check the site's root folder — e.g. a Local site keeps it two levels up,
beside `app\`); else ask the user. Never echo credentials into output.

**Two status axes + draft visibility:** WP `post_status` (is the section part
of the published document?) is not the `pppd_status` workflow term (the badge)
— `references/rest-api.md` § "The three status axes" is the source of truth.
The outline route defaults to published sections only; enumerate/verify draft
work with `GET pppd/v1/reports/{id}/outline?status=publish,draft` (plugin
≥0.4.0, team-gated — the agent qualifies; rows carry `post_status`). On older
plugins fall back to `GET wp/v2/pppd-sections?status=draft&context=edit`
filtered on `meta._pppd_report_id`. Sections attach to a report via
`_pppd_report_id`; `parent` is only for section-tree nesting.

## Subcommands

### `frd init <source-docs-dir> [--repo <path>] [--granola <folder>] [--title <report title>]`

1. Read every source document; if `--granola`, also mine that Granola folder
   via the granola MCP tools; if `--repo`, inventory the codebase (read-only)
   to seed feature coverage.
2. Draft an outline per `references/frd-outline.md`. Show the user; iterate
   until approved. Flag every detected expectation mismatch per
   `references/friction-detection.md` — these become sections tagged
   `at-risk`, not silently smoothed over.
3. Create via REST, **all as DRAFT**: one `pppd_report` (title, executive
   summary, repo paths, requirement prefix, project slug), then `pppd_section`
   posts in outline order (set `_pppd_report_id`, `menu_order`, `parent`,
   section type, `pppd_status` term; post `status: "draft"`). Section content
   is core block markup per the cheat sheet in `references/rest-api.md`.
   Requirements get acceptance criteria, impl notes, and code/test refs when
   known. The human
   reviews drafts in wp-admin and publishes them there; publishing a
   requirement auto-assigns its stable ID (FR-001…) — never invent IDs
   client-side, and never publish on the human's behalf.
4. Print the report URL and an outline summary with statuses.

### `frd interview <report-id-or-slug> [--stakeholder <role>] [--inline]`

1. GET the outline + sections (an all-draft report needs
   `?status=publish,draft` on the outline call — see the visibility note in
   Connection); collect gaps:
   `at-risk` sections, requirements with no acceptance criteria, decisions
   with no owner, friction flags, contradictions between sections.
2. Generate interview docs per `references/interview-method.md` — grouped per
   stakeholder role, each question citing the section/requirement that raised
   it. **Single-stakeholder mode:** when `--inline` is passed or the
   commissioning user is the only stakeholder, emit ONE `interview.md` the
   user answers inline instead of per-role docs — see "Inline mode" in
   `references/interview-method.md`. The file on disk IS the state,
   resumable across sessions.
3. Write to the reports output directory
   (`...\reports\<project-slug>\<date>-frd-interviews\`). Do not modify the
   report. Stop after writing; do not invent answers.

### `frd ingest-meeting <report-id-or-slug> [--granola <folder> | --doc <path>] [--meeting <title-or-date>]`

1. Pull the meeting (latest in the Granola folder unless `--meeting`), or read
   `--doc`.
2. Diff its content against current sections: changed expectations, new
   requirements, descoped items, decisions made.
3. For each delta, POST a `pppd_change` (status pending) with proposed
   replacement content, `_pppd_target_section`, `_pppd_source` (meeting title
   + date), `_pppd_rationale` (what in the meeting motivated it). New-section
   proposals target the report's designated inbox section (create one titled
   "Proposed additions" via a change if absent).
4. Summarize the queue for the user and link the wp-admin Review Queue. Never
   auto-approve; the approve capability is deliberately human-only.

### `frd drift <report-id-or-slug> <repo-path>`

1. GET the traceability matrix; read the repo READ-ONLY (verify
   `git -C <repo> status --short` is identical before and after).
2. Follow `references/drift-method.md`: verdict per requirement —
   `covered` / `partial` / `missing` — plus `orphan` entries for significant
   built features no requirement describes.
3. POST the run to `/pppd/v1/reports/{id}/drift` (summary + items). Print the
   verdict table and the orphan list.

### `frd export <report-id-or-slug>`

**Publish prerequisite (draft-first consequence):** the report frontend renders
**published sections only**, and a draft *report* post 404s entirely. So export
(and any rendered-page accessibility scan) only produces meaningful output once
the human has published the report and its sections in wp-admin. Before
exporting, confirm the report resolves (HTTP 200) and its published-section count
is non-zero; if it 404s or renders zero sections, tell the user the FRD is still
in draft and stop — do not publish on their behalf to force a render. The
authored *content* can still be accessibility-checked at the source level
(semantic-HTML lint of the section bodies) before publish.

1. Fetch the rendered report page with the shared exporter (authenticated):
   `node ~/.claude/skills/shared/report-design/export-pdf.mjs <report-url> frd-report.pdf --login "$PPPD_USER:$PPPD_APP_PASS"`
   — must print `OK: tagged PDF` and exit 0. The exporter verifies the page it
   rendered before exporting, so exit 4 (bad HTTP status), 5 (landed on a login
   page) and 6 (page isn't a report) all mean **auth or access failed, not that
   the PDF is malformed** — never attach the output or retry with a different
   filename. Fix the credentials or the report's client access, then re-run.
2. Download the traceability CSV from
   `/pppd/v1/reports/{id}/traceability?format=csv`.
3. Deliver to `...\reports\<project-slug>\<YYYY-MM-DD>-frd\`.

## After any subcommand

Report what changed, what awaits human review, and the single most important
open risk. If the Granola MCP is unavailable, say so and continue with file
sources — never block on it.

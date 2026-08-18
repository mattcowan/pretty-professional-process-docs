---
name: pppd-sync
description: Agent layer for the Pretty Professional Process Docs plugin — ingest sources (repo, Granola meetings, Drive/local docs) into draft report content, and push the approved GitHub queue. Use when the user runs /pppd-sync, asks to update a process-docs report from meetings/code/docs, or asks to push approved report items to GitHub. Subcommands - ingest (sources → drafts/proposals), push-github (queue → issues). Everything lands as drafts or pending pppd_change proposals; signed-off sections are never touched.
---

# pppd-sync — ingest & GitHub push for PPPD reports

Two subcommands: `ingest` (sources → draft content / change proposals) and
`push-github` (approved queue → GitHub issues). Parse which one from the
user's request; ask if ambiguous.

## Shared setup

1. **Credentials** — same resolution as the `frd` skill: env `PPPD_URL` /
   `PPPD_USER` / `PPPD_APP_PASS`, else `pppd-agent.env` one level above the WP
   webroot, else ask. Auth is HTTP Basic (application password, `pppd_agent`
   service user). Read `~/.claude/skills/frd/references/rest-api.md` for the
   full REST contract before writing anything.
2. **Orientation** — `GET pppd/v1/reports/{id}/outline` FIRST. Check
   `pppd_contract_version`: absent → plugin predates the sign-off lock, STOP
   and tell the user to update the plugin before any automated write. To see
   draft sections too (e.g. verifying your own ingest), add
   `?status=publish,draft` (plugin ≥0.4.0, team-gated — the agent qualifies;
   rows carry `post_status`). Mind the status axes: `post_status` ≠ the
   `pppd_status` workflow term — see the contract's "three status axes"
   section.
3. **The sign-off lock (hard rule)** — every outline section carries
   `signoff: {state: none|approved|stale, ...}`. A section with
   `signoff.state === "approved"` is a signed legal record:
   - NEVER write to it — no content update, no meta write, no term change.
   - If sources show it needs changing, file a `pppd_change` proposal and tell
     the user re-approval will be required (the plugin flips it to
     "changed since approval" automatically and records provenance).
   - Prefer skipping it entirely unless the user explicitly asked for updates
     to approved content.
4. **Never approve anything.** Approval routes require the human-only
   `pppd_approve_changes` capability; the agent user will get 403s by design.
   Do not retry with admin credentials unless the user explicitly says to act
   as them.

## `ingest` — sources → drafts / proposals

1. **Gather sources** (only what the user points at; confirm scope first):
   - **Repo**: read the target codebase (read-only) for implemented behavior,
     routes, models. Respect the report's `_pppd_repo_paths`.
   - **Granola**: use the Granola MCP tools (`query_granola_meetings`,
     `get_meeting_transcript`) for the meetings the user names or a date
     range they confirm.
   - **Docs**: local files the user supplies, or Google Drive via the Drive
     MCP tools (`search_files`, `read_file_content`).
2. **Map findings to the outline.** For each finding decide:
   - **New content** → create a `pppd_section` as **`status: draft`** (POST
     `wp/v2/pppd-sections` with `_pppd_report_id`, section type term,
     `parent`/`menu_order`). Content is core block markup per the cheat sheet
     in `~/.claude/skills/frd/references/rest-api.md`. Never publish directly
     during ingest — a human reviews drafts in the report's Sections outline
     (wp-admin), and team viewers see drafts flagged on the report page
     itself.
   - **Change to an existing published section** → POST `wp/v2/pppd-changes`
     (`status: pending`, content = full replacement, meta
     `_pppd_target_section`, `_pppd_source` = meeting/doc identifier,
     `_pppd_rationale`). This is the ONLY path for published content,
     approved or not.
   - **Dev context** → write `_pppd_impl_notes` (internal-only), NOT
     `_pppd_dev_prompt` (deprecated client field, retired from the client
     view).
3. **Report back**: a table of created drafts and filed proposals with links,
   plus anything skipped because of the sign-off lock (say so explicitly).

Reuse, don't duplicate: report creation from scratch is the `frd` skill's
`init`; codebase-vs-spec verdicts are its `drift`; project retrospectives are
`project-audit`. This skill's niche is incremental multi-source updates into
an existing report.

## `push-github` — approved queue → issues

The plugin holds no GitHub credential; this skill does the pushing with the
local `gh` CLI (verify `gh auth status` first).

1. `GET pppd/v1/github/queue` (optionally `?report={id}`). Each item carries
   `repo` (owner/name), `title`, `content`, `acceptance[]`, `labels[]`,
   `req_id`, `permalink`, `approved.by/at`.
2. For each item, create the issue:
   - Title: item `title` (already `REQ-ID: Section title`).
   - Body: the section content converted to markdown (the queue serves
     `content` as rendered HTML — block-authored sections arrive without
     `<!-- wp:* -->` comments, plugin ≥0.4.0), an **Acceptance criteria**
     checklist from `acceptance[]`, and a footer line
     `Approved in <permalink>` for traceability.
   - Labels: item `labels[]` (create missing labels only if the user okays it;
     otherwise create the issue without them and note it).
3. **Write back immediately** after each successful creation:
   `POST pppd/v1/github/queue/{section_id}/pushed` with `issue_url` +
   `issue_number`. A 409 means another run already pushed it — do NOT create
   a duplicate issue; report it and move on. Never create an issue for an
   item you cannot mark pushed (verify the write-back succeeded before the
   next item).
4. Summarize: pushed items with issue links, skipped 409s, failures.

## Scheduling — deliberately not wired up

Do not create cron/scheduled runs of this skill unless the user explicitly
asks. If they do: the Phase 3 approval lock (plugin ≥0.3.0, verified) is the
prerequisite and is now in place — confirm `pppd_contract_version` is present
on the target site before scheduling anything.

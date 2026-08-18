---
name: project-audit
description: Run a project retrospective/audit — mine a repo's git history, project notes, and Granola meeting notes, generate a custom interview doc for the user, then synthesize an accessible HTML retrospective report with tagged PDF export. Use when the user runs /project-audit, asks for a project audit, retrospective, post-mortem, lessons-learned report, "what went well" review, or wants to look back at how a project went. Three stages — mine, interview (human answers), synthesize — resumable across sessions.
---

# Project Audit — retrospective reports from project archaeology

Turns a finished (or limping) project into an honest retrospective: what to
keep doing, what to fix, and the process changes worth making before the next
project. The report is built from evidence (git, docs, meetings) combined with
a candid interview of the user — not from vibes.

**Client-agnostic rule:** project and client specifics enter ONLY as inputs
(paths, folder names, interview answers) and may appear ONLY in generated
output. Never write a client name into this skill, its references, or any
tooling. Before delivering, grep the toolchain for the client name — zero hits.

## Inputs

Collect these up front (ask for whichever is missing):

- `repo` — path to the project repository (read-only; never write, stage, or
  commit there; verify `git status` is unchanged after every run)
- `docs` — directory of gathered project notes/documents (read-only)
- `granola` — optional Granola folder name to mine via the granola MCP tools
  (if the MCP is not connected, tell the user to authenticate via /mcp and
  continue without meetings rather than blocking)
- `out` — output directory. If the user has a reports-root convention, follow
  it; otherwise ask once and use
  `<reports-root>/<project-slug>/<YYYY-MM-DD>-audit/`. Never invent a location
  outside the user's stated root.
- Any related-but-separate material the user names (e.g. notes from an
  adjacent failed project) — treat as context, label its origin in the report.

## Stage 1 — mine

Goal: a CUSTOM interview doc whose questions are traceable to real findings.

Analyze, at minimum:

1. **Git history** (read-only): commit count/date range/contributors; cadence
   over time (was the end a rush?); conventional-commit type distribution
   (fix-heavy areas = churn); subsystem churn hotspots; revert/hotfix clusters;
   long-lived branches; who worked where (silo detection); first/last activity
   per contributor (arrivals/departures mid-project).
2. **Docs directory**: what documentation exists vs what's missing (FRD? launch
   plan? QA plan? sitemap?); decision records; anything contradicting the git
   story.
3. **Granola meetings** (if available): recurring topics, repeated unresolved
   items across meetings (a topic appearing ≥3 times unresolved is a finding),
   scope-change moments, tone shifts around deadlines.
4. **Tool sprawl**: count the distinct systems referenced (issue trackers,
   sheets, chat, QA tools) — each is a place decisions can hide.

Then write `interview.md` into `out`, following
`references/interview-template.md`:

- Open with the blunt-honesty preamble (verbatim from the template).
- Part A: findings-driven questions — each cites its evidence ("Commits show X;
  was that Y or Z?"). Only ask what the evidence genuinely raises.
- Part B: the standard question bank in `references/interview-template.md`, all
  sixteen, in order. Each one feeds a named section of
  `references/report-sections.md` — dropping a question means that section has
  no human input, so drop the section too or say so in it. SKIP any question
  the user has already answered in this conversation or in provided material —
  pre-fill those answers instead and ask only for confirmation or follow-up.

Stop after writing `interview.md`. The user answers inline in the file (or in
chat). Do not invent answers.

## Stage 2 — interview (human)

The user fills in answers. Follow-ups are allowed but keep the total burden
small; they said what they said — dig only where an answer contradicts the
evidence or opens something new.

## Stage 3 — synthesize

> **CRITICAL — NEVER name a real person in any report.** Not leadership, not
> owners or founders, not the departed, not subcontractors, not the client's
> staff, and NOT anyone the user named in their answers or said they have a
> personal relationship with. Everyone is their ROLE ("the technical lead",
> "the founder of the firm you came from"). A personal relationship is the case
> to guard *hardest*, not an exception. This applies to the private
> personal-evaluation and career reports too — the ONLY named subject is the
> user, about themselves, because they asked. The single allowed proper noun is
> the client/project name, in report content only. This rule outranks
> completeness: when in doubt, use the role. See `references/style-rules.md`.

1. Read the answered `interview.md` plus all Stage 1 evidence.
2. Write the report per `references/report-sections.md` (structure) and
   `references/style-rules.md` (voice, discretion, evidence rules). These are
   binding.
3. Render as ONE self-contained HTML file (`audit-report.html`): inline
   `../shared/report-design/report.css` + `print.css` into a `<style>` block;
   follow `../shared/report-design/report-design.md` exactly (skip link,
   landmarks, TOC, badges, tables, print rules; `sample.html` is the pattern
   reference).
4. Export the tagged PDF:
   `node ../shared/report-design/export-pdf.mjs audit-report.html audit-report.pdf`
   — must print `OK: tagged PDF`.
5. **Name check (mandatory gate).** Grep the finished HTML for every person-name
   that appeared in the sources and interview answers. Expect ZERO hits. One
   slipped name — even a friend's — is a defect: replace with the role and
   re-export before delivering.
6. Deliver the files in `out`. Confirm the repo working tree is untouched
   (`git -C <repo> status --short` unchanged).

## Optional additional reports (private)

Beyond the shareable retrospective, the user may want candid material that must
NOT travel with it. Produce these as **separate files** (never folded into the
shareable report), each clearly marked "PRIVATE — not for distribution":

- **Personal evaluation** — an honest read of the commissioning user's own work:
  what they did well, where they fell short or held back, growth edges, and
  (explicitly) validation of real strengths. Write it to them, in the second
  person. Be honest, not flattering — include real growth edges — but fair and
  evidence-based.
- **Career notes** — direction advice weighed against the user's own stated
  temperament and constraints (from the interview), with concrete next steps.

Rules for these private reports:

- **Others stay role-level even here — no exceptions.** Assess other people
  only as the ROLE they held ("what the PM seat did poorly / could have done
  better"). This applies to owners, founders, and leadership, and to anyone the
  user names or says they have a personal relationship with — a relationship is
  not a licence to name them; it is the case to guard hardest. The only candid,
  named subject is the user, and only about themselves, and only because they
  asked. Two people in the same role get distinct role labels, never names.
  Grep the finished report for person-names before delivering — expect zero.
  (See `references/style-rules.md` → "Names — roles only, no exceptions".)
- Same design contract, accessibility, and tagged-PDF export as the main report.
- Deliver alongside the retrospective in the same output dir, with a filename
  that signals privacy (e.g. `personal-and-career.html`).

Future enhancement (not yet built): a single audit document with toggleable
private sections and a redaction-aware "share" export. Until then, separate
files are the safe default — see the plugin's `docs/IDEAS.md`.

## Resuming

Each stage is independent. If `interview.md` exists and is answered, jump to
Stage 3. If it exists unanswered, remind the user it's waiting.

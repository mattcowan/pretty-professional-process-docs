# FRD Outline — canonical structure

The living FRD is organized so every part is individually addressable,
statused, and traceable. Sections map 1:1 to `pppd_section` posts. Section
types: `narrative` (context prose), `requirement` (numbered, testable),
`decision` (a choice with owner + date). Section bodies are core block markup
— see the cheat sheet in `rest-api.md` (§ "Section content is core block
markup").

## Top-level outline

1. **Overview** (narrative) — what the system is, for whom, in ≤ 300 words.
2. **Goals & non-goals** (narrative) — explicit non-goals prevent scope creep;
   every non-goal is a scope conversation someone already had.
3. **Stakeholders & roles** (narrative) — who decides, who builds, who
   approves; the escalation path.
4. **Decisions log** (decision children) — one child section per decision:
   what was decided, by whom, when, source (meeting/doc). Undecided = status
   `at-risk`.
5. **Functional requirements** (requirement children, grouped by feature area)
   — the core. See per-requirement format below.
6. **Non-functional requirements** (requirement children) — performance,
   accessibility, security, i18n, SEO, analytics, email deliverability,
   hosting/environments. These are the classically forgotten ones; the outline
   forces the conversation.
7. **Integrations** (requirement/narrative mix) — one child per external
   system: what data flows which way, failure behavior, ownership of accounts.
8. **Content model** (narrative + requirements) — post types, taxonomies,
   fields, migration sources.
9. **Out of scope / deferred** (narrative) — the parking lot, visible to the
   client. Descoped items move here, never silently deleted.
10. **Open questions** (narrative, status `at-risk`) — feeds `frd interview`.
11. **Proposed additions** (inbox for `ingest-meeting` new-section proposals).

## Per-requirement format (post content + meta)

- **Title**: imperative and specific ("User can reset a forgotten password"),
  never a feature label ("Account stuff").
- **Content**: description of exactly what must exist, written so a developer
  who wasn't in the meetings can build it. Include the WHY in one sentence —
  requirements without rationale get "improved" into something else.
- **Meta `_pppd_acceptance`**: 2–6 testable criteria. Each one is a sentence a
  QA person can mark pass/fail without asking anyone.
- **Meta `_pppd_impl_notes`**: internal-only implementation context — a
  ready-to-paste brief for a developer (or coding agent) covering constraints,
  files to look at, and the definition of done. This is the AI-first part: the
  FRD ships with its own build instructions. It never renders on the client
  view. (`_pppd_dev_prompt` is the deprecated, client-facing predecessor — do
  not write to it; see `rest-api.md`.)
- **Meta `_pppd_code_refs` / `_pppd_test_refs`**: repo-relative paths once
  known; empty on a green-field FRD, filled as built; `frd drift` audits them.
- **Status**: draft → agreed (client + dev both signed off) → built →
  verified (acceptance criteria pass). `at-risk` at ANY point means an
  expectation mismatch or open question — it is a flag, not a stage.

## Writing rules

- Follow the shared report design language for anything rendered
  (`~/.claude/skills/shared/report-design/report-design.md`).
- Short sentences. A requirement nobody reads is a requirement nobody honors.
- Mark every inference from reverse-engineering as inference, and give
  reverse-engineered requirements status `built` (they exist) — `verified`
  only with evidence (a passing test, a confirmation).
- Never delete client-visible history: descoped → "Out of scope", superseded
  → revision history via the change queue.

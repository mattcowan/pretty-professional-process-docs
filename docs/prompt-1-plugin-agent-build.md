# Build Prompt 1 — pretty-professional-process-docs (plugin + agent layer)

**Repo:** https://github.com/mattcowan/pretty-professional-process-docs (private)
**Plugin on disk:** `c:\Users\matth\Local Sites\frd-reports\app\public\wp-content\plugins\pretty-professional-process-docs`
**Local env:** http://frd-reports.local  (a Local by Flywheel site)
**Skills (system-level, read before proposing):** `frd`, `project-audit`. Also available and relevant: `accessibility-audit`, `wordpress-accessibility-patterns`, `wordpress-security-patterns`, `create-tests`.
**MCPs:** Granola (meeting notes) and Google Drive are connected. Gmail/Calendar are NOT authorized in headless runs — don't rely on them. Use Granola/Drive in the **agent layer**, not inside plugin PHP.

## Read this first — three layers, never conflate them

- **[PLUGIN]** — PHP/JS in the repo. What WordPress does.
- **[AGENT]** — Claude Code skills + commands that read the repo, Granola, and Drive/local docs and push content to WordPress as drafts. What Claude does on Matt's machine.
- **[CONTENT]** — Actual reports authored *using* the tool. **Out of scope for this prompt** — handled by a separate Prompt 2 after this ships. Do not start content authoring here.

Every task below is tagged with its layer. Do not mix layers in a single change.

## Phase 0 — Confirm & propose. **No code changes. Hard gate.**

The current codebase has already been investigated. Below are established findings — your job is to **verify each against the code, correct me if any is wrong, then return a revised phase plan and confirm the three gating decisions.** Do not re-investigate from scratch, and do not start Phase 1 until I approve.

**Established findings (verify, don't assume):**
1. Reports are a CPT, not a custom table: `pppd_report` (exec summary = its `post_content`) + hierarchical `pppd_section` posts linked by `_pppd_report_id` meta; classified by `pppd_section_type` (narrative/requirement/decision) + `pppd_status` taxonomies; structured data in `_pppd_*` meta. **No storage migration is needed.** BUT the report body is split across separate section posts, so opening a report in Gutenberg shows **only the exec summary** — there is no coherent editing surface for the whole report body today. Confirm this and treat "how the full report body is authored" as an open design question (Decision A), not solved.
2. The 10 empty `<th>` failing AXE are **authored `core/table` block content** in `post_content` (rendered at `templates/single-pppd_report.php:98` and `templates/partials/section.php:26`), NOT plugin templates. The plugin's own tables (`templates/partials/requirement-meta.php`, `drift-summary.php`) are already accessible (scope, caption, populated headers).
3. The client-facing "prompt" is one field: `_pppd_dev_prompt` per-section meta, rendered at `templates/partials/requirement-meta.php:59-64`.
4. There is already an approval spine to build on: `pppd_change` proposals (`includes/change-actions.php`), human-only `pppd_approve_changes` cap, a non-approving `pppd_agent` role, and a REST guard `pppd_guard_published_section_updates()` (`includes/capabilities.php:90-110`) that blocks agents from editing published sections. Plus per-section comments and unlimited revisions.
5. There are **no custom extensibility hooks** today (only core `the_content`). The `frd` skill is coupled to the plugin's REST surface (CPT slugs, `_pppd_*` meta, `pppd/v1` verbs, `pppd_agent` app-password auth); `project-audit` is decoupled.

**Confirm or challenge these three gating decisions:**
- **A — Editing model:** Keep reports as the existing CPT + block storage in wp-admin (admin/PM only) and do NOT build frontend "wiki" editing; client interaction is a *separate read-only frontend view* with approve/comment. **But resolve how the full report body is authored** — today it's fragmented across separate section posts and only the intro shows in the editor. Recommend an approach (e.g. a report-level editing dashboard that lists/links its sections; a unified single-document surface that maps to the section posts; or a per-section-block model) with reasoning. (reqs 3, 6)
- **B — Prompts:** Remove `_pppd_dev_prompt` as client-facing content. Fold any genuine dev context into an **internal-only "implementation notes"** field that never renders on the client view. (reqs 2, 13)
- **C — Document model (the spine):** Introduce a pluggable **report-type registry** (`register_report_type()`) and make **client** the organizing entity. FRD, user-access-model, change-order, and (later) content-strategy all become registered report types. (reqs 4, 5, 7, 8, 11)

Return: (a) a one-line confirm/correction per finding, (b) confirm/challenge per decision, (c) a revised phase plan. **Then stop for my approval.**

## Phase 1 — [PLUGIN] Data-model & extensibility spine

Build this carefully; it is the foundation.
- `register_report_type()` registry — a report type declares its allowed section types, item types, ID scheme, and which fields are internal vs client-facing.
- Pluggable **sections** and **item types** per report type — decision-log items, functional-requirement items, and **data-requirement sections + UI-rule sections** (req 7), plus arbitrary new sections. Each fires an extensibility hook (`do_action`/`apply_filters`) — there are none today, so add the first real seams (req 4).
- **Client** as a first-class organizing entity. Recommend taxonomy vs CPT and justify; a client can be assigned any number of reports of any registered type (req 8).
- **Change orders** modeled as a report type (or sub-type) on the same spine (req 5).
- **Decide the `frd` REST-contract question and recommend with reasoning** (Matt left this to your judgment after you examine the code and the skill): either (a) **preserve** the existing REST surface — CPT slugs, `_pppd_*` meta keys, the two taxonomies, the `pppd/v1` verbs — so the skill keeps working untouched, or (b) do a **clean-break redesign** of the data model and rewrite `C:\Users\matth\.claude\skills\frd\references\rest-api.md` + `frd-outline.md` in lockstep in the same change. Either way, silent drift that breaks the skill is unacceptable.
- **Acceptance:** a third party can register a new report type + section + item type without editing plugin core.

## Phase 2 — [PLUGIN] Content model, prompts decision & the AXE fix

Storage exists (no migration), but the authoring surface does not.
- **Build the report-body editing surface chosen in Decision A (req 6).** Today only the intro shows in the editor and sections are edited as scattered separate posts. Implement the approved approach so an admin/PM can author the whole report body coherently — following ATAG Part B.
- Implement the Phase-0 prompts decision: retire client-facing `_pppd_dev_prompt`; add an **internal-only implementation-notes** field that never renders on the client view (reqs 2, 13).
- Add **data-requirement** and **UI-rule** section types via the Phase-1 registry (req 7).
- **Fix the empty `<th>` at the source (req 12):** normalize authored `core/table` blocks so a header row can't ship blank — server-side on save (sanitize/repair or reject empty header cells) and/or an editor guard, plus authoring guidance. Do not patch it in the template.
- **Edit permissions:** only admins/PM edit report content; agent-generated updates land as **drafts/`pppd_change` proposals**, never published directly (req 3) — extend the existing `pppd_agent`/guard model, don't replace it.
- **ATAG:** authoring UI (Part B) and the content it produces (Part A — valid table markup, headings, alt-text prompting) both accessible.

## Phase 3 — [PLUGIN] Client access, per-section sign-off & the approval lock

- Client users are **subscriber-role**; multiple users per client; any WP user assignable to any report (req 1).
- **Client-facing read-only frontend view** that hides all internal content — retired prompt, implementation notes, team-only sections (req 2).
- **Per-section approve** action recording approving user + timestamp, plus a **comment box** for change requests. Build on the existing `pppd_change` + comments machinery rather than a parallel system. Comment ≠ edit — clients never modify report content (reqs 1, 2).
- Change-order sign-off uses the same approve mechanism (req 5).
- **Approval lock (hard requirement):** once a section is approved it is locked against agent/draft overwrite. A later agent run must either skip approved sections or mark them "changed since approval — re-approval required," recording provenance (which run, what changed). Extend `pppd_guard_published_section_updates()`. Approved sign-offs are a legal record — nothing may silently invalidate them.

## Phase 4 — [AGENT] Commands & automation (not plugin code)

- A Claude Code command that ingests the repo, Granola meeting notes (MCP), and supplied local/Drive docs, then drafts or updates report content.
- All pushes are **draft / `pppd_change` status**. Respect the Phase-3 approval lock — never touch approved sections.
- Reuse `frd` and `project-audit`; update `frd/references/rest-api.md` if the data model moved. Note any gap where a new skill is warranted.
- **Do NOT wire up scheduled/recurring agent runs** until the approval lock (Phase 3) is shipped and verified. An agent that regenerates a deliverable on a cron before the lock exists will silently overwrite a signed-off section. Lock first, automate second.

## Phase 5 — GitHub integration ([PLUGIN] queue + config, [AGENT] push)

**Decided:** the push is **agent-gated — the plugin never stores a GitHub credential.** No PAT, no App key in WordPress. The plugin manages intent; the agent does the API call using existing Claude Code GitHub access.

- **[PLUGIN] Queue + config only.** Each report stores its target repo (`owner/name`, admin/PM-only setting) and a queue of approved items flagged "ready for GitHub" (status `queued → pushed`, with the resulting issue URL/number stored on the item). The plugin does **not** call the GitHub API and holds **no** secret.
- **Who queues, and when — human by default.** By default a **dev/PM** marks approved items ready to push; issues are NOT queued automatically on client approval. Provide a per-report **trigger setting**: (a) *dev/PM-marks* (default), or (b) *auto-on-client-approval* (client sign-off auto-queues the item).
- **[AGENT] The actual push lives in the skill/command** (Phase 4 layer). It reads the queue via REST, creates GitHub issues with the mapping approved item → title / body / labels, and writes the issue URL/number back to mark items `pushed` (idempotent — never double-push).
- Only **approved** items are eligible. Because push is agent-gated, it is inherently not real-time — issues appear on the next agent run, not on an in-admin button click. (Matt accepted this tradeoff in exchange for zero secrets in WP.)

## WordPress AI readiness — Abilities API + MCP (best practice; applies to Phases 1 & 4)

WordPress 6.9/7.0 shipped the **Abilities API** (`wp_register_ability()`) and the official **MCP Adapter** — now the standard way to make a plugin usable by AI agents. Follow it:
- **Register the plugin's agent-facing operations as typed, schema-validated, permission-gated abilities** (create/update section, propose change, run drift, etc.) via `wp_register_ability()` — discoverable from PHP/JS/REST and, opt-in, over MCP. Prefer this where an ability fits; it **composes with, does not replace**, the `pppd/v1` REST verbs the `frd` skill depends on.
- **Default-deny MCP exposure.** Only set `meta.mcp.public` on safe abilities. **Never expose approve/sign-off or any ability that can overwrite an approved section to an unaudited AI client** — this is the plugin-level enforcement of the approval lock. The existing human-only `pppd_approve_changes` cap / non-approving `pppd_agent` role is exactly this pattern; keep it.
- **Every ability checks the minimum real capability** (never `__return_true`). Least-privilege agent user, application-password (or OAuth) auth, draft/read-first. The `pppd_agent` role already models this.
- **Acceptance:** agent-facing operations are reachable via registered abilities under a default-deny MCP posture, and no approval/overwrite ability is ever AI-exposable.

## Accessibility — acceptance criteria on **every** phase (req 12)

- Report output passes AXE with **zero** violations. The empty table headers are fixed at the source (Phase 2), not patched.
- Editing flows follow **ATAG** — Part A (accessible content) and Part B (accessible authoring UI).
- No phase is "done" until both its output and its editing surface pass. This is a gate, not a final sweep. Use the `accessibility-audit` and `wordpress-accessibility-patterns` skills.

## Out of scope for this prompt (→ Prompt 2, after this ships)

Typography Stylist FRD (req 10), the content-strategy skill + interview (req 11), and the outreach/research strategy. Do not begin them here.

# Pretty Professional Process Docs

Living, agent-accessible functional requirements documents (FRDs) for WordPress — wiki-style reports with revision history, a human review queue, drift tracking against a codebase, and accessible exports.

## Two parts, installed separately

| | What it is | Where |
|---|---|---|
| **The plugin** | Storage, access control, the approval gate, the REST API, and the accessible front end. Standalone — no build step, no runtime dependencies. | this directory |
| **The agent layer** *(optional)* | Claude Code skills that author and maintain the documents: generating a report from source material, running stakeholder interviews, reconciling a spec against a codebase, pushing an approved queue to GitHub, exporting tagged PDFs. | [`agent-layer/skills/`](agent-layer/skills/) — copy its **contents** into `~/.claude/skills/`, not the `agent-layer/` folder itself: `cp -r agent-layer/skills/* ~/.claude/skills/`. Full instructions in [`agent-layer/README.md`](agent-layer/README.md) |

You can use the plugin entirely through wp-admin, or drive `pppd/v1` from any
tooling you like. The agent layer is what automates the authoring, and it is
not installed by activating the plugin.

## The living-FRD concept

A traditional FRD is a PDF that is out of date the day after sign-off. This plugin treats the FRD as a **living document**:

- **Editable** — every part of the document is a WordPress post with full revision history (revisions are kept forever for reports and sections).
- **Agent-accessible** — everything is exposed over the REST API, so automation (a coding agent, a CI job, a meeting-notes pipeline) can read the spec, propose changes, and record drift runs.
- **Human-gated** — agents can *propose* changes, but only a human with the `pppd_approve_changes` capability can approve or reject them. Approval applies the proposed content to the target section and the previous state survives as a revision.
- **Traceable** — requirement sections carry stable IDs (`FR-001`, …), acceptance criteria, code references, and test references. Drift runs record how well the codebase covers each requirement, and a traceability matrix (JSON or CSV) rolls it all up.

## Data model

| Object | Kind | Purpose |
|---|---|---|
| `pppd_report` | Post type (public, `/report/{slug}/`) | The report itself. Its editor content is the executive summary. |
| `pppd_section` | Post type (private, hierarchical) | The granular unit: narrative, requirement, or decision. Supports comments, revisions, ordering, nesting. |
| `pppd_change` | Post type (private) | A proposed replacement for one section's content, awaiting human review. Workflow: `pending` → `publish` (approved) or `pppd_rejected`. |
| `pppd_drift` | Post type (private) | Append-only history of drift runs (codebase-vs-spec checks). |
| `pppd_section_type` | Taxonomy | `narrative`, `requirement`, `decision`. Report types may register more. |
| `pppd_status` | Taxonomy | `draft`, `agreed`, `at-risk`, `built`, `verified`. |
| `pppd_report_type` | Taxonomy | Which registered report type a report is. A report with no term is an FRD. |
| `pppd_client` | Taxonomy | The client a report belongs to — the organizing entity for access control. |

Key meta (all registered with sanitizers, auth callbacks, and REST schemas):

| Meta | On | Purpose |
|---|---|---|
| `_pppd_report_id` | section, drift | Owning report. |
| `_pppd_req_id` | section | Stable requirement ID (`FR-001`). Assigned automatically on first publish of a `requirement` section; never reassigned or reused. |
| `_pppd_acceptance` | section | Acceptance criteria (array of strings). |
| `_pppd_impl_notes` | section | Internal-only implementation brief for the developer/agent. Never rendered on the client view. |
| `_pppd_dev_prompt` | section | **Deprecated** — the client-facing predecessor of `_pppd_impl_notes`, retired from the client view. Don't write to it. |
| `_pppd_internal` | section | Team-only flag; internal sections are withheld from the client view. |
| `_pppd_code_refs` / `_pppd_test_refs` | section | File/symbol and test references. |
| `_pppd_approved_by` / `_pppd_approved_at` / `_pppd_approved_revision` / `_pppd_reapproval_source` | section | Sign-off record. Read-only over REST for everyone. |
| `_pppd_github_status` / `_pppd_github_issue_url` / `_pppd_github_issue_number` / `_pppd_github_queued_at` / `_pppd_github_queued_by` / `_pppd_github_pushed_at` | section | Push-queue state. Read-only over REST; written by the queue endpoints. |
| `_pppd_target_section` | change | Section the change applies to. |
| `_pppd_source` / `_pppd_rationale` / `_pppd_reject_reason` | change | Provenance and review outcome. |
| `_pppd_applied_revision` / `_pppd_reviewed_by` / `_pppd_reviewed_at` | change | Section revision created on approval, and who reviewed it when. |
| `_pppd_repo_paths` / `_pppd_req_prefix` / `_pppd_req_counter` / `_pppd_project_slug` | report | Repo mapping, requirement ID prefix (default `FR`), counter, machine slug. |
| `_pppd_assigned_user_ids` | report | Users granted access to this report beyond the client-term match. |
| `_pppd_pdf_url` | report | Stored tagged PDF served by the front-end download link. |
| `_pppd_public` | report | Makes a **published** report world-readable (a work sample). Human-only to set — the agent role cannot. |
| `_pppd_github_repo` / `_pppd_github_trigger` | report | Target repo (`owner/name`) and whether sign-off queues items automatically (`manual` default / `auto`). Human-only. |
| `_pppd_drift_json` | drift | Validated per-requirement verdicts for the run. |

## Roles & capabilities

- **Administrator / Editor** — full control, including the human-only `pppd_approve_changes` capability.
- **`pppd_agent` ("Process Docs Agent")** — the role to give automation. It can read, create, edit, and publish reports/sections/changes/drift runs, but it **cannot delete anything and cannot approve or reject changes**. Give your agent a user with this role and an [application password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/).
- **Clients** — reports are organized by a `pppd_client` term. A logged-in user sees a report only if they match its client (or are listed in `_pppd_assigned_user_ids`); this is enforced at the single-report template, in search, and on every REST read route. Team viewers additionally see draft and internal sections, flagged as not part of the signed document; `?pppd_preview=published` shows a team viewer the exact client view.
- Deactivating the plugin leaves roles/capabilities untouched; uninstalling removes them (content is kept — see `uninstall.php`).

## REST API

Core CRUD comes free from `show_in_rest` (`/wp/v2/pppd-reports`, `/wp/v2/pppd-sections`, `/wp/v2/pppd-changes`, `/wp/v2/pppd-drift`). The plugin adds a `pppd/v1` namespace:

| Endpoint | Method | Permission | Purpose |
|---|---|---|---|
| `/pppd/v1/reports/{id}/outline` | GET | view access to the report | Report metadata + ordered section tree (type, status, req ID, depth, comment counts, sign-off state, `post_status`). Add `?status=publish,draft` to include drafts — team-only; other callers get `403 pppd_forbidden_status`. |
| `/pppd/v1/reports/{id}/reorder` | POST | `edit_pppd_reports` | Reorder/renest a report's sections; backs the keyboard-operable outline metabox. |
| `/pppd/v1/changes/{id}/approve` | POST | `pppd_approve_changes` | Apply a pending change to its target section (creates a revision), stamp reviewer + applied revision. |
| `/pppd/v1/changes/{id}/reject` | POST | `pppd_approve_changes` | Reject a pending change; optional JSON body `{ "reason": "..." }`. |
| `/pppd/v1/reports/{id}/drift` | POST | `edit_pppd_reports` | Record a drift run: `{ "summary": "...", "items": [ { "req_id", "verdict", "code_refs": [], "test_refs": [], "notes" } ] }`. Verdicts: `covered`, `partial`, `missing`, `orphan`. |
| `/pppd/v1/reports/{id}/drift/latest` | GET | `edit_pppd_reports` | Latest drift run (summary + items), 404 if none. |
| `/pppd/v1/reports/{id}/traceability` | GET | `edit_pppd_reports` | One row per requirement: status, acceptance count, refs, latest drift verdict. Add `?format=csv` for a CSV download (formula-injection-safe). |
| `/pppd/v1/github/queue` | GET | `edit_pppd_reports` | Approved, signed-off sections flagged ready to become GitHub issues. Optional `?report={id}`. |
| `/pppd/v1/github/queue/{section}/pushed` | POST | `edit_pppd_reports` | Record the issue created for a queued section (`queued` → `pushed`). Idempotent: a second push returns 409 rather than duplicating. |

### Example calls (application passwords)

```bash
# Read a report outline
curl -u agent:APP_PASSWORD \
  "https://example.test/wp-json/pppd/v1/reports/123/outline"

# Propose a change (core endpoint; note status=pending)
curl -u agent:APP_PASSWORD -X POST \
  -H "Content-Type: application/json" \
  -d '{"title":"Update FR-003 wording","content":"<p>New section content…</p>","status":"pending","meta":{"_pppd_target_section":456,"_pppd_source":"meeting-2026-07-01","_pppd_rationale":"Scope clarified in standup"}}' \
  "https://example.test/wp-json/wp/v2/pppd-changes"

# Record a drift run
curl -u agent:APP_PASSWORD -X POST \
  -H "Content-Type: application/json" \
  -d '{"summary":"Nightly drift check","items":[{"req_id":"FR-001","verdict":"covered","code_refs":["src/auth.php"],"test_refs":["tests/AuthTest.php"],"notes":""}]}' \
  "https://example.test/wp-json/pppd/v1/reports/123/drift"

# Approve a change (human reviewer credentials — agents cannot do this)
curl -u reviewer:APP_PASSWORD -X POST \
  "https://example.test/wp-json/pppd/v1/changes/789/approve"

# Traceability CSV
curl -u reviewer:APP_PASSWORD \
  "https://example.test/wp-json/pppd/v1/reports/123/traceability?format=csv" -o traceability.csv
```

## Admin UI

- **Process Docs** menu: Reports, plus Sections / Proposed Changes / Drift Runs / **Review Queue** submenus.
- The Review Queue shows every pending change with source, rationale, a side-by-side diff against the current section content, and Approve / Reject buttons (nonce-protected; same underlying logic as the REST endpoints).
- Meta boxes cover report settings (repo paths, requirement prefix, project slug, client, GitHub target), section settings (parent report, acceptance criteria, internal implementation notes, code/test refs), and change settings (target section, source, rationale).
- The report outline metabox lists a report's whole section tree and supports keyboard-operable reordering and renesting.

## Front end

Single reports render with a dedicated accessible template: skip link, sidebar table of contents with current-section highlighting, hierarchical headings, status badges (symbol + label — never color alone), acceptance-criteria tables with captions and scoped headers, latest drift summary, per-section discussion (logged-in users), and per-section file attachments (logged-in users with `upload_files`; whitelisted file types).

## Accessibility notes

- The layout follows a strict contract: one `main` landmark, labelled `nav`, `section[aria-labelledby]` per section, real tables, visible focus styles, reduced-motion support, 44px minimum touch targets on controls.
- **Accessible PDF export**: the report front-end shows a **Download accessible PDF** link whenever a report has a `_pppd_pdf_url` set — this points at a genuinely tagged/navigable PDF and is stored on the report. When no such file exists yet, the front-end falls back to a **Print / Save as PDF** button (browser print), which is *not* guaranteed to be tagged — so it is labelled honestly rather than promising accessibility it can't deliver. The plugin does not generate the tagged PDF itself; [`agent-layer/skills/shared/report-design/export-pdf.mjs`](agent-layer/skills/shared/report-design/export-pdf.mjs) produces it via Chromium's `page.pdf({ tagged: true })` and fails loudly unless the output carries `/StructTreeRoot` and `/Marked true`. Attach the result to the report and set `_pppd_pdf_url`.
- The traceability CSV escapes cells that start with `=`, `+`, `-`, or `@` to prevent spreadsheet formula injection.

## Development setup

1. Drop the plugin into `wp-content/plugins/` and activate it (WordPress 6.4+, PHP 8.0+).
2. Activation registers post types/taxonomies, inserts the default terms, grants capabilities, creates the `pppd_agent` role, and flushes rewrite rules.
3. Create a report, add sections (assign each a parent report + section type), and publish. Requirement-type sections get their `FR-###` ID on first publish.
4. For automation, create a user with the `pppd_agent` role and generate an application password. Then, optionally, install the agent layer — see [`agent-layer/README.md`](agent-layer/README.md).

No build step: the plugin ships plain CSS/JS. Composer is used for the test
suite only — there are no runtime dependencies, so `vendor/` never ships.

```sh
composer install
composer test          # PHPUnit; needs a WP test install, see tests/phpunit/wp-tests-config-sample.php
php tools/make-pot.php # regenerate languages/*.pot after changing any user-facing string
```

Repository layout:

```
includes/     plugin PHP (post types, access, REST controllers, admin)
templates/    front-end report templates and partials
assets/       plain CSS/JS, no build step
languages/    POT file for translators
docs/         REST contract (frozen at v1) and the roadmap parking lot
tools/        one-off maintenance scripts (block migration, POT generation)
tests/        PHPUnit suite
agent-layer/  optional Claude Code skills — installed to ~/.claude/skills/
```

## Contributing

The `pppd/v1` REST surface is a **frozen, additive-only contract** — see
[`docs/rest-contract.md`](docs/rest-contract.md). A change that removes or
renames anything in it must bump `PPPD_CONTRACT_VERSION` and update both the
contract doc and `agent-layer/skills/frd/references/rest-api.md` in the same
commit. Silent drift breaks every agent talking to the plugin.

## License

GPL-2.0-or-later.

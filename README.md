# Pretty Professional Process Docs

Living, agent-accessible functional requirements documents (FRDs) for WordPress — wiki-style reports with revision history, a human review queue, drift tracking against a codebase, and accessible exports.

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
| `pppd_section_type` | Taxonomy | `narrative`, `requirement`, `decision`. |
| `pppd_status` | Taxonomy | `draft`, `agreed`, `at-risk`, `built`, `verified`. |

Key meta (all registered with sanitizers, auth callbacks, and REST schemas):

| Meta | On | Purpose |
|---|---|---|
| `_pppd_report_id` | section, drift | Owning report. |
| `_pppd_req_id` | section | Stable requirement ID (`FR-001`). Assigned automatically on first publish of a `requirement` section; never reassigned or reused. |
| `_pppd_acceptance` | section | Acceptance criteria (array of strings). |
| `_pppd_dev_prompt` | section | Hand-off prompt for the implementing developer/agent. |
| `_pppd_code_refs` / `_pppd_test_refs` | section | File/symbol and test references. |
| `_pppd_target_section` | change | Section the change applies to. |
| `_pppd_source` / `_pppd_rationale` / `_pppd_reject_reason` | change | Provenance and review outcome. |
| `_pppd_applied_revision` | change | Section revision created when the change was approved. |
| `_pppd_repo_paths` / `_pppd_req_prefix` / `_pppd_req_counter` / `_pppd_project_slug` | report | Repo mapping, requirement ID prefix (default `FR`), counter, machine slug. |
| `_pppd_drift_json` | drift | Validated per-requirement verdicts for the run. |

## Roles & capabilities

- **Administrator / Editor** — full control, including the human-only `pppd_approve_changes` capability.
- **`pppd_agent` ("Process Docs Agent")** — the role to give automation. It can read, create, edit, and publish reports/sections/changes/drift runs, but it **cannot delete anything and cannot approve or reject changes**. Give your agent a user with this role and an [application password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/).
- Deactivating the plugin leaves roles/capabilities untouched; uninstalling removes them (content is kept — see `uninstall.php`).

## REST API

Core CRUD comes free from `show_in_rest` (`/wp/v2/pppd-reports`, `/wp/v2/pppd-sections`, `/wp/v2/pppd-changes`, `/wp/v2/pppd-drift`). The plugin adds a `pppd/v1` namespace:

| Endpoint | Method | Permission | Purpose |
|---|---|---|---|
| `/pppd/v1/reports/{id}/outline` | GET | `read` | Report metadata + ordered section tree (type, status, req ID, depth, comment counts). |
| `/pppd/v1/changes/{id}/approve` | POST | `pppd_approve_changes` | Apply a pending change to its target section (creates a revision), stamp reviewer + applied revision. |
| `/pppd/v1/changes/{id}/reject` | POST | `pppd_approve_changes` | Reject a pending change; optional JSON body `{ "reason": "..." }`. |
| `/pppd/v1/reports/{id}/drift` | POST | `edit_pppd_reports` | Record a drift run: `{ "summary": "...", "items": [ { "req_id", "verdict", "code_refs": [], "test_refs": [], "notes" } ] }`. Verdicts: `covered`, `partial`, `missing`, `orphan`. |
| `/pppd/v1/reports/{id}/drift/latest` | GET | `read` | Latest drift run (summary + items), 404 if none. |
| `/pppd/v1/reports/{id}/traceability` | GET | `read` | One row per requirement: status, acceptance count, refs, latest drift verdict. Add `?format=csv` for a CSV download (formula-injection-safe). |

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
- Meta boxes cover report settings (repo paths, requirement prefix, project slug), section settings (parent report, acceptance criteria, developer prompt, code/test refs), and change settings (target section, source, rationale).

## Front end

Single reports render with a dedicated accessible template: skip link, sidebar table of contents with current-section highlighting, hierarchical headings, status badges (symbol + label — never color alone), acceptance-criteria tables with captions and scoped headers, latest drift summary, per-section discussion (logged-in users), and per-section file attachments (logged-in users with `upload_files`; whitelisted file types).

## Accessibility notes

- The layout follows a strict contract: one `main` landmark, labelled `nav`, `section[aria-labelledby]` per section, real tables, visible focus styles, reduced-motion support, 44px minimum touch targets on controls.
- **Accessible PDF export**: the report front-end shows a **Download accessible PDF** link whenever a report has a `_pppd_pdf_url` set — this points at a genuinely tagged/navigable PDF produced by the `export-pdf.mjs` exporter (`page.pdf({ tagged: true })`) and stored on the report. When no such file exists yet, the front-end falls back to a **Print / Save as PDF** button (browser print), which is *not* guaranteed to be tagged — so it is labelled honestly rather than promising accessibility it can't deliver. Generate and attach the tagged PDF via the `frd export` workflow.
- The traceability CSV escapes cells that start with `=`, `+`, `-`, or `@` to prevent spreadsheet formula injection.

## Development setup

1. Drop the plugin into `wp-content/plugins/` and activate it (WordPress 6.4+, PHP 8.0+).
2. Activation registers post types/taxonomies, inserts the default terms, grants capabilities, creates the `pppd_agent` role, and flushes rewrite rules.
3. Create a report, add sections (assign each a parent report + section type), and publish. Requirement-type sections get their `FR-###` ID on first publish.
4. For automation, create a user with the `pppd_agent` role and generate an application password.

No build step: the plugin ships plain CSS/JS.

## License

GPL-2.0-or-later.

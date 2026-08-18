# PPPD REST API — agent cheat sheet

Base: `{PPPD_URL}/wp-json`. Auth: HTTP Basic with an application password
(`-u "user:app_password"`). The service role `pppd_agent` can create/edit
reports, sections, changes, and drift runs but can NOT approve changes —
approval is human-only, in wp-admin → Reports → Review Queue.

Contract: this surface is frozen as **v1** in the plugin's
`docs/rest-contract.md` (additive-only; plugin changes that remove/rename
anything here must update this file in the same change). Since plugin 0.2.0
the outline response carries `pppd_contract_version` (`"1"`) — if it's absent
you're talking to an older plugin; if it's greater, re-read the contract doc.

Credentials: env `PPPD_URL` / `PPPD_USER` / `PPPD_APP_PASS`, or a
`pppd-agent.env` file at or above the WP webroot (e.g. a Local site keeps it
in the site root, beside `app\`).

## Core CRUD (WordPress `wp/v2`)

| Resource | Route | Notes |
|---|---|---|
| Reports | `wp/v2/pppd-reports` | content = executive summary; meta: `_pppd_repo_paths[]`, `_pppd_req_prefix`, `_pppd_project_slug` |
| Sections | `wp/v2/pppd-sections` | meta: `_pppd_report_id`, `_pppd_acceptance[]`, `_pppd_dev_prompt`, `_pppd_code_refs[]`, `_pppd_test_refs[]`, `_pppd_req_id` (read-only in practice — assigned server-side on publish); `parent`, `menu_order` for tree |
| Section type | `wp/v2/pppd_section_type` | terms: narrative / requirement / decision (plugin ≥0.2.0 may add more via its type registry — treat unknown terms as valid) |
| Status | `wp/v2/pppd_status` | terms: draft / agreed / at-risk / built / verified |
| Report type | `wp/v2/pppd_report_type` | plugin ≥0.2.0: frd / user-access-model / change-order / content-strategy; a report with no term is an `frd` (all pre-0.2.0 reports). The outline response echoes it as `report.type`. |
| Client | `wp/v2/pppd_client` | plugin ≥0.2.0: the client a report belongs to (site-defined terms) |
| Changes | `wp/v2/pppd-changes` | create with `status: "pending"`, content = proposed replacement; meta `_pppd_target_section`, `_pppd_source`, `_pppd_rationale` |
| Comments | `wp/v2/comments` | `post` = section ID |
| Media | `wp/v2/media` | `post` = section ID to attach |

Create a requirement section (example):

```bash
curl -sk -u "$PPPD_USER:$PPPD_APP_PASS" -X POST "$PPPD_URL/wp-json/wp/v2/pppd-sections" \
  -H "Content-Type: application/json" \
  -d '{"title":"User can reset a forgotten password","status":"draft",
       "content":"<!-- wp:paragraph -->\n<p>…</p>\n<!-- /wp:paragraph -->","parent":123,"menu_order":40,
       "pppd_section_type":[<term_id>],"pppd_status":[<term_id>],
       "meta":{"_pppd_report_id":45,
               "_pppd_acceptance":["Reset link expires 60 minutes after it is issued","A spent link shows an explanatory message, not a server error"],
               "_pppd_impl_notes":"Implement …"}}'
```

Fetch taxonomy term IDs once per session (`GET wp/v2/pppd_section_type` etc.)
— do not hardcode them.

## The three status axes (do not conflate)

| Axis | Where you see it | Question it answers |
|---|---|---|
| `post_status` (WP: `draft`/`publish`) | section's `status` field; outline row `post_status` (plugin ≥0.4.0) | Is this section part of the published document? `draft` = written, reviewable, **not yet part of it**. Publishing is a human act; it mints item IDs and arms the approval lock. |
| `pppd_status` (taxonomy) | outline row `status`; the badge readers see | Workflow state: draft → agreed → built → verified, `at-risk` as a flag. Set it freely on your drafts. |
| `signoff.state` | outline row `signoff` | Legal record: none / approved / stale. `approved` = never write. |

A draft section (post_status) whose workflow term is `agreed` is fine; the two
move independently. Readers of the rendered report: team viewers see draft
sections flagged "Draft — not part of the signed document"; clients and
anonymous visitors never see them (`?pppd_preview=published` shows a team
viewer the exact client view).

## Section content is core block markup (plugin ≥0.4.0)

Write `content` as Gutenberg core-block grammar, NOT bare HTML — sections then
open as editable blocks instead of one Classic block. One block comment pair
per top-level element; the same accessibility rules as before (heading
hierarchy, real lists, `scope`d table headers, nothing color-only).

```html
<!-- wp:paragraph -->
<p>One idea per paragraph.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Subheading (h2 omits the level attr)</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Each item is its own wp:list-item block.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:code -->
<pre class="wp-block-code"><code>Invoke-prompt or code, entities escaped.</code></pre>
<!-- /wp:code -->

<!-- wp:html -->
<table>…real caption + th scope markup…</table>
<!-- /wp:html -->
```

Rules: ordered lists add `{"ordered":true}` and use `<ol>`; **tables go in
`wp:html`** (the core table block cannot represent `<caption>` or scoped row
headers, which the report design requires); anything else nonstandard also
goes in `wp:html`. Structured data — acceptance criteria, req IDs, code/test
refs — stays in **meta**, never in content; the render adds it from meta.
The editor offers matching starter patterns (`PPPD: Narrative/Requirement/
Decision section`) so hand-authored sections look the same.

## Custom verbs (`pppd/v1`)

| Route | Method | Purpose |
|---|---|---|
| `pppd/v1/reports/{id}/outline` | GET | Orientation call: section tree with type/status/req_id/comment counts. Call this FIRST in any subcommand. Plugin ≥0.3.0 adds per-section `signoff` (`{state: none\|approved\|stale, by, at}`) and `internal` (team-only flag). Plugin ≥0.4.0 adds per-row `post_status` and an optional `?status=` param (CSV of `publish,draft,pending,future,private`; **team-only** — the agent qualifies; non-team callers get `403 pppd_forbidden_status`; default `publish` unchanged). |
| `pppd/v1/changes/{id}/approve` | POST | Human-only (`pppd_approve_changes`). Agents never call this. |
| `pppd/v1/changes/{id}/reject` | POST | Human-only. Body: `{"reason":"…"}` |
| `pppd/v1/reports/{id}/drift` | POST | Submit a drift run: `{"summary":"…","items":[{"req_id":"FR-001","verdict":"covered|partial|missing|orphan","code_refs":[],"test_refs":[],"notes":"…"}]}` |
| `pppd/v1/reports/{id}/drift/latest` | GET | Latest run, parsed items |
| `pppd/v1/reports/{id}/traceability` | GET | Requirement matrix; `?format=csv` for the CSV export. Plugin ≥0.2.0 appends a `type` field/column (the row's section type — non-requirement item types can appear); existing columns never move. |

## Ground rules

- Agents create sections as **drafts** and may edit drafts freely; publishing
  is a human act in wp-admin (it assigns item IDs and arms the approval lock).
  Never PUT/PATCH a published section directly — propose a `pppd_change`
  instead. This is SERVER-ENFORCED: REST updates to published sections return
  `403 pppd_approval_required` unless the user holds the human-only
  `pppd_approve_changes` cap.
- **Draft visibility:** by default `pppd/v1/reports/{id}/outline` returns
  published sections only; an all-draft report answers `"sections": []`.
  Plugin ≥0.4.0: enumerate drafts with
  `GET pppd/v1/reports/{id}/outline?status=publish,draft` (team-only; the
  agent qualifies) — this replaces the old workaround of querying
  `wp/v2/pppd-sections?status=draft` and filtering on `meta._pppd_report_id`.
  Verify your own draft work through the outline route. On an older plugin
  (no `post_status` in rows), fall back to the wp/v2 query.
- **Item-ID minting:** `_pppd_req_id` mints on **first publish only** — draft
  reading/previewing never mints, and a publish → draft → publish round-trip
  keeps the ID. Don't publish sections just to get IDs; drafts are readable
  via the outline `status` param and the team render.
- **Sign-off lock (plugin ≥0.3.0):** a section whose outline `signoff.state`
  is `approved` is a client-signed legal record. Never write to it — skip it,
  or file a `pppd_change` knowing approval flips to "changed since approval —
  re-approval required." Sign-off meta itself is read-only over REST for
  everyone.
- Since plugin 0.3.0, `traceability` and `drift/latest` require
  `edit_pppd_reports` and outline requires view access to the report — the
  `pppd_agent` user qualifies for all of them; nothing changes for this
  skill's flows.
- Prefer writing `_pppd_impl_notes` (internal-only) over the deprecated
  client-facing `_pppd_dev_prompt`; the plugin mirrors old-field writes into
  empty impl notes during the deprecation window.
- The GitHub push queue (`pppd/v1/github/queue`) belongs to the `pppd-sync`
  skill — see `~/.claude/skills/pppd-sync/SKILL.md`.
- `PPPD_ADMIN_USER` / `PPPD_ADMIN_APP_PASS` (if present in the env file) are
  the human account — use ONLY when the user explicitly asks you to act as
  them (e.g. cleanup); day-to-day writes go through the agent credentials.
- `pppd_report._pppd_public` (plugin ≥0.4.0) makes a **published** report
  world-readable (a work sample). Human-only write — the agent role cannot
  set it, by design; never try.
- `_pppd_req_id` is assigned by the server when a requirement publishes.
  Reference requirements by that ID everywhere (drift items, interview docs,
  dev prompts).
- Check REST error bodies: 403 = missing cap (probably trying to approve as
  the agent user), 404 = wrong ID, 409 = change no longer pending.

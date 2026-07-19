# PPPD REST Contract — v1

**Contract version:** `1` (exposed as `PPPD_CONTRACT_VERSION` and as `pppd_contract_version` on the outline response).

This document freezes the surface the `frd` Claude Code skill (and any other external consumer) depends on. **Changes to this surface are additive only.** Removing or renaming anything listed here requires a contract major-version bump **and** a lockstep update to the skill's `references/rest-api.md` in the same change. Silent drift that breaks the skill is unacceptable.

The contract-guard test (`tests/test-contract.php`) asserts this file's inventory against the live registrations — if it fails, either the code broke the contract or this file needs an *additive* update.

## Auth model

- WordPress **application passwords**, HTTP Basic.
- Service role **`pppd_agent`**: `read` + all `pppd_report` edit/publish/create caps, **no** `delete_*`, **no** `pppd_approve_changes`. Agents create and propose; humans approve.
- Human-only capability: **`pppd_approve_changes`** (administrator, editor).
- REST guard: updating a **published** `pppd_section` without `pppd_approve_changes` returns `403 pppd_approval_required` (`pppd_guard_published_section_updates()`, hooked `rest_pre_insert_pppd_section`). New inserts and draft edits pass.

## Post types (core `wp/v2` routes)

| CPT | REST base | Notes |
|---|---|---|
| `pppd_report` | `wp/v2/pppd-reports` | `post_content` = executive summary only |
| `pppd_section` | `wp/v2/pppd-sections` | hierarchical; `parent` + `menu_order` position it |
| `pppd_change` | `wp/v2/pppd-changes` | proposals created with `status: pending` |
| `pppd_drift` | `wp/v2/pppd-drift` | written via the custom drift route, not directly |

Custom post status: `pppd_rejected` (rejected changes). Approvals use core `publish`; proposals sit in `pending`.

## Taxonomies

| Taxonomy | On | Frozen term slugs |
|---|---|---|
| `pppd_section_type` | `pppd_section` | `narrative`, `requirement`, `decision` (registry may **add** terms; these three never change) |
| `pppd_status` | `pppd_section` | `draft`, `agreed`, `at-risk`, `built`, `verified` |
| `pppd_report_type` | `pppd_report` | *(added in v0.2.0, additive)* `frd`, `user-access-model`, `change-order`, `content-strategy`; a report without a term is an `frd` |
| `pppd_client` | `pppd_report` | *(added in v0.2.0, additive)* site-defined terms, no frozen slugs |

Term IDs are never part of the contract — consumers fetch them per session.

## Registered meta (all `single`, REST-exposed, auth = `edit_pppd_reports`)

- **`pppd_section`:** `_pppd_report_id` (int), `_pppd_req_id` (string, **server-assigned on publish — never write client-side**), `_pppd_acceptance` (string[]), `_pppd_dev_prompt` (string), `_pppd_code_refs` (string[]), `_pppd_test_refs` (string[])
- **`pppd_change`:** `_pppd_target_section` (int), `_pppd_source` (string), `_pppd_rationale` (string), `_pppd_reject_reason` (string), `_pppd_applied_revision` (int), `_pppd_reviewed_by` (int), `_pppd_reviewed_at` (string, GMT MySQL — *added v0.2.0*)
- **`pppd_report`:** `_pppd_repo_paths` (string[]), `_pppd_req_prefix` (string, default `FR`), `_pppd_req_counter` (int), `_pppd_project_slug` (string), `_pppd_pdf_url` (string), `_pppd_assigned_user_ids` (int[] — *added v0.2.0*)
- **`pppd_drift`:** `_pppd_report_id` (int), `_pppd_drift_json` (string, JSON)

## Custom routes (`pppd/v1`)

| Route | Method | Capability |
|---|---|---|
| `/reports/{id}/outline` | GET | `read` |
| `/reports/{id}/traceability` (`?format=csv`) | GET | `read` |
| `/reports/{id}/drift` | POST | `edit_pppd_reports` |
| `/reports/{id}/drift/latest` | GET | `read` |
| `/changes/{id}/approve` | POST | `pppd_approve_changes` (human-only; agents never call) |
| `/changes/{id}/reject` | POST | `pppd_approve_changes` (human-only) |

### Frozen response shapes

- **Outline:** `{ report: { id, title, link, project_slug, type* }, sections: [ { id, title, parent, order, depth, type, status, req_id, comment_count, edit_link } ], pppd_contract_version* }` — `*` added v0.2.0 (additive).
- **Traceability row (JSON) / CSV column order:** `req_id, title, status, acceptance_count, code_refs, test_refs, drift_verdict, section_id` + `type` **appended last** in v0.2.0. New CSV columns are only ever appended.
- **Change summary (approve/reject responses):** `id, title, status, target_section, source, rationale, reject_reason, applied_revision, reviewed_by` + `reviewed_at` (v0.2.0).

## v0.3.0 additions (additive) and permission tightenings

**New registered meta** (all REST-readable):
- `pppd_section`: `_pppd_impl_notes` (string, internal-only in rendering), `_pppd_internal` (bool, team-only section), sign-off record `_pppd_approved_by`/`_pppd_approved_at`/`_pppd_approved_revision`/`_pppd_reapproval_source` (**REST read-only** — stamped exclusively by the sign-off flow; a legal record), GitHub queue state `_pppd_github_status`/`_pppd_github_issue_url`/`_pppd_github_issue_number`/`_pppd_github_queued_at`/`_pppd_github_queued_by`/`_pppd_github_pushed_at` (**REST read-only** — moves via the queue endpoints only).
- `pppd_report`: `_pppd_github_repo`, `_pppd_github_trigger` (**human-only writes** — `pppd_approve_changes`; the agent role can never set the target repo).

**New routes:**

| Route | Method | Capability |
|---|---|---|
| `/reports/{id}/reorder` | POST | `edit_pppd_reports` (authoring UI; agents don't need it) |
| `/github/queue` (`?report=`) | GET | `edit_pppd_reports` |
| `/github/queue/{section}/pushed` | POST | `edit_pppd_reports`; 409 unless status is `queued` (idempotent — never double-push) |

**Outline additions:** each section gains `signoff` (`{state: none|approved|stale, by, at, revision, source}`) and `internal` (bool). **Agent rule: `signoff.state === "approved"` means the section is read-only — skip it, or propose a `pppd_change` knowing it forces re-approval.**

**Permission tightenings (security fixes; the `pppd_agent` role and team are unaffected because they hold `edit_pppd_reports`):**
- `/reports/{id}/outline` GET: was `read`, now requires *view access* to that report (team capability, client membership, or per-report assignment).
- `/reports/{id}/traceability` GET and `/reports/{id}/drift/latest` GET: was `read`, now **team-only** (`edit_pppd_reports`) — rows carry internal fields.
- Core `wp/v2/pppd-reports` reads: were public; now require view access (single: 401/403; collection: scoped to viewable reports).

**Deprecation:** `_pppd_dev_prompt` no longer renders on the client view. It stays registered and writable; writes mirror one-way into `_pppd_impl_notes` while it is empty. Prefer writing `_pppd_impl_notes`. Mirror removal target: 0.5.0.

**Abilities (WP Abilities API, when available):** `pppd/get-outline` (the only `meta.mcp.public` ability; read-only, view-gated), `pppd/propose-change`, `pppd/get-github-queue`, `pppd/mark-issue-pushed` (all `edit_pppd_reports`, not MCP-exposed). Approve/sign-off/queue-for-github are deliberately **not** abilities — human-only surfaces, never AI-exposable.

## v0.4.0 additions (additive) and permission tightenings

**Contract version remains `1`** — every change below is additive (new optional parameter, new response key, stricter permissions). No existing key is renamed, removed, or retyped; callers that ignore the additions see the same responses as before.

**The three status axes.** These were previously easy to conflate; they are distinct:

| Axis | Where | Question it answers |
|---|---|---|
| `post_status` (WP) | section post; outline row `post_status` | Is this section part of the published document? (`draft` = written, reviewable, not yet part of it) |
| `pppd_status` (taxonomy) | outline row `status`; the badge readers see | Workflow state: `draft` → `agreed` → `built` → `verified`, with `at-risk` as a flag |
| `signoff.state` | outline row `signoff` | Legal record: `none` / `approved` / `stale` |

**Outline `status` parameter (team-only):** `GET /reports/{id}/outline?status=…` accepts a CSV or array of `publish`, `draft`, `pending`, `future`, `private`. Default remains `publish` — existing callers are unaffected. Non-team callers (client members) who pass `status` get an explicit `403 pppd_forbidden_status`; invalid values are a core `400 rest_invalid_param`. This replaces the old workaround of enumerating drafts via `wp/v2/pppd-sections?status=draft` + client-side meta filtering.

**Outline row `post_status` (additive):** each section row now carries its WordPress `post_status` alongside the `pppd_status` term in `status`, so consumers can distinguish the two axes.

**Draft rendering rule:** team viewers (`edit_pppd_reports`) see non-published sections in the front-end report render, each flagged "Draft — not part of the signed document"; `?pppd_preview=published` shows a team viewer the exact client view. Clients and anonymous visitors always get the publish-only document.

**Permission tightening (security fix, same category as the v0.3.0 tightenings):** outline section rows are now filtered through the same internal-section rule as the front-end template — non-team callers no longer receive rows for `_pppd_internal` sections. Previously a client member could read internal sections' titles, req IDs, and sign-off state via the outline.

**Item-ID minting is unchanged and now explicit:** `_pppd_req_id` mints on **first publish** only. Draft reading/previewing never mints; a publish → draft → publish round-trip keeps the existing ID and counter.

**New registered meta:** `pppd_report._pppd_public` (bool, **human-only write** — `pppd_approve_changes`; the agent role can never expose a report). When a **published** report carries the flag, anonymous visitors may read it: the front-end template guard, `wp/v2/pppd-reports/{id}`, the scoped collection, and the outline route all open up — but readers still receive only published, non-internal sections, the outline `status` param stays team-only, and site search continues to exclude reports (public = reachable by URL, not discoverable).

## Behavioral invariants

1. **Item IDs are server-assigned** on first publish of a section whose type bears IDs; never reassigned or reused. For `requirement` sections the counter is the report's `_pppd_req_counter` and the prefix is the report's `_pppd_req_prefix` when explicitly set, else the report type's scheme (`FR` for FRDs) — byte-identical to pre-registry behavior for existing reports.
2. **Propose, don't edit:** after initial publish, agents modify published sections only via `pppd_change` proposals. The 403 guard enforces this server-side.
3. **Approval is human-only** and only ever gets stricter.
4. **The sign-off lock (0.3.0):** a section with `signoff.state === "approved"` is a legal record. Its sign-off meta cannot be written over REST by anyone; any content change (even a human-approved `pppd_change`) flips it to `stale` — "changed since approval, re-approval required" — with the invalidating change ID recorded in `_pppd_reapproval_source`. Nothing can silently alter a signed-off section.
5. **Zero GitHub secrets in WordPress:** the plugin stores only the target repo and queue state; the agent layer performs pushes with its own credentials.

## Extension surface (v0.2.0, additive — not depended on by the frd skill yet)

`pppd_register_report_type()` / `pppd_register_section_type()` on the `pppd_register_types` action; filters `pppd_report_type_args`, `pppd_section_type_args`, `pppd_item_id_prefix`, `pppd_traceability_row`, `pppd_section_meta_partial`; actions `pppd_item_id_assigned`, `pppd_change_approved`, `pppd_change_rejected`.

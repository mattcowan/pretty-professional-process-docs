=== Pretty Professional Process Docs ===
Tags: requirements, documentation, rest-api, accessibility, project-management
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.4.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Living, agent-accessible requirements documents: wiki-style reports with revision history, a human review queue, and drift tracking.

== Description ==

A traditional functional requirements document is a PDF that is out of date the
day after sign-off. This plugin treats the requirements document as a living
one:

* **Editable** — every part of the document is a WordPress post with full
  revision history, kept forever for reports and sections.
* **Agent-accessible** — everything is exposed over the REST API, so automation
  (a coding agent, a CI job, a meeting-notes pipeline) can read the spec,
  propose changes, and record drift runs.
* **Human-gated** — agents *propose*; only a human holding the
  `pppd_approve_changes` capability can approve or reject. Approval applies the
  proposed content to its target section, and the previous state survives as a
  revision. This is server-enforced, not a convention.
* **Traceable** — requirement sections carry stable IDs (`FR-001`, …),
  acceptance criteria, and code/test references. Drift runs record how well the
  codebase covers each requirement, and a traceability matrix (JSON or CSV)
  rolls it up.

Reports render on the front end with a dedicated accessible template: skip
link, sidebar table of contents, hierarchical headings, status badges conveyed
as symbol plus label (never colour alone), captioned tables with scoped
headers, per-section discussion, and per-section attachments.

= Optional agent layer =

An optional set of Claude Code skills — shipped in the repository under
`agent-layer/`, not in this plugin — authors and maintains these documents:
generating a document from source material, running a gap-driven stakeholder
interview, reconciling a spec against a codebase, pushing an approved queue to
GitHub issues, and exporting a genuinely tagged PDF. The plugin is fully usable
without it, through wp-admin or the REST API directly.

= Not distributed through WordPress.org =

This plugin is not submitted to the WordPress.org plugin directory. This
readme.txt exists because it is the format WordPress tooling reads; install by
dropping the plugin directory into `wp-content/plugins/`.

== Installation ==

1. Copy the plugin directory into `wp-content/plugins/` and activate it.
   Requires WordPress 6.4+ and PHP 8.0+.
2. Activation registers the post types and taxonomies, inserts the default
   terms, grants capabilities, creates the `pppd_agent` role, and flushes
   rewrite rules.
3. Create a report, add sections (assign each a parent report and a section
   type), and publish. Requirement sections receive their `FR-###` ID on first
   publish.
4. For automation, create a user with the `pppd_agent` role and generate an
   application password for it.

There is no build step; the plugin ships plain CSS and JS. Composer is used for
the PHPUnit test suite only — no runtime dependencies.

== Frequently Asked Questions ==

= Can an agent approve its own proposed changes? =

No. The `pppd_agent` role can read, create, edit, and publish reports,
sections, changes, and drift runs, but it cannot delete anything and cannot
approve or reject changes. The approve and reject routes require the
human-only `pppd_approve_changes` capability, and REST updates to a published
section return `403 pppd_approval_required` without it.

= What happens to a section after a client signs off on it? =

It becomes a signed record. Its sign-off meta is read-only over REST for
everyone, and any later content edit automatically flips it to "changed since
approval", recording that re-approval is required.

= Does the plugin store any credentials? =

No. It stores no API keys or tokens of any kind. The GitHub push queue records
queued and pushed state only; the actual GitHub API calls belong to the agent
layer, which uses your local `gh` CLI.

= Is the PDF export actually accessible? =

The front end shows a "Download accessible PDF" link only when a report has a
tagged PDF stored against it, produced by the agent layer's exporter via
Chromium's `page.pdf({ tagged: true })`. When no such file exists, it falls
back to a "Print / Save as PDF" browser button — labelled honestly, because
browser print does not reliably emit a tag tree. The exporter fails loudly
unless the output carries `/StructTreeRoot` and `/Marked true`. The bar is a
screen-reader-navigable tagged PDF; it is not certified PDF/UA.

= What happens when I uninstall? =

Deactivating leaves roles, capabilities, and content untouched. Uninstalling
removes the roles and capabilities but keeps your content — see
`uninstall.php`.

== Changelog ==

= 0.4.0 =
* Sections are stored as core block markup, so they open as editable blocks
  instead of one Classic block. Block patterns added for the narrative,
  requirement, and decision section types.
* Draft visibility: team viewers see draft sections flagged "not part of the
  signed document"; clients and anonymous visitors never do. The outline route
  accepts a `?status=` parameter (team-gated) and returns `post_status` per row.
* Added the `_pppd_public` report flag for publishing a report as a work
  sample, with a cache for public report IDs. Human-only to set, by design.
* Byte-preserving text fallback and attribute detection in content handling.
* One-time migration script for converting existing raw-HTML section content
  (`tools/migrate-to-blocks.py`, dry-run first, with backups and rollback).

= 0.3.0 =
* Client access control: reports require login and a per-client access check,
  enforced at the single-report template, in search, and on every REST read.
* Per-section sign-off records, read-only over REST, auto-flipping to "changed
  since approval" on later edits.
* GitHub push queue storing queued/pushed state only — no credentials in
  WordPress.
* Report and section type registry as the plugin's extensibility spine,
  replacing ad-hoc registration.
* Save-time content-quality enforcement, including accessible-table
  normalisation.
* Abilities API integration: default-deny, read-only outline ability only.
* Report authoring dashboard with a keyboard-operable outline reorder.
* PHPUnit suite covering access, clients, content quality, the GitHub queue,
  the registry, reordering, requirement IDs, and sign-off.

= 0.1.0 =
* Initial release: report, section, change, and drift post types; REST
  controllers for report outlines and traceability matrices; unlimited
  revisions; accessible front-end templates for reports, sections, comments,
  and uploads; activation, deactivation, and uninstall routines.

== Upgrade Notice ==

= 0.4.0 =
Section content moves to core block markup. Existing raw-HTML sections keep
working, but run `tools/migrate-to-blocks.py` (dry-run first) to convert them
into editable blocks.

# Project Ideas / Roadmap

Parking lot for enhancements to the reporting system (this plugin + the `frd`
and `project-audit` skills + the shared report-design language). Not commitments
— captured so they aren't lost. Client-agnostic; no project specifics here.

## Editability / round-tripping

- **Push reports to Google Docs for editing.** HTML reports are great to read and
  export, but non-technical stakeholders want to edit in a familiar surface.
  Idea: a one-way (or round-trip) push of a report to a Google Doc via the Docs
  API, so reviewers can comment/edit there. Open questions: how to map the
  section/requirement structure to Doc headings; whether edits flow back into
  the wiki (the plugin) or the Doc becomes a fork; and whether a Doc export
  preserves accessibility (tagged structure) as well as our own tagged-PDF path.
  **Status: idea only — do not build yet.** Would require Google API setup and
  auth the project doesn't currently have.

## Accessible PDF (partially done)

- **Front-end "Download accessible PDF" now links to a stored tagged PDF**
  (`_pppd_pdf_url` report meta), falling back to an honestly-labelled
  "Print / Save as PDF" browser button when none exists. `window.print()` is no
  longer presented as an accessible download, because browser print does not
  reliably emit a tag tree. *(Implemented 2026-07-07.)*
- **Follow-up: wire `frd export` to upload the tagged PDF and set
  `_pppd_pdf_url`.** The exporter (`export-pdf.mjs`) already produces the tagged
  file; the `export` subcommand should upload it to the report's media and set
  the meta, so the front-end button serves it automatically. Until then the
  meta must be set manually (or via a small REST/WP-CLI step).

## Private sections + redaction-aware export (for project-audit)

- **Personal-evaluation and career-advice sections as toggleable "tabs" in one
  audit document, with a sharing/export function that strips the private tabs.**
  Today these are produced as *separate files* (a shareable retrospective +
  a private companion) to guarantee the private material never travels with the
  shareable one. A nicer future model: one document with clearly-marked private
  sections and an export mode ("share" vs "full") that redacts the private
  sections on the way out — so there's a single source but a safe shareable
  artifact. Requires: a section-visibility flag, and an exporter that honors it.
  Until built, keep producing separate files (safer default).

## Other

- **Per-role review notes as a reusable library.** The audit's role-level
  lessons ("what the PM seat did poorly / better") are reusable across projects.
  Could become a small library the audit skill draws from and appends to.

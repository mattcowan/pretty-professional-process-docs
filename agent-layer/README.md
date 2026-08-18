# Agent layer

The plugin stores and gates process documents. This directory holds the
**optional** [Claude Code](https://claude.com/claude-code) skills that author
and maintain them: turning source material into draft sections, reconciling a
spec against a codebase, and exporting tagged PDFs.

The plugin is fully usable without any of this — via wp-admin, or by driving
the `pppd/v1` REST API from whatever tooling you like. Install the agent layer
only if you want Claude Code to do the authoring.

## What's here

| Skill | Writes into the plugin? | Purpose |
|---|---|---|
| `frd` | yes | Living functional requirements documents: `init`, `interview`, `ingest-meeting`, `drift`, `export`. Owns the REST contract cheat sheet the other skills read. |
| `pppd-sync` | yes | Incremental multi-source updates into an existing report (`ingest`), and pushing the approved GitHub queue to issues (`push-github`). |
| `content-strategy` | yes | Content-strategy reports: audience segmentation, page-to-audience map, editorial backlog, outreach plan. |
| `project-audit` | **no** | Project retrospectives. Writes a standalone HTML file and tagged PDF to a directory — see below. |
| `shared/report-design` | n/a | The design contract every generated report follows, plus `export-pdf.mjs` — the tagged-PDF exporter the plugin front-end's "Download accessible PDF" link serves. |

**`project-audit` is the exception, deliberately.** It never calls the REST
API, needs no credentials, and creates no `pppd_report` — the plugin registers
four report types (`frd`, `user-access-model`, `change-order`,
`content-strategy`) and an audit is not among them. It produces a
self-contained `audit-report.html` plus a tagged PDF in an output directory,
and it ships here because it shares `shared/report-design/` with the rest and
is the source of the interview pattern the other skills reuse. A retrospective
is a point-in-time document with no client sign-off, no approval queue, and
nothing to reconcile against a codebase — the things the plugin exists to
provide. If that ever changes, it becomes a registered report type like the
others.

`frd/references/rest-api.md` is the agent-facing companion to
[`docs/rest-contract.md`](../docs/rest-contract.md). The contract is frozen at
v1 and additive-only; a plugin change that removes or renames anything in it
must update both files in the same change.

## Install

The skills resolve each other by absolute path (`~/.claude/skills/…`), so they
must be installed at the root of your Claude Code skills directory rather than
run from this repo:

```sh
# macOS / Linux
cp -r agent-layer/skills/* ~/.claude/skills/

# Windows (PowerShell)
Copy-Item -Recurse -Force agent-layer\skills\* $HOME\.claude\skills\
```

That yields `~/.claude/skills/frd/`, `~/.claude/skills/pppd-sync/`,
`~/.claude/skills/content-strategy/`, `~/.claude/skills/project-audit/`, and
`~/.claude/skills/shared/report-design/`.

If you already have a `shared/` directory, merge rather than overwrite — only
the `report-design/` subdirectory belongs to this project.

## Configure

Create a WordPress user with the `pppd_agent` role and generate an
[application password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)
for it. The skills resolve credentials in this order:

1. Environment variables `PPPD_URL`, `PPPD_USER`, `PPPD_APP_PASS`
2. A `pppd-agent.env` file at or above the WordPress webroot — **outside the
   document root**, so it is never web-readable
3. Otherwise they ask

```sh
# pppd-agent.env
PPPD_URL=https://example.test
PPPD_USER=pppd-agent
PPPD_APP_PASS=xxxx xxxx xxxx xxxx xxxx xxxx

# Optional: a human account, used only when you explicitly ask an agent to act
# as you (e.g. the block migration in tools/). Day-to-day writes use the agent.
PPPD_ADMIN_USER=you
PPPD_ADMIN_APP_PASS=xxxx xxxx xxxx xxxx xxxx xxxx
```

The plugin itself stores **no credentials of any kind**. GitHub issue creation
runs through your local `gh` CLI, in the `pppd-sync` skill — nothing is held in
WordPress.

## Optional dependencies

- **Node + Playwright** — required only for `export-pdf.mjs`.
  `npm i -g playwright-core` uses your installed Edge/Chrome; full `playwright`
  works too.
- **`gh` CLI** — required only for `pppd-sync push-github`.
- **MCP servers** for meeting notes or document storage — genuinely optional.
  Every skill degrades to file-based sources and says so rather than blocking.

## The rules these skills enforce

Worth knowing before you point an agent at a real client document, because they
are deliberate and the skills treat them as non-negotiable:

- **Agents propose; humans approve.** Publishing is a human act in wp-admin —
  it is what mints requirement IDs and arms the approval lock. Agents create
  drafts and may edit drafts freely; every change to a *published* section goes
  through the `pppd_change` queue. This is server-enforced, not just convention:
  REST updates to published sections return `403 pppd_approval_required`.
- **A signed-off section is never written to.** `signoff.state === "approved"`
  marks a client-signed record; the skills skip it or file a proposal that
  explicitly warns re-approval will be required.
- **Roles, never names.** Generated report content refers to people by role,
  never by name — including people the user names directly. The only proper
  noun allowed is the client or project name, in content only.
- **Client-agnostic tooling.** Project specifics arrive as arguments or live in
  report meta. Nothing client-specific is ever written into a skill file.

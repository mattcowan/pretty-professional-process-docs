# Build Prompt 2 — content engagements (run AFTER Prompt 1 ships)

**Precondition:** Prompt 1 has shipped Phases 1–3 (report-type registry, client access, per-section sign-off + approval lock). This is a **fresh session against the live, finished plugin** — the plugin is now a tool you *use*, not one you build. Running here also exercises and stress-tests the `frd` and new `content-strategy` skills with live feedback.

**Plugin on disk:** `c:\Users\matth\Local Sites\frd-reports\app\public\wp-content\plugins\pretty-professional-process-docs`
**Local env:** http://frd-reports.local
**Skills:** `frd` (system-level), `project-audit`. You will **create** a `content-strategy` skill in this session.
**MCPs:** Granola, Google Drive (for research/source ingestion). Gmail/Calendar unauthorized in headless runs.

All authored content lands in WordPress as **drafts**, respecting the approval lock. Register each new document as a report **type** via Prompt 1's `register_report_type()` — do not hardcode.

## Sequence (interviews are markdown-file driven with follow-up questions; I answer inline)

### 1. Typography Stylist FRD (req 10)
Typography Stylist is a WordPress plugin I built. Produce an FRD for a **website for that plugin**, using the `frd` skill against the live plugin (draft status).
- **Interview me via a markdown file** with follow-up questions to define: the pages the site needs, the content per page, and the look/feel.
- **Research** Typography Stylist's role in the WordPress and typography ecosystems (competitors, positioning, what gap it fills) and feed that into the FRD.
- Output the FRD as drafts in the plugin.

### 2. Content-strategy skill + report (req 11)
- **First design an AI-era content-strategy workflow**, then create a system-level `content-strategy` skill implementing it (mirror the structure of `frd`/`project-audit` — a SKILL.md with staged subcommands + references). Think about what an effective content-strategy creation workflow looks like in the age of AI.
- Then run a **content-strategy interview** (markdown file, I answer inline) and produce the report(s), registered as a `content-strategy` report type. **No client access model needed** for this one.

### 3. Outreach / research strategy (part of content strategy)
Outreach is a **capability of the `content-strategy` skill**, not a separate skill. As part of designing the skill (Step 2), **first research what an AI-age outreach strategy actually needs** — don't assume; investigate current best practice — then bake that into the skill's workflow. The outreach deliverable should at least define: the research method (audience + channel + competitive-landscape research, WP plugin-directory positioning, typography-community channels), the target audiences, the channel plan, and the messaging/positioning for Typography Stylist, grounded in the Step-1 research.

## Accessibility
Same gate as Prompt 1 — any HTML/report output passes AXE clean and follows ATAG. Use `accessibility-audit` / `wordpress-accessibility-patterns`.

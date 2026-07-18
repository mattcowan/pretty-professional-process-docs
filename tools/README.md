# PPPD tools

## migrate-to-blocks.py

One-time, reversible migration of `pppd_section` content from raw HTML to core
Gutenberg block markup (so sections open as editable blocks instead of one
Classic block). See the module docstring for conversion rules and flags.

Order of operations — dry-run first is mandatory:

```sh
python3 tools/migrate-to-blocks.py --reports 46,47 --census   # what's in there
python3 tools/migrate-to-blocks.py --reports 46,47            # dry-run: diffs + backup
# read the diffs, then:
python3 tools/migrate-to-blocks.py --reports 46,47 --apply --backup tools/backups/<file>.json
# undo:
python3 tools/migrate-to-blocks.py --rollback tools/backups/<file>.json
```

Notes:

- Needs `PPPD_ADMIN_USER` / `PPPD_ADMIN_APP_PASS` in `pppd-agent.env` (beside
  the site's `app/` dir): the plugin returns `403 pppd_approval_required` for
  published-section updates from the agent role by design.
- `tools/backups/` is gitignored; every run (including dry-runs) writes a full
  content backup there. WP revisions are the second safety net (the plugin
  keeps them all).
- Sections with `signoff.state === approved` are skipped, always.
- Tables convert to `wp:html` (not `wp:table`) on purpose: the core table
  block cannot represent `<caption>` or scoped row headers, which the report
  accessibility contract requires.

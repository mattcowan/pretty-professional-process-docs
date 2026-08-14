# PPPD tools

## make-pot.php

Regenerates `languages/pretty-professional-process-docs.pot` from the plugin's
translatable strings.

```sh
php tools/make-pot.php
```

`wp i18n make-pot . languages/pretty-professional-process-docs.pot` is the
canonical tool and should be preferred where WP-CLI is available; this script
exists so the POT stays regenerable without it. It tokenizes the PHP with
`token_get_all()` (not regex), covers the full `__`/`_e`/`_x`/`_n`/`_noop`
family plus the `esc_html_*` / `esc_attr_*` wrappers, carries `translators:`
comments through, and skips any call whose text domain isn't this plugin's.
`agent-layer/`, `tools/`, `tests/`, and `vendor/` are not scanned.

Run it after adding or changing any user-facing string.

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

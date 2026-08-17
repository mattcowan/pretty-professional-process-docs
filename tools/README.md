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
comments through (attached by line, so a comment above a wrapped call such as
`esc_html( _n( … ) )` or an array value `'x' => _n_noop( … )` still lands),
emits `#, php-format` on every string carrying a printf placeholder, and skips
any call whose text domain isn't this plugin's.

Directories not scanned — keep this list identical to `$skip_dirs` in the
script: `.git/`, `agent-layer/`, `languages/`, `node_modules/`, `tests/`,
`tools/`, `vendor/`.

Run it after adding or changing any user-facing string.

**It exits non-zero and names the file and line whenever the POT would be
incomplete** — an unreadable source file, a non-literal msgid, a missing text
domain, or a failed/short write. A green exit means the POT on disk is
complete; treat a red one as "do not commit this POT".

```sh
php tools/make-pot.php && msgfmt --check -o /dev/null languages/*.pot
```

That second command checks the POT's **syntax**, and nothing more. It cannot
check placeholders: `--check` does imply `--check-format`, but a POT's every
`msgstr` is empty, and gettext treats an untranslated entry as nothing to
validate. The `#, php-format` flags this script writes are for later — they
travel into each translator's `.po`, and the check that matters runs when those
are compiled:

```sh
msgfmt --check -o /dev/null languages/pretty-professional-process-docs-fr_FR.po
```

That is what rejects a translation which drops or reorders `%1$s` / `%2$s`
before it reaches `printf()` at runtime.

Known gap versus WP-CLI: JavaScript and block-editor strings are not extracted.
This plugin has none today — `assets/js/*.js` contains no `wp.i18n`, `__()`, or
`_n()` call, and no script declares `wp-i18n` as a dependency — but if that
changes, switch to `wp i18n make-pot`.

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

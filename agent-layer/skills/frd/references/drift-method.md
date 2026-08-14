# Drift Method — reconciling code against the FRD

`frd drift` answers: is the build still plumb with the spec? Read-only against
the repo — verify `git status --short` is byte-identical before and after.

## Procedure

1. `GET /pppd/v1/reports/{id}/traceability` — the requirement list with
   existing `code_refs` / `test_refs`.
2. For each requirement, in order:
   a. If it has code_refs: confirm each path exists and still plausibly
      implements the requirement (read the file, don't trust the name).
   b. If refs are missing/stale: search the repo for the feature (by domain
      terms from the requirement title/content, route/CPT/hook names, CSS
      selectors — whatever the stack suggests).
   c. Check for tests covering the acceptance criteria (test dirs, spec
      names, e2e flows).
3. Verdict per requirement:
   - `covered` — implementation found AND at least one acceptance criterion
     is demonstrably tested or verifiable.
   - `partial` — implementation found but incomplete against the content, or
     no test coverage of any criterion. `notes` must say which half is
     missing.
   - `missing` — no implementation found. (Say where you looked.)
4. **Orphan sweep** (the reverse direction): inventory the repo's major
   feature surfaces (post types, REST routes, admin pages, front-end
   components, integrations, CLI commands). Anything significant with no
   describing requirement → an `orphan` item: `req_id: ""`,
   `notes: "<feature> exists at <paths>; no requirement describes it"`.
   Orphans are how reverse-engineered FRDs discover what the spec forgot —
   report them prominently; each is a candidate new requirement
   (`ingest-meeting`-style proposed change, or an interview question if the
   intent is unclear).
5. `POST /pppd/v1/reports/{id}/drift` with summary + items. The summary leads
   with the counts (X covered / Y partial / Z missing / N orphans) and the
   single riskiest gap.

## Rules

- Never mark `covered` from a filename alone; open the file.
- Code refs you discover go in the drift item's `code_refs` — do NOT edit the
  section's meta directly; propose a `pppd_change` to update stale refs.
- Respect scale: on big repos sample the acceptance criteria (state which were
  checked) rather than silently checking one and implying all.
- The drift run is append-only history. Never delete old runs; trend beats
  snapshot.

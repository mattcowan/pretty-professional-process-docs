# Friction Detection — expectation-mismatch heuristics

Run these over source docs, meetings, and the draft FRD. Every hit becomes an
`at-risk` flag with a one-line "Friction flag" callout naming both sides of
the mismatch — never smooth a conflict into vague prose. False positives are
cheap; silent mismatches cost launches.

## Language signals (in docs/meetings)

- **Ambiguity words**: "etc.", "and so on", "similar to", "like the old
  site", "standard", "typical", "as needed", "TBD", "we'll figure it out",
  "should be easy". Each one hides an undocumented expectation.
- **Unquantified quality**: "fast", "modern", "clean", "user-friendly",
  "accessible" with no criterion attached.
- **Future-tense promises without owners**: "there will be", "it will
  support" — who builds it, and is it in scope?

## Structural signals (in the draft FRD)

- Requirement with zero acceptance criteria.
- Decision with no owner or date.
- Integration with only one side described (data goes IN to the CRM — what
  comes back? who owns the account? what happens when it's down?).
- A feature area with requirements but no non-functional coverage (search
  with no performance note; forms with no spam/deliverability note; anything
  user-facing with no accessibility note).
- Navigation/sitemap referenced but no signed-off sitemap exists (classic
  late-project failure: missing root pages discovered at nav-build time).
- Migration mentioned without a content inventory or owner.

## Cross-source signals (client-said vs dev-planned)

- Client materials name a feature the dev plan doesn't (or vice versa).
- Same term used differently ("member" the CRM object vs "member" the WP
  user).
- A meeting decision that never landed in any document.
- Scope items that moved between meetings without an explicit descope
  ("gave the client everything they asked for, whether designed or not" —
  detect by matching requests in meetings against designs/specs).
- Deadline discussed before scope is enumerated (deadline-first planning is
  the root of most trust damage; flag it whenever dates precede requirement
  lists chronologically).

## Email / launch special case

Email (transactional, deliverability, DNS) and environment cutover are
disproportionately unpredictable. If the project sends email or changes DNS,
force a requirement section for it even if no source mentions it, status
`at-risk`, with the note that this class of problem is hard to predict and
needs a pre-launch checklist.

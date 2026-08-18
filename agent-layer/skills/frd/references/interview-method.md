# Interview Method — turning FRD gaps into stakeholder questions

`frd interview` converts report gaps into questions the right person can
actually answer. One doc per stakeholder role; short; every question traceable
to a section/requirement ID.

## Gap → question mapping

| Gap found | Ask (role) | Question shape |
|---|---|---|
| `at-risk` section / friction flag | whoever owns the mismatch (client sponsor or dev lead) | Present both readings: "Doc A implies X; the plan implies Y. Which is right, and who decides?" |
| Requirement without acceptance criteria | client-side owner | "How would you check this works? What would make you reject it?" |
| Decision without owner/date | project lead | "Who has the final call on this, and by when do we need it?" |
| Integration half-described | technical contact for that system | Data direction, failure behavior, account ownership, rate limits, sandbox access |
| Missing NFR (perf/a11y/email/SEO) | dev lead + client sponsor | Concrete target, not preference: "What page-load budget / WCAG level / email volume are we committing to?" |
| Vocabulary collision | both sides at once | "When you say <term>, do you mean <A> or <B>? We found both usages." |
| Unrecoverable intent (reverse-engineered FRDs) | anyone who was there | "The code does X. Was that the requirement, a workaround, or an accident worth fixing?" |

## Rules

- Group by stakeholder ROLE (client sponsor, dev lead, designer, SEO,
  content), not by report order. People answer what's addressed to them.
- ≤ 10 questions per role per round. Rank by launch risk; drop the rest to a
  "later" list at the bottom.
- Every question cites its source: `(FR-014)` or `(§ Decisions log)`.
- Offer answer options where the space is knowable — multiple-choice gets
  answered same-day, essays never do.
- Questions must be answerable by the addressee alone. If it needs a meeting,
  it's a decision item, not an interview question — file it under Decisions
  log instead.
- After answers arrive, do NOT edit published sections directly: convert each
  answer into a `pppd_change` (source = "interview: <role>, <date>") so the
  review queue stays the single audit trail. Answers that target sections
  still in draft may be written into the draft directly (drafts are the
  agent's workspace; the human reviews them before publishing).

## Inline mode (single stakeholder)

When `--inline` is passed or the commissioning user is the only stakeholder
(e.g. an FRD for the user's own product), per-role docs add ceremony without
value. Emit ONE `interview.md` instead, following the shared inline-interview
pattern (as used by the project-audit and content-strategy skills):

- Open with the blunt-honesty preamble used by those skills, then the
  project name.
- **Part A — from the evidence:** 5–12 questions, each citing the section,
  requirement, research finding, or repo fact that raised it. Prefer questions
  that distinguish competing options over yes/no confirmations. Every question
  ends with a `> **Answer:**` marker on its own line.
- **Part B — standard bank**, grouped by the roles the user is standing in for
  (client sponsor, dev lead, designer, SEO, content) so coverage still spans
  every role's concerns. Do NOT re-ask anything already answered in the
  conversation or sources — reproduce it as
  `> **Answer (previously provided):** …` with at most one follow-up.
- The file on disk IS the state — resumable across sessions. Stop after
  writing it; the user answers inline. Do not invent answers.
- Answer-conversion rule above still applies: answers become draft content or
  `pppd_change` proposals (source = "interview: inline, <date>"), never direct
  edits to published sections.

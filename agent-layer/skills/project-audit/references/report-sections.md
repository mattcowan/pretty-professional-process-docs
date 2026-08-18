# Audit Report — fixed section structure

The report uses these sections, in this order. Every section draws from both
evidence (git/docs/meetings) and interview answers; cite which. If a section
has genuinely nothing, say so in one sentence — never pad.

Sections #3–#20 each have a question behind them in the Part B bank of
`interview-template.md`, which carries the mapping. The two are one contract:
changing the bank without changing this file (or the reverse) leaves a section
that reads like team input but had none. Sections #1 and #2 are synthesis and
evidence respectively, and have no bank question by design.

1. **Summary** — five to eight sentences. The project in one paragraph: what
   was built, how it went, the one-line verdict, the top three actions.
2. **Project facts** — table: timeline, team size/roles, commit count, major
   subsystems, tools used. Pure evidence, no opinion.
3. **What went well — keep doing this** — including team resilience and
   technically difficult wins. Name practices, not just outcomes, so they are
   repeatable.
4. **What could have gone better — and why** — causal chains, not blame lists.
   (Pattern to watch for: undefined scope → wrong deadlines → lost client
   trust → team stress. Trace each problem to its root.)
5. **Biggest challenge**
6. **Recurring bottlenecks and pain points** — include tool sprawl findings
   (count the systems; note where a "project archaeologist" would fail to find
   decisions). Accountability gaps belong here — see style-rules on
   individuals.
7. **Communication: what worked** — internal and client-facing.
8. **Communication: what broke down**
9. **Roles and responsibilities** — were they clear; self-management vs
   team-level clarity.
10. **Information access** — did people have what they needed; what it cost to
    get it ("I kept demanding it" is a cost, not a success).
11. **Process improvements that would have saved rework** — FRD/spec gaps,
    misunderstandings that a shared source of truth would have caught.
12. **Scope and client-request management** — scope changes accepted without
    trade-off conversations; designs vs delivered.
13. **Recurring issues pointing at process problems** — cross-discipline
    misses (e.g. a missing sitemap surfacing as nav gaps at launch); name the
    checkpoint that would have caught each one.
14. **QA effectiveness** — what QA existed, when it happened, what should
    change.
15. **Client review cycles** — cadence, effectiveness, pain points.
16. **Documentation adequacy** — what existed, what was missing, what to
    standardize.
17. **Launch risks** — what could have been identified earlier; what was
    genuinely unforeseeable (say so honestly).
18. **Tools and systems** — helped vs hindered; consolidation recommendation.
19. **Start / Stop / Continue** — three short lists. Sustainability items
    (burnout, unrealistic promises) go under Stop and are stated plainly.
20. **Action items before the next project** — numbered, concrete, each with
    an owner-shaped subject ("PM lead:", "Dev lead:") and a checkpoint where it
    bites (e.g. "sitemap signed off before nav build starts"). This section is
    the deliverable inside the deliverable.

Statuses/badges are not used in audit reports (they're FRD vocabulary); the
audit report uses only the callout patterns (`callout--risk` for unresolved
risks, `callout--evidence` for evidence citations).

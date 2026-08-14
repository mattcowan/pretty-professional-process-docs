# Interview Doc — template

Generated as `interview.md` in the output directory. Structure below; Part A
questions are written fresh from Stage 1 findings, Part B is the standard
bank. Answer markers (`> **Answer:**`) let the user reply inline.

---

## Preamble (include verbatim, then the project name)

> **Please be as blunt and honest as possible.** This document is raw input,
> not the report. Nothing here is quoted directly in the final report without
> being paraphrased and de-identified; criticism of individuals will be
> converted to statements about roles and process. Candor here is what makes
> the final report worth reading. Write in fragments if you like — speed over
> polish.
>
> If a question doesn't apply, write "n/a". If a question misses the real
> issue, ignore it and write what actually mattered.

## Part A — questions from the evidence

Each question MUST cite what raised it, so the user can confirm or correct the
inference. Format:

### A1. <short title>
**Evidence:** <what the mining found — e.g. "38% of commits after <date> are
fix-typed, and commit cadence triples in the final month.">
**Question:** <the specific question this raises>

> **Answer:**

Rules for Part A:
- 5–12 questions. Only what the evidence genuinely raises.
- Prefer questions that distinguish between competing explanations, not
  yes/no confirmations.
- Include at least one question probing anything the docs/meetings contradict
  in the git story.

## Part B — standard bank

Sixteen questions covering report sections #3–#20 (`report-sections.md`). Two
of them each cover a pair of sections that people answer in one breath —
communication (#7/#8) and the review loops (#14/#15) — which is why the count
is sixteen rather than eighteen.

**The bank and the report sections are one contract.** Every section from #3 to
#20 must have a question behind it. If you drop a question, either drop the
section it feeds or say plainly in that section that nobody was asked — never
let a section get written from evidence alone while reading as though it came
from the team.

Ask open questions. A bank item answerable with "yes" has failed, by the same
rule Part A follows.

For any item the user already answered elsewhere (earlier conversation,
provided notes), do NOT re-ask: reproduce the answer under the question as
`> **Answer (previously provided):** …` and add at most one follow-up if the
evidence complicates it.

| # | Question | Feeds |
|---|---|---|
| 1 | What went well enough that you'd deliberately repeat it? | #3 |
| 2 | What went badly, and what actually caused it? | #4 |
| 3 | What was the hardest single problem on this project? | #5 |
| 4 | What slowed you down over and over, rather than once? | #6 |
| 5 | Where did communication land well, and where did it fail — internally and with the client? | #7, #8 |
| 6 | Did people know what they owned? Where was that unclear? | #9 |
| 7 | What did you need to know and struggle to find out — and what did it cost you to get it? | #10 |
| 8 | What would have prevented the rework you ended up doing? | #11 |
| 9 | How were scope changes and new client requests handled — and what should have happened instead? | #12 |
| 10 | Which problems showed up across more than one discipline (QA, content, design, dev)? | #13 |
| 11 | Were the review loops — QA and client review — catching things early enough to matter? | #14, #15 |
| 12 | What documentation existed, what was missing, and what would you standardise? | #16 |
| 13 | Which launch risks were foreseeable, and which genuinely weren't? | #17 |
| 14 | Which tools earned their place, and which just added somewhere for decisions to hide? | #18 |
| 15 | Start, stop, continue — one line each. | #19 |
| 16 | What has to change before the next project starts? | #20 |

Render each as its own block, in this order, with a `> **Answer:**` marker.

## Closing

> Anything else the questions above didn't surface?

> **Answer:**

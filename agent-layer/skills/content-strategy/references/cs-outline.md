# Content Strategy Outline — canonical structure

The living content strategy is organized so every part is individually
addressable, statused, and traceable. Sections map 1:1 to `pppd_section` posts.
Section types: `narrative` (positioning, plans, guidance prose) and `decision`
(a strategic choice with owner + date). Report prefix `CS`; report type
`content-strategy`. Everything created here is `status: draft` — a human
promotes it. Section bodies are core block markup — see the cheat sheet in
`../../frd/references/rest-api.md` (§ "Section content is core block markup").

## Top-level outline

1. **Executive summary & positioning** (narrative) — in ≤ 300 words: what the
   brand is, the one thing it wants to be known for, and the positioning line
   (audience + problem + why-this-brand). If a positioning claim can't be
   backed by proof in the catalog below, mark it `at-risk` — a claim with no
   demonstration is a liability, not a headline.
2. **Audience segments** (narrative) — one subsection per segment: who they are
   (by ROLE, never name), what they came to decide, what would make them trust
   or bounce, the single action we want from them. Derived per
   `audience-segmentation.md`. Explicitly list who we are NOT for.
3. **Competitive & market landscape** (narrative) — who else serves these
   audiences (direct and adjacent alternatives), what gap the brand fills, and —
   critically — **where the alternatives are discussed** (the community threads,
   reviews, and roundups that name them). Include **prompt-research findings**:
   what AI assistants currently recommend for the audience's core questions and
   which sources they cite (those cited sources are outreach targets; the weakly
   answered questions are backlog opportunities). Grounds positioning in
   evidence, not assertion. Method: `references/outreach.md` Step 1. Any claim
   not backed by research is marked `at-risk`.
4. **Page-to-audience map** (narrative, table) — the site inventory: every
   current and planned page, its ONE primary audience, its ONE primary CTA, and
   its status (exists / revise / new / retire). No page may list "everyone" as
   its audience; that is the flag `audience-segmentation.md` exists to catch.
5. **Messaging & positioning matrix** (narrative, table) — one row per audience
   segment; columns: the problem *in their words*, the one value prop that lands,
   the proof that demonstrates it (must reference a proof-catalog entry — a value
   prop with no proof is `at-risk`), and the channel + format where they meet it
   (maps to the channel & outreach plan). This is how one product says a
   different first sentence to each audience without diluting the positioning.
   Format per `references/outreach.md` Step 3.
6. **Portfolio / proof catalog** (narrative children) — the evidence that
   *demonstrates* expertise (see `ai-era-guidance.md` "demonstrate, don't
   claim"). One child per proof piece; per-entry format below.
7. **Content backlog** (narrative children) — the editorial pipeline: articles,
   guides, and answer pages worth writing, prioritized. One child per item;
   per-entry format below. Includes **answer pages targeting the prompt-research
   gaps** from section 3 (questions the LLMs currently answer badly).
8. **Required-page plans** (narrative children) — pages the strategy demands
   that don't exist yet. Always includes an **AI-disclosure page** plan (see
   `ai-era-guidance.md` for what it must cover: tools used, the human gate,
   client-data handling). May include an about/positioning page, a
   services/offer page, a contact/inquiry page. Each carries a brief and the
   same authoring invoke-prompt shape as a backlog item.
9. **Channel & outreach plan** (narrative children) — the brand's whole
   distribution plan across **three lanes** (see `references/outreach.md` Step 2):
   **owned** (channels the brand maintains — the site, blog, docs, its own
   social), **community / earned** (participation in the validation-layer venues
   where recommendations are actually won), and **partnership / amplification**
   (integrations, guest content, roundups/newsletters, creator seeding, PR,
   directory listings). One child per channel/target. Each states its **lane**,
   the specific target, **why** (which segment, grounded in section 3's
   research), the content type/format that fits, cadence, the **norm/etiquette**
   for that venue, and a first concrete action. Owned entries also state the
   crawlability-vs-feed distinction (see `ai-era-guidance.md`) and platform
   conventions — for **LinkedIn**: the "link in comments" convention (post body
   carries the substance, link goes in the first comment, because feeds suppress
   outbound links; the post must stand alone as value). Community/partnership
   entries obey the evidence & ethics rules in `references/outreach.md` — no
   astroturfing, no undisclosed promotion, verify the venue is active.
   If the product lives in a directory (app store, plugin/theme repo, package
   index), include a **directory-positioning** entry per `references/outreach.md`
   ("Directory / platform positioning"): the listing's ranking mechanics, a
   review-solicitation action, and how the listing links out to the site.
10. **Decisions log** (decision children) — one child per strategic choice:
    what was decided, by whom (ROLE), when, and the source. Open choices are
    `status: at-risk`. Examples: primary positioning, which audiences are in vs
    out of scope, disclosure posture on client work, publishing cadence, which
    outreach lanes/channels are in scope.
11. **Cross-references to the companion FRD** (narrative) — the content strategy
    says WHAT to say and to whom; the FRD says what the SITE must DO. Where a
    content plan needs a site capability (a filterable portfolio index, an
    inquiry form, an AI-disclosure page template, structured-data output, an
    llms.txt / answer-shaped-docs requirement), link the FRD requirement by its
    `FR-###` ID rather than restating it. If no companion FRD exists, list the
    capabilities this strategy assumes as an "FRD hand-off" list so one can be
    created.
12. **Open questions** (narrative, `status: at-risk`) — feeds
    `content-strategy interview`. Every "who is this page for?", every unproven
    claim, and every unverified channel lands here until resolved.

## Per-entry format — portfolio / proof catalog (#4)

Each proof piece is one child section:

- **Title** — the piece by its outcome or subject, not "Project 3".
- **Angle** (content) — what this piece proves and to WHICH audience segment;
  the story it tells (context → role → approach → outcome). One idea: this is
  evidence for a specific claim in the positioning.
- **Disclosure / NDA status** (content, stated plainly) — one of: `owned`
  (the brand's own work, showable), `client — cleared` (client work with
  written permission to show), `client — NDA` (blocked; may only appear
  anonymized/aggregated), `unknown` (treat as blocked until confirmed).
  Nothing marked NDA or unknown becomes a live page. This status is the gate.
- **Invoke-prompt** (content) — a ready-to-paste line naming the authoring
  skill + agent tier + effort so a human can dispatch the work:
  > Invoke `portfolio-piece` to write this up (owned-work / cleared only).
  > Agent tier: Sonnet, medium effort (~single focused session). Source:
  > `<repo-or-notes path passed as an argument>`. Deliver as WordPress draft.

  Route client-referencing pieces through `devnotes` instead (it carries the
  anonymization + PASS gate `portfolio-piece` deliberately omits).

## Per-entry format — content backlog (#5)

Each backlog item is one child section:

- **Title** — the working headline, shaped for retrieval where it fits (an
  answer-style or error-string title when the piece answers a searchable
  question — see `ai-era-guidance.md`).
- **Brief outline** (content) — the piece's job (which audience, which question
  it answers, the one action it drives) plus a 3–6 bullet skeleton. Note the
  primary keyword/question the piece should be findable for.
- **Starter prompt** (content) — a ready-to-paste line invoking an authoring
  skill:
  > Invoke `blog-writing` with this outline as the starting prompt. Agent tier:
  > Sonnet, low–medium effort (~one session). Deliver as an accessible
  > Gutenberg draft. Publishing stays human.
- **Agent / effort estimate** (content) — model tier + rough session count, so
  the backlog doubles as a capacity plan.
- **Priority** — rank by (audience value × how underserved the question is),
  not by how easy it is to write.

## Per-entry format — channel & outreach plan (#9)

Each channel/target is one child section:

- **Title** — the specific venue or channel (the niche subreddit by name, the
  brand's own LinkedIn, a named trade publication to pitch, a named showcase
  site), not a category like "social" or "PR".
- **Lane** (content) — one of `owned` / `community-earned` / `partnership-amplification`.
- **Why** (content) — which audience segment this reaches and the Step-1 evidence
  that it's where they are (cite the research); if unverified, status `at-risk`.
- **Format & cadence** (content) — the content type that fits this venue and how
  often. For owned feeds, note crawlability-vs-feed and platform convention.
- **Norm / etiquette** (content) — how one behaves here; the register that gets
  received vs. banned. Community/partnership entries must respect the evidence &
  ethics rules in `references/outreach.md` (participate as a peer, disclose
  affiliation, no astroturfing/undisclosed promotion, verify the venue is live).
- **First action** (content) — one concrete, ready-to-do next step, specific
  enough to do today (e.g. "answer the 3 open questions on <topic> in
  <community>," "pitch the <feature> story to <publication> with a 30s demo,"
  "solicit 5 genuine early reviews").

## Writing rules

- Follow `../shared/report-design/report-design.md` for anything rendered, and
  status uses text + symbol + shape, never color alone.
- Short sentences. Concrete nouns. A strategy nobody reads is a strategy nobody
  follows.
- Every recommendation traces to a source (a mined page, an interview answer, a
  companion-FRD requirement); mark inference as inference.
- Roles, never names. The only proper noun is the client/brand, in content.
- Never delete client-visible history: retired pages move to the map with
  status `retire`, superseded plans go through the change queue.

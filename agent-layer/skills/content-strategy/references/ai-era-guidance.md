# AI-Era Content Guidance — GEO and the LLM-retrieval era

Content is now read by two kinds of reader: humans, and the retrieval systems
(search crawlers and LLMs) that decide whether a human ever sees it. GEO
(generative engine optimization) is writing so that an answer engine can lift
your content as *the* answer and attribute it to the brand. This does not mean
gaming a model; it means being the clearest, best-attributed source for a
specific question. The guidance below shapes every page and backlog item.

## Answer-shaped content

Write content that directly answers a real question a person would ask, in the
first breath, before the throat-clearing.

- **Lead with the answer, then support it.** State the conclusion in the first
  sentence or two; follow with the reasoning, caveats, and detail. Retrieval
  systems extract the answer near the top; humans who bounce still leave with
  it. The inverted pyramid beats the slow build here.
- **One question per page or section.** A page that answers one question
  cleanly is retrievable; a page that meanders across five answers none of them
  well. Use a clear question-style heading and answer directly beneath it.
- **Self-contained passages.** Write sections that make sense lifted out of
  context — a retrieval system quotes a paragraph, not the whole page. Restate
  the subject rather than relying on "it" pointing three paragraphs up.
- **Structure the answer** with real semantic HTML: a heading that poses the
  question, a direct answer, then a list or table for the specifics. Structured
  answers are both more accessible and more extractable.

## Searchable, error-string-style titles where relevant

Titles are retrieval bait — match how people actually search, not how a brand
wants to sound.

- **Use the searcher's words.** When a piece answers a findable question, title
  it as the question or the query ("How do you migrate X without downtime?"),
  not as a clever phrase. The literal phrasing a person types is the title that
  gets retrieved.
- **Error-string / exact-symptom titles** for technical or troubleshooting
  content: title the piece with the literal error message, code, or symptom
  people paste into a search box ("`Fatal error: Allowed memory size exhausted`
  — what it means and how to fix it"). The exact string is the highest-intent
  query there is; owning it earns qualified traffic and citations. Use this
  where it fits the content; do not force it onto pieces that aren't answering a
  symptom.
- **Not everything is searchable.** Positioning and brand pages are not queries;
  title those for the human and the brand. Match the title strategy to whether
  the piece answers a search or makes an impression.

## Crawlability vs feed placement

These are two different distribution surfaces and content is shaped differently
for each — do not confuse them:

- **Crawlable content** lives on pages a search crawler and an LLM can fetch and
  index: the brand's own site, publicly readable, server-rendered or
  crawler-visible, with clean URLs and structured data. This is content you
  *own* and that compounds — it can be retrieved and cited months later. The
  goal here is to be indexable and answer-shaped.
- **Feed-placed content** lives inside a platform's feed (LinkedIn, social) and
  is optimized by the platform's engagement algorithm, not a crawler. It is
  often *not* reliably crawlable, is ephemeral, and is frequently penalized for
  outbound links in the post body. The goal here is engagement and reach within
  the platform, with the substance living in the post itself.
- **The practical split:** put content you want *found and cited over time* on
  the crawlable site; use feeds to *point people to it*, keeping the feed post
  valuable on its own. On LinkedIn specifically, the "link in comments"
  convention exists because body links suppress reach — the post carries the
  value, the link goes in the first comment (see the channel plan in
  `cs-outline.md`).

## AI-disclosure pages

An AI-disclosure page is trust infrastructure in the AI era: it tells clients
and readers, plainly, how AI is used in the work. Plan one for any brand that
uses AI in its delivery. It must cover:

- **Tools used** — which AI tools are part of the workflow, and for what
  (drafting, research, code, images). Specific enough to be honest, not a
  vendor list.
- **The human gate** — the explicit statement that AI output is reviewed,
  edited, and approved by a human before it ships; nothing goes to a client or
  goes live unreviewed. Name the checkpoint, not the person.
- **Client-data handling** — whether client data is fed to AI tools, which
  tools, what is excluded, and how confidentiality and NDA-covered material are
  kept out of third-party systems. This is the paragraph clients actually read.
- Optionally: the brand's stance (why it uses AI, where it draws the line, what
  it will not automate). Write it in plain language; this page's job is candor,
  not marketing.

Keep it a real, crawlable page with a stable URL — it is both a trust signal to
humans and a citable answer to "does this brand disclose AI use?".

## Demonstrate expertise, don't claim it

The AI era is drowning in generic claims of expertise; the differentiator is
proof. Content should *show the work*, not assert the adjective.

- **Show, don't assert.** Replace "we are experts in X" with a piece that solves
  a hard X problem in front of the reader — the walk-through, the before/after,
  the actual decision and its trade-offs. Competence is demonstrated by
  specificity a pretender couldn't fake.
- **Specifics over adjectives.** Real numbers, real constraints, real dead ends
  that were navigated. "Cut the query from 2.1s to 180ms by X" proves more than
  "performance-focused". Generic content reads as AI-generated filler and earns
  neither trust nor citations.
- **This is why the proof catalog exists.** Every positioning claim should map
  to a proof piece that demonstrates it (`cs-outline.md` #1 → #4). A claim with
  no demonstration is flagged `at-risk`, not published.

## Accessibility is not separate from this

Answer-shaped, well-structured, semantic content is *the same content* that is
accessible and retrievable. Proper heading hierarchy (no skipped levels), real
lists and tables with `scope`, alt text that conveys the image's information,
and no meaning carried by color alone serve the screen-reader user and the
retrieval system alike. Write it once, correctly, and it works for every reader
— human or machine.

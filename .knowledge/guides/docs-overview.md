# How to write an OVERVIEW — the standard

This guide **is the standard** for `../OVERVIEW.md`. Shipped and versioned by `knowledge-template`.
**The template is `../OVERVIEW.md` itself** — start from it and fill each section.

`OVERVIEW.md` is **the one doc written for a stakeholder**. Everything else in `.knowledge/` serves an
agent: `BRIEF.md` orients, `CODEMAP.md` locates, `prd/` contracts. None of them ever shows the platform.

**Write it as if a product owner is reading it to understand the product they are about to sell.** It
answers what the parts are, how work flows between them, and what governs it. Someone should finish it able
to explain the product in a meeting without ever having opened the code.

**Only what a customer could buy or touch.** No environments, no test strategy, no deployment, no internal
tooling — none of that is the product. An admin surface counts if it is part of the offering people
actually use. If a line would not survive being read aloud to a prospect, cut it.

**Where an internal-sounding mechanism has a customer-facing consequence, name the consequence.** A new
seller being held in a sandbox is engineering's word for it; what a prospect needs to know is that *new
sellers cannot bill or deliver until they are approved*. Same rule, and only one of the two belongs here.

## Say once that this is the design, not the inventory

Every line below is written in the present tense — *a lead clears*, *an expired licence pauses the
agreement* — because that is how you describe a product. But a young platform's overview describes parts
that are built beside parts that are only agreed, and present tense flattens the two into one claim: **it
all works today.** In the one document that gets forwarded to a customer, that is the most expensive
sentence in the file, and nobody wrote it.

The fix is not status on the parts — see below, it rots in a week and this is the file nobody re-reads.
**It is one line under the title, and the template carries it:** this describes the platform as designed,
and each contract's rows record what is proven today. Written once, it never goes stale, and it converts
every present tense underneath from a claim into a design.

**Say where the line falls, or the disclaimer says nothing.** "Some of this may not be built" covers a
product that is five per cent done and one that is ninety-five per cent done equally, and a reader who
cannot tell which has been warned rather than informed. You do not fix that with a status column — you fix
it by naming **the two homes the links already point into**: ratified contracts in `prd/`, proposals in
`prd-drafts/`. That is not a build state anyone has to maintain, it is where the file sits, so it cannot go
stale; and a reader who notices every link going to one of them has learned the thing a percentage would
have told them.

Tested: a reader given an overview whose parts were two-thirds unbuilt came away believing the whole
platform shipped. Nothing in the document was false. Nothing in it was honest either. Tested again after
the framing line: two cold readers both answered "no, and the document told me so" — and both then asked
for a status column, having worked the answer out for themselves from the link targets.

## Name what the customer actually touches

**A reader who finishes this should be able to picture using it.** The parts and the flow describe a
machine; they do not say whether a buyer signs in to a dashboard, an operator works a queue, or a seller
integrates once and never logs in again. That is the first thing a product owner needs and the last thing
an engineer thinks to write, because to them it is obvious.

So: **one line per audience, naming the surface they touch and what they do there.** No screenshots, no
navigation, no feature list — the surface and its purpose. If an audience touches nothing (a party the
platform acts *on* rather than *for*), say that too; it is just as clarifying.

**Surfaces, not modes.** The ban on environments applies here hardest, because this section invites the
leak: a sandbox, a staging tenant or a dry-run flag is a thing engineering built, not a thing a customer
buys. If it has a customer-facing consequence, that consequence belongs in `What governs it` as a rule —
never here as something you use.

```
✅ Buyers and sellers each get a workspace — set terms, watch what cleared, get paid.
✅ Sellers send traffic in through one integration; most never open the workspace day to day.
❌ The Vue SPA exposes participant and operator routes behind Inertia middleware.
❌ Dashboard with real-time analytics, reporting suite, notification centre, and more.
```

The first two tell a stranger what owning this product feels like. The third is written for the wrong
reader; the fourth is a brochure and says nothing.

## It has to stand alone

**This is the one doc that leaves the building.** It gets forwarded to a new hire, a candidate, an
investor, someone in sales — people with no access to `BRIEF.md` and no reason to want it. So it opens by
saying **what the product is, who it is for, and how it makes money**, in three sentences, before anything
else.

Yes, that overlaps `BRIEF.md`. **Take the overlap.** "One fact, one home" protects facts that agents must
not restate in two places and let drift; three sentences of orientation on a document that is read by
strangers is the price of it being usable at all. Without them a reader can follow every step of the
machine and still not know what business they are looking at.

Tested: handed this document alone, a reader worked out the mechanics correctly and still could not say
**how the product makes money, or which industry it serves.** Both were absent, and both are the first
things anyone asks.

**If the commercial model isn't settled, write that** — one line, plainly. Never invent one, and never
leave the question unanswered by silence.

**Every term you use in those three sentences must be findable below, or defined where you use it.** Name a
company, a surface or a mechanism the reader then cannot locate in the diagram or the list, and you have
manufactured a question instead of answering one. **This binds hardest on the revenue mechanism: if the
product makes money through something, that something is on the diagram** — whatever the box budget says.
A map that omits how the business earns is describing a machine, not a product.

Tested: a reader given the opening alone asked what "the operating company" was and where "the open
exchange" lived, because neither appeared anywhere else in the document. Both were real parts of the
product; both had been left off the map.

## Who it is for

**A product manager, a founder, a marketer, a new joiner on their first day — not a developer.** Assume the
reader has never opened the repo, will never open the repo, and stopped reading at the first word they
didn't recognise. Every other doc here is for an engineer or an agent; this one is not, and writing it in
engineering vocabulary wastes the only artefact those readers have.

**Use the words the business uses with its customers.** If a term appears in a sales conversation, a
pricing page or a support ticket, it belongs here. If it only appears in code, a protocol spec, or an
architecture discussion, replace it with what it *means* to someone using the product.

```
✅ Delivery — the buyer receives the lead in their own system, and we record whether it arrived
❌ FORM_POST delivery — posts the lead payload to the buyer's configured CRM endpoint

✅ Price quote — a price we have offered, good for a short window before it expires
❌ Bid TTL envelope — a signed bid token with an expiry claim, required on post
```

Banned outright: file names, class and table names, HTTP verbs and status codes, protocol names, and any
acronym the business does not say out loud. Link the contract for anyone who wants the mechanism —
**the link is where technical detail lives, never the sentence.**

The test before you ship it: **could someone in marketing read this aloud on a customer call without
stopping to ask what a word means?** If not, it isn't finished.

**It is written, not generated.** A tool can list what exists and draw the links it finds; it cannot decide
what matters, what to leave out, or what to call things so a newcomer follows. That judgement is the entire
value here. **Distil — do not dump.**

**Public and PII-free.** Assume a stranger reads it: no names, credentials, or machine paths.

## The diagram is the point

Everything else supports it. Rules, in order of how often they're broken:

- **Group by component.** Use one `subgraph` per layer the project declared in
  [`../prd/README.md`](../prd/README.md), in that order. The reader should see the system break into its
  levels before reading a single label.
- **About fifteen boxes, hard.** Past that nobody reads it and nobody maintains it. If the platform has
  forty parts, the diagram shows the fifteen that decide how it behaves — the rest live in
  [`../prd/README.md`](../prd/README.md), which lists everything by design.
- **One box = one thing a *non-technical* person would name.** If a stakeholder has never said the word out
  loud, it is implementation: rename it to what it does for the business, or leave it out.
- **Arrows are the point, not decoration.** An arrow means something real moves or depends: a request, a
  record, an authorisation. A diagram of boxes with no arrows has said nothing — if you cannot draw the
  arrows, you do not yet understand the platform, and that is worth saying out loud.
- **Point at the evidence for every arrow before you draw it** — the requirement, the field, the code path
  that makes it true. **A plausible arrow is the most dangerous thing in this file:** a reader builds their
  mental model from the picture and will never re-check it, and unlike a wrong sentence nobody proofreads a
  wrong line. If two things merely *appear* related, leave the arrow out and ask.
- **No status in the diagram.** No suffixes, no dashed nodes, no styling for what does or doesn't exist
  yet. The diagram answers one question — what the platform is and how work moves through it.
- **Label in the product's words**, matching each contract's `name:`, so a reader can jump from a box to
  the contract that governs it.

```mermaid
flowchart LR
  subgraph Entity["Entities"]
    a["Thing"] --> b["Other thing"]
  end
  subgraph Flow["Flows"]
    b --> c["What happens when they meet"]
  end
```

## Section guidance

- **`The platform`** — the diagram, and nothing else. No prose above it; it should survive being screenshotted.
- **`How it works`** — every component in flow order, **one short line each — about fifteen words**: what
  it is, and what it hands on. The whole list should be scannable in under a minute. Long paragraphs are the most common
  failure here — a product owner skims this section, and a wall of prose gets skipped entirely. The
  component is the unit: not a user story, not a sequence of API calls, the business naming its own parts.

  ```
  ✅ Source — where a seller's leads come from; feeds the lanes that price them.
  ❌ Source — where a seller's leads come from. A seller registers one; it starts in a safe sandbox
     until it is cleared for live traffic, and once live it feeds the lanes that price its leads.
  ❌ The Source entity is provisioned in sandbox mode and validated against the ping taxonomy.
  ```

  The first is scannable. The second says more and communicates less, and drags in a sandbox, which is not
  the product. The third is written for the wrong reader entirely.
- **`What you use`** — the surfaces, one line per audience, straight after `How it works`. Who signs in to
  what, and who integrates once and never signs in. It is the shortest section and the one a product owner
  reaches for first.
- **No build status anywhere in this file.** Not in the diagram, not in the list, not as an aside. Whether
  something exists yet is the contracts' job — their glyph rows carry it, and where a contract lives says
  the rest. Status written here is stale the week after, and it is not what this document is for. **The
  framing line under the title is what makes this safe** — it says once that the file describes the design,
  so no part has to carry its own disclaimer. Drop that line and the ban starts overclaiming on your behalf.
- **`What governs it`** — the rules that constrain the journey, and **who sets each one**: the terms both
  sides agreed, the limits, the obligations that pause things when unmet. A stakeholder can watch a demo
  and learn the flow; they cannot see the governance, which is exactly why it is here.

## Keeping it true

- **Update it when the shape changes** — a new system, a system removed, a flow re-routed. Not when a
  requirement changes; that is the contract's job, and this file never restates a requirement.
- **Never copy requirement text here.** Link the contract instead. Copied rules go stale silently, and this
  file is the one place nobody thinks to re-check.
- **If it disagrees with `prd/`, `prd/` wins** — and fix this file in the same task.
- **Where the content comes from:** the contracts in `prd/` and `prd-drafts/` (each one's *What this is*),
  the components declared in `../prd/README.md`, and `CODEMAP.md` for what actually exists. Where those
  leave the ordering ambiguous — and they usually do — **ask the owner rather than guessing a flow.**

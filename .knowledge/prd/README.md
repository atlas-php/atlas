# prd/ — tested contracts (catalog)

The ratified, test-backed PRDs — one per built system. **To write or modify one, follow [`../guides/docs-prd.md`](../guides/docs-prd.md).**

## Components — authored

The project's ontology, in order (`prefix — gloss`). The owner's call; an agent stops and asks.

```
1. base-     — config, service provider, HTTP transport, driver base
2. provider- — per-vendor clients and shared driver behavior
3. modality- — text, image, audio, voice, embeddings, rerank, moderation, batch
4. flow-     — tool loop, streaming, sub-agents, similarity search
```

## Contents — maintained by hand

Add a row when you add a PRD; `doc-lint` fails the build if one is missing. Component, then file, each row
a link to the contract followed by a one-line gloss of what it is. Read top to bottom, this list is the
product's high-level map.

No contracts are ratified yet — the ontology above is declared, but `prd/` holds no PRD files.

- **Base**
  - _(no PRDs yet)_
- **Provider**
  - _(no PRDs yet)_
- **Modality**
  - _(no PRDs yet)_
- **Flow**
  - _(no PRDs yet)_

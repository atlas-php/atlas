---
id: TESTING
name: Testing
---

## What this is

The seam that makes the whole provider layer fakeable. A test swaps every real provider for a fake, scripts the responses it wants, and asserts against what was sent — with no network call and no API key.

## Why it exists

- Tests run deterministically and offline, never touching a real provider or spending a token.
- A developer scripts exactly the responses a scenario needs, in the order they need them.
- A rich assertion suite proves what was sent, to whom, and with what content.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-TESTING-1 | A fake replaces the entire provider layer so no network call or key is needed. | `swaps the manager with AtlasFake` |
| ✅ | R-TESTING-2 | Every request made through the fake is recorded for later inspection. | `records text calls` |
| ✅ | R-TESTING-3 | Scripted responses are returned in the order they were queued. | `returns responses from sequence in order` |
| ✅ | R-TESTING-4 | The last scripted response repeats once the sequence is exhausted. | `repeats last response when sequence is exhausted` |
| ✅ | R-TESTING-5 | Each modality can be faked with its own typed response builder. | `returns correct response type from sequence for each modality` |
| ✅ | R-TESTING-6 | An assertion confirms at least one request was sent. | `assertSent passes after a call` |
| ✅ | R-TESTING-7 | An assertion confirms no request was sent. | `assertNothingSent passes with no calls` |
| ✅ | R-TESTING-8 | An assertion confirms the exact count of requests sent. | `assertSentCount passes with exact count` |
| ✅ | R-TESTING-9 | An assertion confirms a request matched a custom predicate. | `assertSentWith passes with matching callback` |
| ✅ | R-TESTING-10 | An assertion confirms a request targeted a given provider and model. | `assertSentTo passes with correct provider and model` |
| ✅ | R-TESTING-11 | An assertion confirms a given capability method was called. | `assertMethodCalled passes for called method` |
| ✅ | R-TESTING-12 | An assertion confirms a request's instructions contain given text. | `assertInstructionsContain passes with matching text` |
| ✅ | R-TESTING-13 | An assertion confirms a request's message contains given text. | `assertMessageContains passes with matching text` |

## Open questions

- None.

---
id: CONVO
name: Conversations
---

## What this is

A stored thread of messages between owners and agents that an agent's next turn draws on
automatically. One thread can span multiple users and multiple agents, tracks what has been read,
and replays earlier media as real input. Persisting the thread is optional; these behaviors apply
when it is enabled.

## Why it exists

- Agents keep context across turns without the developer re-sending history.
- One owner runs distinct threads with different agents side by side.
- Media a user shared earlier stays visible to the model later in the thread.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-CONVO-1 | Prior conversation history loads automatically into a turn before the provider call. | `prepends conversation history into the request messages` |
| ✅ | R-CONVO-2 | Loaded history is bounded by a configurable message limit. | `respects message limit when loading history` |
| ✅ | R-CONVO-3 | An owner keeps a separate thread for each agent. | `creates a new conversation for an owner and agent combo` |
| ✅ | R-CONVO-4 | Reusing the same owner and agent returns the existing thread. | `findOrCreate is idempotent — second call returns same conversation` |
| ✅ | R-CONVO-5 | Each agent in a shared thread sees other agents' messages as user input with a name prefix. | `remaps other agent messages to user role with name prefix` |
| ✅ | R-CONVO-6 | System messages pass through role remapping unchanged. | `passes system messages through unchanged in group remapping` |
| ✅ | R-CONVO-7 | An agent responds to a thread without a new user message. | `uses last user message as parentId in respond mode` |
| ✅ | R-CONVO-8 | Responding without a new user message is refused when no existing thread is joined. | `throws when respond mode used without forConversation` |
| ✅ | R-CONVO-9 | Unread messages in a thread can be counted. | `returns correct unread count` |
| ✅ | R-CONVO-10 | Messages are marked read up to a chosen point in the thread. | `marks messages as read up to a sequence` |
| ✅ | R-CONVO-11 | Earlier user media replays to the model as real input on later turns. | `preserves a shared image through the group remap so the model still sees it` |
| ✅ | R-CONVO-12 | Media replay is bounded to the most recent messages by a configurable limit. | `only replays media for the most recent messages within the configured limit` |
| ✅ | R-CONVO-13 | All earlier media replays when the replay limit is unset. | `replays media for every message when the limit is null` |

## Open questions

- Whether queued-message delivery within a thread warrants its own guaranteed row here.

---
id: QUEUE
name: Queue
---

## What this is

Background execution for any Atlas request. Calling for a queued run returns a handle with an
identifier immediately, then processes asynchronously with success and failure callbacks. Queue
settings are overridable per request, and failures that would only repeat are not retried.

## Why it exists

- Long-running AI work moves off the request cycle without blocking the user.
- The UI gets an identifier to track progress the moment a job is queued.
- Expensive or looping work fails fast instead of retrying and re-billing.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-QUEUE-1 | Any modality request can be dispatched to run in the background. | `QueuedModalitiesTest` |
| ✅ | R-QUEUE-2 | A queued request returns a handle immediately. | `queued terminal returns PendingExecution` |
| ✅ | R-QUEUE-3 | The handle carries an identifier the UI can show before the job runs. | `stores execution ID` |
| ✅ | R-QUEUE-4 | The job dispatches automatically when the handle goes out of scope. | `dispatches lazily via __destruct` |
| ✅ | R-QUEUE-5 | A success callback fires with the result when the job completes. | `invokes thenCallback with result on success` |
| ✅ | R-QUEUE-6 | A failure callback fires with the error when the job fails. | `invokes catchCallback with exception on failure` |
| ✅ | R-QUEUE-7 | The queue connection is overridable per request. | `onConnection() sets queue connection` |
| ✅ | R-QUEUE-8 | The retry attempt count is overridable per request. | `withTries() sets tries and returns self` |
| ✅ | R-QUEUE-9 | The retry backoff is overridable per request. | `withBackoff() sets backoff and returns self` |
| ✅ | R-QUEUE-10 | The job timeout is overridable per request. | `withTimeout() sets timeout and returns self` |
| ✅ | R-QUEUE-11 | A transient provider failure lets the job retry with a fresh provider call. | `lets a transient provider error propagate so the queue can retry it` |
| ✅ | R-QUEUE-12 | A step-ceiling failure fails immediately without retrying. | `fails immediately on MaxStepsExceededException without retrying` |

## Open questions

- None.

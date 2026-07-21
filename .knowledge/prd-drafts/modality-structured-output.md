---
id: STRUCT
name: Structured output
---

## What this is

The capability that makes a model return schema-validated structured data instead of prose. A developer
declares the shape with PHP field builders, and the same declaration both constrains the model's answer
and defines tool parameters.

## Why it exists

- A developer works with typed data whose shape they declared, not free text they must parse.
- One schema definition is reused for both tool parameters and structured answers.
- Optional and required fields are expressed once and honored consistently.

## Requirements

|  | ID | Requirement | Evidence |
|:--:|---|---|---|
| ✅ | R-STRUCT-1 | A schema is declared through PHP field builders rather than written by hand. | `creates correct field types` |
| ✅ | R-STRUCT-2 | A structured request directs the provider to answer in the schema's shape. | `sets responseMimeType and responseSchema for structured output` |
| ✅ | R-STRUCT-3 | A structured response exposes the parsed data. | `serializes to array with structured, usage, finish_reason, and meta` |
| ✅ | R-STRUCT-4 | Strict normalization makes every declared field required. | `adds additionalProperties:false and requires all keys on a flat object` |
| ✅ | R-STRUCT-5 | Strict normalization refuses any key the schema does not declare. | `adds additionalProperties:false and requires all keys on a flat object` |
| ✅ | R-STRUCT-6 | An optional field becomes nullable while remaining present. | `makes optional fields nullable but still required` |
| ✅ | R-STRUCT-7 | Nested objects are normalized to the strict form recursively. | `normalizes nested objects recursively` |
| ✅ | R-STRUCT-8 | A field is required by default. | `is required by default` |
| ✅ | R-STRUCT-9 | A field can be marked optional. | `marks last field optional` |
| ✅ | R-STRUCT-10 | One schema definition serves both tool parameters and structured output. | `supports tool parameters pattern` |

## Open questions

- Whether returned data is validated against the schema before it reaches the caller, or trusted as-is.

# Development Notes

## TODO: PHPUnit Security Advisory (CVE-2026-24765)

**Date:** 2026-01-27

**Issue:** PHPUnit has a security vulnerability (CVE-2026-24765 - Unsafe Deserialization in PHPT Code Coverage Handling).

- **Fixed in:** PHPUnit 11.5.50
- **Pest 3.x conflicts with:** PHPUnit >11.5.33
- **Result:** Pest 3.x blocks the security fix

**Current Workaround:** Ignoring the advisory in `composer.json`:
```json
"audit": {
    "ignore": ["PKSA-z3gr-8qht-p93v"]
}
```

**Why not upgrade to Pest 4?** Pest 4.x requires PHPUnit 12.x - a major version change.

**Action Required:**
- Monitor Pest 3.x releases for PHPUnit 11.5.50 support
- Once Pest 3.x supports the fixed PHPUnit, remove the audit ignore
- Alternatively, evaluate upgrading to Pest 4.x + PHPUnit 12.x

---

## Open Question: Should we commit `composer.lock`?

**Pros:**
- Reproducible CI builds
- Avoids surprise failures when upstream dependencies release breaking changes
- Zero impact on consumers (lock file is ignored when package is installed as dependency)

**Cons:**
- Traditional library convention is to not commit lock files
- May mask dependency resolution issues until intentional updates

**Decision:** TBD

---

## Prism Compatibility Tracking

Track Prism releases and their impact on Atlas. See `AGENTS.md` "Prism Compatibility" section for the review process.

| Date | Prism Versions | Status | Notes |
|------|----------------|--------|-------|
| 2026-01-29 | v0.99.16 - v0.99.19 | ✅ Compatible | No changes needed. Atlas unaffected by: streaming artifact key change, ResponseBuilder refactor, Skills support, provider fixes. |

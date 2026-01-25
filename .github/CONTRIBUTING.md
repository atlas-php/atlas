# Contributing to Atlas

Thank you for your interest in contributing to Atlas. This guide covers how both humans and AI agents can contribute to this project while maintaining our quality standards.

## Core Standards

**Before contributing, read [AGENTS.md](../AGENTS.md) thoroughly.** This document is the definitive source of truth for all coding standards, architectural decisions, and quality requirements. Every contribution must adhere to these guidelines.

## Etiquette

- Be respectful and constructive in all interactions
- Search existing issues before creating new ones
- Provide clear, detailed descriptions when reporting bugs or requesting features
- Keep discussions focused on the technical merits
- Accept feedback graciously and iterate accordingly

## Viability

Before starting work on a contribution, ensure your proposed change is viable:

1. **Bug fixes** - Confirm the bug exists and is reproducible
2. **New features** - Open an issue first to discuss the feature and its scope
3. **Refactoring** - Must have clear benefits; discuss major refactors before starting
4. **Documentation** - Always welcome; ensure accuracy and follow existing patterns

For significant changes, open an issue to discuss your approach before investing time in implementation. This helps ensure alignment with the project's direction.

## Contribution Procedure

### For AI Agents

AI agents must follow these steps precisely:

1. **Read AGENTS.md completely** before making any changes
2. **Read relevant documentation** - VitePress docs for the module you're modifying
3. **Make focused changes** - One logical change per contribution
4. **Run `composer check`** - All lint, static analysis, and tests must pass
5. **Update documentation** - Keep docs in sync with code changes
6. **Write descriptive commit messages** - Explain what and why

### For Human Contributors

1. Fork the repository
2. Create a feature branch from `main`
3. Make your changes following AGENTS.md guidelines
4. Run `composer check` to verify quality
5. Submit a pull request with a clear description

## Branching Strategy & Commits

### Branch Naming

Use descriptive branch names that indicate the type of change:

```
feature/add-streaming-support
fix/tool-execution-timeout
docs/update-installation-guide
refactor/simplify-agent-resolver
```

### Commit Messages

Write clear, concise commit messages that explain the change:

```
Add streaming response support for chat method

- Implement StreamingResponse value object
- Update AtlasManager to support streaming
- Add tests for streaming functionality
```

**Guidelines:**
- Use present tense ("Add feature" not "Added feature")
- First line is a summary (50 characters or less)
- Leave a blank line before detailed description if needed
- Reference issues when applicable (e.g., "Fixes #123")

## Requirements

All contributions must meet these requirements before merging:

### Code Quality

1. **`composer check` must pass** - This runs lint, static analysis, and tests
2. **Follow PSR-12** - Code style enforced via Laravel Pint
3. **PHPStan level 6** - No static analysis errors
4. **Test coverage** - All new code must have tests
5. **PHPDoc blocks** - Every class must have documentation

### Architecture

1. **Respect layer boundaries** - See AGENTS.md for the dependency direction model
2. **Use dependency injection** - Never instantiate services directly
3. **Program to interfaces** - When contracts exist, use them
4. **Earn your abstractions** - No speculative generalization

### Documentation

1. **Update docs** - When changing behavior, update the relevant VitePress documentation
2. **Keep examples working** - All code examples must be syntactically correct
3. **Link to Prism** - For Prism-level features, link to Prism docs instead of duplicating

## Running Quality Checks

```bash
# Run all checks (lint, analyse, test)
composer check

# Individual commands
composer lint        # Fix code style
composer lint:test   # Check code style without fixing
composer analyse     # Run PHPStan
composer test        # Run Pest tests
```

## What We Accept

- Bug fixes with tests proving the fix
- New features aligned with the project roadmap
- Documentation improvements and clarifications
- Performance improvements with benchmarks
- Test coverage improvements

## What We Don't Accept

- Breaking changes without prior discussion
- Features that don't align with the project's scope
- Code that doesn't pass quality checks
- Changes without tests (for code changes)
- Over-engineered solutions or speculative abstractions

## Getting Help

- **Issues** - For bugs, features, and questions
- **AGENTS.md** - For coding standards and architecture
- **docs/** - For technical specifications and guides

Thank you for contributing to Atlas!

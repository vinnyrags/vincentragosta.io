# Changelog & Releases Plan

This document outlines the plan for implementing conventional commits, changelogs, and release management after the ix is split into its own repository.

## Overview

Once `ix` is extracted into its own Composer package, both repositories will need independent versioning and changelog management.

## Prerequisites

Before implementing, complete the ix extraction:
- [ ] Create separate repository for ix
- [ ] Set up Composer package configuration for ix
- [ ] Update child-theme to require ix via Composer

## Setup for Each Repository

Run these steps in both `ix` and `child-theme` repos after the split.

### 1. Install Dependencies

```bash
npm install --save-dev conventional-changelog-cli @commitlint/cli @commitlint/config-conventional husky standard-version
```

### 2. Create commitlint.config.js

```js
module.exports = {
  extends: ['@commitlint/config-conventional'],
};
```

### 3. Update package.json Scripts

```json
{
  "scripts": {
    "prepare": "husky",
    "release": "standard-version",
    "release:first": "standard-version --first-release",
    "release:minor": "standard-version --release-as minor",
    "release:major": "standard-version --release-as major"
  }
}
```

### 4. Initialize Husky

```bash
npm run prepare
echo "npx --no -- commitlint --edit \$1" > .husky/commit-msg
chmod +x .husky/commit-msg
```

### 5. Create Initial CHANGELOG.md

```bash
npm run release:first
```

## Commit Message Format

All commits must follow the conventional commit format:

```
type(scope): subject

# Examples:
feat: add dark mode support
fix: resolve header alignment issue
chore: update dependencies
docs(readme): add installation instructions
feat(blocks)!: redesign hero block API

BREAKING CHANGE: Hero block now requires title attribute
```

### Types

| Type     | Description                          | Version Bump |
|----------|--------------------------------------|--------------|
| feat     | New feature                          | Minor        |
| fix      | Bug fix                              | Patch        |
| docs     | Documentation only                   | None         |
| style    | Code style (formatting, etc.)        | None         |
| refactor | Code refactor (no feature/fix)       | None         |
| perf     | Performance improvement              | Patch        |
| test     | Adding/updating tests                | None         |
| build    | Build system changes                 | None         |
| ci       | CI configuration                     | None         |
| chore    | Maintenance tasks                    | None         |
| revert   | Revert previous commit               | Patch        |

### Breaking Changes

Add `!` after type or include `BREAKING CHANGE:` in footer for major version bumps:

```
feat!: remove deprecated API

# or

feat: redesign component

BREAKING CHANGE: Component props have changed
```

## Release Workflow

### Parent Theme (Composer Package)

Semantic versioning is critical for Composer resolution:

```bash
# After completing features/fixes on main branch
npm run release        # Auto-determines version from commits
git push --follow-tags # Push commit and tag
```

Child theme references parent via Composer:
```json
{
  "require": {
    "your-vendor/ix": "^1.0"
  }
}
```

### Child Theme

```bash
npm run release
git push --follow-tags
```

Optionally document ix version dependency in changelog:
```markdown
## 1.2.0 (2026-02-01)

* Requires ix ^1.5.0

### Features
* add custom block styles
```

## Generated Changelog Example

After using conventional commits, `standard-version` generates:

```markdown
# Changelog

## [1.2.0](https://github.com/user/repo/compare/v1.1.0...v1.2.0) (2026-02-01)

### Features

* **blocks:** add shutter card animation ([abc1234](https://github.com/user/repo/commit/abc1234))
* implement dark mode toggle ([def5678](https://github.com/user/repo/commit/def5678))

### Bug Fixes

* **header:** resolve mobile alignment issue ([ghi9012](https://github.com/user/repo/commit/ghi9012))
```

## Configuration Options

### .versionrc (optional)

Create `.versionrc` for custom standard-version behavior:

```json
{
  "types": [
    {"type": "feat", "section": "Features"},
    {"type": "fix", "section": "Bug Fixes"},
    {"type": "chore", "hidden": true},
    {"type": "docs", "hidden": true},
    {"type": "style", "hidden": true},
    {"type": "refactor", "section": "Refactoring"},
    {"type": "perf", "section": "Performance"},
    {"type": "test", "hidden": true}
  ]
}
```

## Summary

| Repository   | Versioning               | Changelog | Tags Required |
|--------------|--------------------------|-----------|---------------|
| ix | Semantic (Composer)      | Yes       | Yes (Composer)|
| child-theme  | Semantic                 | Yes       | Recommended   |

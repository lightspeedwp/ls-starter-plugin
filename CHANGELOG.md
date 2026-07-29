# Changelog

All notable changes to this plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Native asset cachebusting: `script_loader_src`/`style_loader_src` filters rewrite the `ver` query arg on local assets to the file's `filemtime()`, replacing the Cachebuster plugin. Filterable via `ls_starter_cachebusting_enabled` and `ls_starter_cachebusting_skip_handle`.
- Dismissible admin notice flagging redundant plugins (Cachebuster, Safe SVG, Change Mail Sender) whose function this codebase already covers natively. Notice-only — never deactivates or blocks activation.

### Changed

### Deprecated

### Removed

### Fixed

### Security

---

## [0.1.0] - YYYY-MM-DD

### Added
- Initial plugin scaffold with placeholder structure.
- Block-ready `src/blocks/` and `blocks/` folder layout.
- `plugin-utils.mjs` for plugin validation, schema checks, and security scanning.
- Composer-based PHP quality tooling (PHPCS, PHPCBF, PHP lint).
- `.github/` folder with Copilot instructions, prompts, reports, tasks, and workflows.
- `.agents/` folder with portable skills and agent personas.
- `docs/` folder for end-user documentation.

---

[Unreleased]: https://github.com/{{GITHUB_ORG}}/{{REPO_NAME}}/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/{{GITHUB_ORG}}/{{REPO_NAME}}/releases/tag/v0.1.0

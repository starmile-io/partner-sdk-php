<!-- ═══════════════════════════════════════════════════════════════════════════
  COMMON STARMILE RULES — inlined so this repo is SELF-CONTAINED (works on a lone
  clone / in CI, with no dependency on the untracked workspace-root CLAUDE.md).
  This block is duplicated verbatim across every Starmile repo — keep it in sync;
  edit the canonical copy and re-sync rather than diverging one repo.
  Repo-specific guidance follows the "───" divider at the bottom.
═════════════════════════════════════════════════════════════════════════════ -->

## Product context

- Starmile is a **cross-border logistics platform**. It is **multi-tenant by company**: each company (a partner's per-country entity, and the customer itself) has its **own tenant DB**, with isolation enforced at the database layer; a shared **global (landlord) DB** holds partners, the companies registry, reference data, and flow definitions. Hosted on **Laravel Cloud**.

## Session Naming

- CRITICAL: name every Claude Code session **`Starmile - <topic>`** (short, specific — e.g. `Starmile - Cargomax flows`). Set/rename the title the moment the focus is clear, and update it if the focus shifts. If the harness can't set it programmatically, surface the suggested `Starmile - <topic>` name so it can be applied.

## Git Commit Rules

- CRITICAL: run `git add -A` and `git commit` **immediately after** completing any requested task, bug fix, or feature, with a descriptive **conventional-commit** message matching the work. Do not ask permission before committing.
- Do NOT create or switch to a new branch for a task. Always commit on the **current branch** unless the user explicitly asks you to branch.

## Documentation Rules

- ALL documentation is written in **English, always** — regardless of the language used in the request or conversation (code comments, READMEs, commit messages, Linear, the glossary, any written docs). Keep it well-structured and easy to understand, with concrete examples where helpful.

## Accuracy Rule (Do Not Invent)

- CRITICAL: never invent, assume, or fabricate facts, requirements, business rules, names, numbers, flows, or terminology — in conversation, analysis, planning, or documentation. Work only from what the user provides or what is verifiably in the codebase.
- If something is unknown, unclear, or not stated, **say so explicitly and ask** — do not fill the gap with a guess. Mark open questions as open questions. When summarizing source documents, stay faithful to the source.

## Testing (Always Write Tests)

- CRITICAL: every task — feature, bug fix, or change — ships with tests in the **same change**; a bug fix **starts** with a test that reproduces the bug. Never commit with failing tests.
- Passing unit/component tests is the **floor, not the finish**: they do not catch build, type, or render errors. After any change you MUST also **verify the thing actually runs** (typecheck, and for UI load the affected route(s) and confirm they return `200` and render with no build/runtime/console error) before reporting done or committing. Stack-specific test commands are in the repo-specific section below.

## Quality Bar & Finishing (apply by default — do not wait to be asked)

- CRITICAL: a first pass that merely "works" is **not done**. Ship the final touches a senior engineer would, proactively and in the same change — "refine it to best-practice UX" is a standing instruction, not a separate request.
- **Never expose raw machine values to a user** (keys, enum codes, slugs, language/country codes, IDs) — always map them to human-readable labels. Always handle loading, empty, and error states; give controls accessible labels; use the available space with responsive, aligned layouts. When unsure whether a polish item is in scope, do it and mention it.

## Glossary Rule

- CRITICAL: whenever a business keyword, domain term, or new business concept is introduced (by the user or discovered in the work), record it in the relevant **per-feature Linear glossary** — a `<Feature> — Glossary` document (`00 —` prefix so it sorts to the top of its project). Don't leave business terms only inline in code/docs. Keep each glossary a compact table: term, short meaning, example/note.

## Linear (Project Management)

- Linear is the **source of truth** for project management. Workspace `star-tech`; team key **`STRMILE`** (always reference the team by its key, never its display name). Use the Linear MCP for all reads and writes.
- CRITICAL: keep Linear up to date **autonomously** — whenever scope, requirements, structure, or status change in the work or the conversation, reflect it immediately, in the **same turn**, **without asking** the user. Do NOT use initiatives — organize everything with **projects + milestones** only.
- Linear holds **business documentation only — never technical** content (no code, schemas, table/column names, file/module paths, API endpoints/payloads, framework/infra detail, or implementation notes). Write in plain business language a non-engineer can read end-to-end. Organize projects by **application/module** (e.g. Management panel, Console, Ops app); each capability/area is a **milestone**.
- Every Linear **Document** is bound to a **project** (never left at team level), numbered **`NN — <Title>`** (`00 —` Glossary, `99 —` overview, capabilities in between), and **opens with a simple business-flow diagram** — a Mermaid ` ```mermaid ` block, default `flowchart TD`, business steps only (no code/DB/infra). One project → many documents (an overview plus one per capability). Whenever a Document is created or changed, **post a Project update** to its project summarizing the change.

<!-- ═══ END COMMON STARMILE RULES ═══ -->

---
# starmile-partner-sdk-php

The official **PHP SDK** for the **Starmile Partner API** (Composer package `starmile/partner-sdk`, namespace `Starmile\PartnerSdk`). A standalone, **dependency-free** client (`ext-curl` + `ext-json` only) supporting **PHP 7.1 → 8.4** (partners may run legacy stacks — keep new code on that floor). It wraps every Partner API capability behind a small typed client: catalogue lookup, order intake/management, the pull-based status pool, and inbound lifecycle events.

## Lockstep with the Partner API (CRITICAL)

- The SDK mirrors the HTTP contract **verbatim**; the HTTP API stays canonical. Each capability maps to a resource group (`catalogue()`, `orders()`, `statusPool()`, `events()`) with fluent builders (`OrderBuilder`/`ShipmentBuilder`/…) and constant catalogues (`Scope`, `EventType`, `OrderStatus`, …) copied from the server vocabulary — do NOT "correct" casing/hyphens.
- CRITICAL — whenever the Partner API changes in any partner-observable way (endpoint, scope, event type or `data` field, request/response field, status value, or error/validation shape), this SDK MUST be updated in the **same change**: add the capability (resource method / builder / constant), cover it with a test, **bump the version + `CHANGELOG.md`**, and keep `README.md` examples accurate. (The Partner API lives in `starmile-api` `Modules/Oms`; its own CLAUDE.md carries the full cross-repo checklist — docs portal + Postman collection + this SDK.)

## Toolchain — Composer/PHPUnit on the host (NOT a Docker service)

> This SDK is a separate package with its own `composer.json` and CI. It is **not** part of `starmile-api` and is **not** a workspace `docker compose` service.

- `composer install` (install, don't update; commit `composer.lock`), tests via `vendor/bin/phpunit`.

## Testing

- Every change ships with a **PHPUnit** test; never commit failing tests. Keep coverage of new resource methods/builders/constants and the version bump together.

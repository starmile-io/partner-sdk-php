# partner-sdk-php

The official **PHP SDK** for the **Starmile Partner API** (Composer package `starmile/partner-sdk`, namespace `Starmile\PartnerSdk`). A standalone, **dependency-free** client (`ext-curl` + `ext-json` only) supporting **PHP 7.1 → 8.4** (partners may run legacy stacks — keep new code on that floor). It wraps every Partner API capability behind a small typed client: catalogue lookup, order intake/management, the pull-based status pool, and inbound lifecycle events.

## Lockstep with the Partner API (CRITICAL)

- The SDK mirrors the HTTP contract **verbatim**; the HTTP API stays canonical. Each capability maps to a resource group (`catalogue()`, `orders()`, `statusPool()`, `events()`) with fluent builders (`OrderBuilder`/`ShipmentBuilder`/…) and constant catalogues (`Scope`, `EventType`, `OrderStatus`, …) copied from the server vocabulary — do NOT "correct" casing/hyphens.
- CRITICAL — whenever the Partner API changes in any partner-observable way (endpoint, scope, event type or `data` field, request/response field, status value, or error/validation shape), this SDK MUST be updated in the **same change**: add the capability (resource method / builder / constant), cover it with a test, **bump the version + `CHANGELOG.md`**, keep `README.md` examples accurate, **and cut the matching release tag (see _Releasing_ below)**. (The Partner API lives in `api` `Modules/Oms`; its own CLAUDE.md carries the full cross-repo checklist — docs portal + Postman collection + this SDK.)

## Releasing (tag every version — this is what publishes to Packagist)

- CRITICAL: the SDK is published on **Packagist** as `starmile/partner-sdk`, and Packagist versions come **only from git tags**. A `CHANGELOG.md` bump is **not** a release on its own — until a matching tag is pushed, `composer require starmile/partner-sdk` can resolve only the unstable `dev-*` branch versions. **Every version bump ships its git tag in the same change** — a change is not "done" until the release is tagged.
- CRITICAL: **never push a version-bump commit without cutting its tag in the same push.** A pushed commit that bumps `CHANGELOG.md` but has no tag is a bug — it leaves the released version behind `HEAD` and can open a version gap. If you ever find a pushed `## [X.Y.Z]` head with no `vX.Y.Z` tag (and any earlier untagged bumps below it), tag each bump at its own commit and push the tags before doing anything else.
- After the code + test + `CHANGELOG.md` bump have landed, cut the release by tagging the **default branch (`prod`)** with the exact current `CHANGELOG.md` head version, then pushing the tag:
  - `git tag -a vX.Y.Z origin/prod -m "Release vX.Y.Z"`
  - `git push origin vX.Y.Z`
  The tag (with its `v` prefix) MUST equal the top `## [X.Y.Z]` entry in `CHANGELOG.md`.
- Work lands on `dev`, is merged to `prod`, then the tag is cut on `prod` — a release always reflects what is merged to prod, never an un-merged `dev` state.
- Packagist auto-updates on tag push via the installed **Packagist GitHub App** (on the `Starmile-IO` org, repo `partner-sdk-php`) — no webhook or API token to manage; the new version appears within ~a minute. **Never** add a `version` field to `composer.json`; the git tag is the single source of truth.
- Follow **SemVer**, consistent with the existing `CHANGELOG.md` history: a breaking partner-observable change → **major**, a backward-compatible addition → **minor**, a fix → **patch**.

## Toolchain — Composer / PHPUnit

> This SDK is a separate package with its own `composer.json` and CI. It is **not** part of `api`.

- `composer install` (install, don't update; commit `composer.lock`), tests via `vendor/bin/phpunit`.

## Testing

- Every change ships with a **PHPUnit** test; never commit failing tests. Keep coverage of new resource methods/builders/constants and the version bump together.

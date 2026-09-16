# CLAUDE.md

Guidance for Claude Code working in this repository.

## What this is

The Romanian e-invoicing kernel: pure PHP 8.4, no framework. `Document\` is the EN 16931 model, `Ubl\` writes and reads CIUS-RO UBL 2.1, `Validation\` predicts ANAF's validator, `Anaf\` talks to it over PSR-18, `Vat\` maps a neutral treatment to Romanian codes. Orchestration (persistence, jobs, events) lives in `atlasflow/efactura-ro-laravel`; this package stays stateless and computes no money.

## Rules that hold

- The caller supplies every amount; the kernel verifies and never rounds a caller's figure. `Amount::of(float)` throws.
- Every model class is `final readonly`. Reshape with a new instance.
- One validation rule per file under `src/Validation/Rules/`, named for the schematron id, with the rule text and ANAF's tolerance in the docblock. When ANAF's behaviour is probed, write the date and the outcome into the docblock.
- The reader is tolerant: received documents come from other people's software. Only the `Document` constructor's own invariants may make a read fail.
- Nothing names Guzzle or Laravel. HTTP is PSR-18 in, PSR-7 out.
- `tests/Live/ValidareTest.php` is the definition of "the writer is right"; run it before touching the writer or the rules. `tests/Live/UploadTest.php` needs a real token and is the gate for anything under `Anaf\` that takes one.

## Working here

`composer test` for the offline suite, `composer test:live` for ANAF's validator, `vendor/bin/pint --parallel` before committing, `vendor/bin/phpstan analyse` must be clean. Develop on `dev/dev-<version>`; merge into `master` when the suite is green. The repository is public — no tokens, no real CUIs with names, nothing from `.env`, in fixtures or commits.

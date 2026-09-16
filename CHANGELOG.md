# Changelog

## v0.1.1 — 2026-09-16

`brick/math` is now `^0.14.2` through `^0.19` (the `RoundingMode` enum), so the kernel installs beside Laravel 13. No behaviour change.

## v0.1.0 — 2026-09-16

First release. The document model, the CIUS-RO UBL 2.1 writer and reader, the local rule set and the public ANAF calls (`validate`, `render`, `verifySignature`) are proven against ANAF's validator: every fixture document is accepted. The OAuth client and the authenticated endpoints (`upload`, `status`, `messages`, `messagesBetween`, `download`, `hello`) are implemented from the Ministry of Finance's published API pages and covered by recorded-response tests, but have not yet been run against a live ANAF session — `tests/Live/UploadTest.php` is the gate.

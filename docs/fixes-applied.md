# KardioRAG — Fixes Applied

Running log of fixes made in response to [security-and-best-practices-review.md](security-and-best-practices-review.md).
Newest first. Each entry: what was wrong, what changed, and how it's verified.

## 2026-06-23 — 🔴 HIGH: queue `retry_after` shorter than job timeouts

**Problem.** `config/queue.php` set the database queue `retry_after` to `90s`, but jobs run far longer —
`AnswerQuestionJob` `timeout = 600s`, `IngestDrugJob` `timeout = 1200s` (CPU generation routinely exceeds
90s). On the `database` queue a job still running after 90s was re-released and processed again: duplicate
openFDA fetch + re-embed for ingestion, and a spurious failed attempt for answers.

**Fix.**
- `config/queue.php` — database `retry_after` default raised `90 → 1300` (just above the 1200s ingest timeout).
- `.env.example` — added `DB_QUEUE_RETRY_AFTER=1300` with an explanatory comment.
- `INSTALL.md` — added the variable to the step-2 `.env` block and a note in step 6 (worker `--timeout`
  and `retry_after` must both stay above the job timeouts).

**Test.** `tests/Feature/QueueRetryAfterTest.php` asserts the database queue `retry_after` is greater than
the longest job `timeout` (read from the job classes, so it can't drift). `php artisan test --filter=QueueRetryAfter` → passing. `vendor/bin/pint --dirty` → clean.

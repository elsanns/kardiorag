# KardioRAG — Fixes Applied

Running log of fixes made in response to [security-and-best-practices-review.md](security-and-best-practices-review.md).
Newest first. Each entry: what was wrong, what changed, and how it's verified.

## 2026-06-23 — 🟠 MEDIUM: jobs lacked failure handling; ingestion not unique

**Problem.** Neither queued job implemented `failed()`. If `AnswerQuestionJob` died outside
`RagService::runQuery`'s own try/catch (e.g. a worker `--timeout` kill), the `Query` stayed
`processing` and the polling UI waited forever. And `IngestDrugJob` was not unique, so the same drug
could be ingested twice concurrently (re-fetch + delete + re-embed is unsafe to run in parallel).

**Fix.**
- `app/Jobs/AnswerQuestionJob.php` — add `failed(?Throwable $e)` that marks a still-`pending`/`processing`
  query as `failed` and records the error, without clobbering an already-`done` query.
- `app/Jobs/IngestDrugJob.php` — implement `ShouldBeUnique` keyed on the drug name (`uniqueId()`), and add
  `failed()` that logs the failure.

**Test.** `tests/Feature/JobReliabilityTest.php` — asserts `failed()` marks a processing query failed,
does not overwrite a completed query, and that `IngestDrugJob` is `ShouldBeUnique` with `uniqueId()` =
the drug name. Full suite green (33 tests, 95 assertions); `vendor/bin/pint --dirty` clean.

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

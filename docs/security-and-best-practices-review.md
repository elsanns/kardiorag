# KardioRAG — Security & Laravel Best-Practices Review

_Date: 2026-06-23 · Stack: Laravel 13.16.1, PHP 8.3, PostgreSQL + pgvector, Ollama (local LLM), openFDA ingestion._

Performed with the Laravel Boost `laravel-best-practices` skill and Boost MCP tools
(`application-info`, `database-connections`, `search-docs`), plus a dedicated code-review pass.
Findings are grounded in the code (file:line). **No code was changed** — fixes are tracked separately.

## A. Laravel best-practices (code)

### 🔴 HIGH — `retry_after` shorter than job timeouts → duplicate / failed jobs

- `config/queue.php:43` `retry_after = 90` (default `DB_QUEUE_RETRY_AFTER`), but
  `app/Jobs/AnswerQuestionJob.php:22` `timeout = 600` and `app/Jobs/IngestDrugJob.php:20` `timeout = 1200`.
- The UI itself states generation "can take a minute or two on CPU" — i.e. routinely **> 90s**. On the
  `database` queue the job is re-reserved after 90s while still running:
  - `IngestDrugJob` (`tries = 2`) → duplicate openFDA fetch + re-embed running **concurrently**.
  - `AnswerQuestionJob` (`tries = 1`) → a spurious second attempt that fails while the first still generates.
- **Fix:** set `DB_QUEUE_RETRY_AFTER` above the longest job timeout (e.g. `1300`); also surface it in INSTALL step 6.

### 🟠 MEDIUM

1. **Jobs don't implement `failed()`** (`AnswerQuestionJob`, `IngestDrugJob`). If anything throws before
   `RagService::runQuery`'s try/catch (DI/deserialize error), the `Query` stays `processing` and the web UI
   polls forever. Add `failed(?Throwable $e)` to mark it `failed` / log.
2. **`IngestDrugJob` not idempotent / not `ShouldBeUnique`.** With the `retry_after` bug it can run twice
   concurrently (`Ingestor::ingestDrug` deletes + re-embeds). Add `ShouldBeUnique` keyed on the generic name.
3. **No `connectTimeout()` / `retry()` on external HTTP.** All LLM providers
   (`app/Services/Llm/*Provider.php`) and `app/Services/OpenFda/OpenFdaClient.php:42` set only `timeout()`.
   A transient blip hard-fails (cloud `tries=1` loses the query). Add `->connectTimeout(5)->retry(3, 200, throw: false)`.
4. **Sequential one-by-one embedding** (`app/Services/Rag/Embedder.php:43-52`, `OllamaProvider::embed`) —
   N round-trips inside a 1200s job; main driver of the timeout. Batch the embed request or use `Http::pool()`.
5. **Inline `$request->validate()`** in `AskController.php:29` / `Api/IngestController.php:25` instead of
   Form Requests; the curated-drug check could be a `Rule::in`.
6. **Missing composite index** `['status','created_at']` on `queries` — supports both the dashboard status
   counts and the per-submit `whereDate('created_at', today())` daily-cap count (`AskController.php:35`).

### 🟡 LOW

- `app/Models/Chunk.php:10-15` declares both `$fillable` and a redundant `$guarded = ['embedding']`.
- Hardcoded table names / raw `DB::table('documents')` in `DashboardController`, `Api/DrugController`,
  `Retriever.php:88` — prefer Eloquent / `(new Model)->getTable()` (the pgvector raw SQL itself is justified).
- `Query` model doesn't mirror the migration's `status = 'pending'` default in `$attributes`.
- No HTTP-faked tests for the ingest/generation path (`Http::fake()` + `preventStrayRequests()`); the
  controller / guard / throttle layer is otherwise well covered.

### Done well

Correct parameter binding in all pgvector SQL; mass-assignment whitelists on user-facing models;
CSP/nonce + per-IP throttling + global daily cap; curated openFDA ingest allowlist; interface-based
providers with constructor DI; `constrained()` + reversible migrations; no queries in Blade; fail-loud
provider factory.

## B. README & INSTALL — correctness & safety

**Correct / good:** pgsql + pgvector + `EMBED_DIM=768` consistent; the "Node optional / CSS already
committed" claim is accurate (layout serves committed `public/css/app.css`, no `@vite`); embedded
screenshot exists; the SSH-tunnel remote-access guidance correctly avoids exposing the unauthenticated port.

1. **🟠 INSTALL step 6 reproduces the `retry_after` bug.** It says to run `php artisan queue:work` on the
   default DB queue with no `DB_QUEUE_RETRY_AFTER`, so users hit the duplicate-job issue above. Add
   `DB_QUEUE_RETRY_AFTER=1300` to the step-2 `.env` block.
2. **🟢 `.env (gitignored)` claim (INSTALL:33) — was inaccurate, now fixed.** Until commit `81dc316` the
   repo `.gitignore` did not ignore `.env`, so that parenthetical was false (a `git add -A` could have
   committed the DB password). `.env` is now ignored — verified.
3. **🟡 `curl -fsSL https://ollama.com/install.sh | sh` (INSTALL:67)** — unpinned remote script piped to a
   shell; the same supply-chain risk the README `supply-chain` row flags. Fine for a demo; note it otherwise.
4. **🟡 No `APP_DEBUG=false` / `APP_ENV=production` guidance.** `.env.example` ships `APP_DEBUG=true` /
   `APP_ENV=local`; any real exposure would leak stack traces. A one-line deployment note in INSTALL closes it.
5. **🟡 Shared placeholder password `change-me`** appears in the `psql` heredoc and `.env` and lands in
   shell history — add a "choose a strong password" nudge.

## Recommended priority

1. **Fix `retry_after`** (config/`.env` + INSTALL line) — real bug, hits every slow generation.
2. Add `failed()` + `ShouldBeUnique` to the jobs.
3. `connectTimeout` + `retry` on external HTTP; batch embeddings.
4. Doc nudges: `DB_QUEUE_RETRY_AFTER`, optional `APP_DEBUG=false` note.

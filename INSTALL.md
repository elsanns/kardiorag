# KardioRAG — Installation

## Prerequisites

- **PHP 8.3+** with the `pdo_pgsql` extension, and **Composer**.
- **PostgreSQL 14+** with the **pgvector** extension available on the host (e.g. `apt install postgresql-16-pgvector`).
- **Ollama** — runs the local models.
- *(Optional)* Node 18+ — only to rebuild frontend assets; the compiled CSS is already committed.

## 1. Clone & install dependencies

```bash
git clone https://github.com/elsanns/kardiorag.git
cd kardiorag
composer install
```

## 2. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` — database and local models:

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=kardiorag
DB_USERNAME=kardiorag
DB_PASSWORD=change-me          # stored only in .env (gitignored)

LLM_MODE=local
OLLAMA_CHAT_MODEL=llama3.2:3b
OLLAMA_EMBED_MODEL=nomic-embed-text
EMBED_DIM=768                  # must match the embedding model (nomic-embed-text = 768)
# OLLAMA_BASE_URL defaults to http://127.0.0.1:11434 (Ollama's default port);
# set it only to point at a different port or a remote Ollama host.
OPENFDA_API_KEY=               # optional; raises the openFDA rate limit (see step 5)
```

## 3. Set up the database

Create the role and database, and enable pgvector **before** migrating (the schema adds a `vector(768)`
column, so the extension must already exist). Run as a Postgres superuser:

```bash
sudo -u postgres psql <<'SQL'
CREATE ROLE kardiorag LOGIN PASSWORD 'change-me';
CREATE DATABASE kardiorag OWNER kardiorag;
\connect kardiorag
CREATE EXTENSION IF NOT EXISTS vector;
SQL
```

Use the same password here and in `.env`. Then create the schema:

```bash
php artisan migrate
```

## 4. Install the local models (Ollama)

```bash
curl -fsSL https://ollama.com/install.sh | sh
ollama pull nomic-embed-text     # 768-dim embeddings
ollama pull llama3.2:3b          # chat / generation
```

Make sure the server is running (`ollama serve`, or the systemd service) and reachable:
`curl http://127.0.0.1:11434/api/tags`.

## 5. Ingest the knowledge base

Fetches the curated cardiology drug set from openFDA and embeds it (needs internet access and Ollama running):

```bash
php artisan kardiorag:ingest
```

Pass generic names to limit the set (e.g. `kardiorag:ingest amiodarone metoprolol`); add `--queue` to
dispatch jobs to the queue instead of running inline.

> openFDA's unauthenticated limit is **1000 requests/day per IP** (240/min). The default ingest makes
> ~1 request per drug (≈8 total), well within that. For heavier use, get a free key and set
> `OPENFDA_API_KEY` in `.env` to raise the limit.

## 6. Run

**Web UI** — needs a queue worker for asynchronous answer generation:

```bash
php artisan serve          # http://127.0.0.1:8000
php artisan queue:work     # separate terminal: processes question + ingest jobs
```

**CLI** — synchronous, no worker needed:

```bash
php artisan kardiorag:ask "What are the contraindications of amiodarone?"
```

## Accessing a remote server from your laptop

The dev server binds to loopback (`127.0.0.1:8000`) and the app has no authentication or TLS by default,
so don't expose the port directly. Use an SSH tunnel from your laptop instead:

```bash
ssh -L 8000:127.0.0.1:8000 ubuntu@<server-host>
```

Then open <http://localhost:8000> on your laptop. Traffic is encrypted over SSH and the app stays
private (loopback / on-prem).

---

> Cloud LLM providers are disabled by default (`LLM_MODE=local`); the app runs fully on-prem unless
> explicitly configured otherwise. A global daily query cap (200) applies.

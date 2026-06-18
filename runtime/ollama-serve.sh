#!/usr/bin/env bash
# Start the local Ollama server with models stored on the data volume.
BASE="$(cd "$(dirname "$0")/ollama" && pwd)"
export OLLAMA_MODELS="$BASE/models"
export OLLAMA_HOST=127.0.0.1:11434
export LD_LIBRARY_PATH="$BASE/lib/ollama:$LD_LIBRARY_PATH"
exec "$BASE/bin/ollama" serve

#!/usr/bin/env python3
"""
KardioRAG PoC baseline.

Proves the full RAG pipeline on the target machine using:
  - public openFDA drug-label data (no API key)
  - local Ollama: nomic-embed-text (embeddings) + llama3.2:3b (generation)

Steps:
  1. Fetch openFDA labels for several cardiac drugs -> build a small document set.
  2. Embed every document chunk locally (measure embedding throughput).
  3. Embed a user question, do cosine top-k retrieval (the "vector search" step).
  4. Generate a grounded, source-cited answer locally (measure generation tps).

Mirrors exactly what the Laravel app will do with PostgreSQL+pgvector and the
LlmProvider interface; here the vector math is in-process to keep the PoC self-contained.
"""
import json, time, math, urllib.request, urllib.parse, sys

OLLAMA = "http://127.0.0.1:11434"
EMBED_MODEL = "nomic-embed-text"
GEN_MODEL = "llama3.2:3b"
DRUGS = ["amiodarone", "metoprolol", "warfarin", "atorvastatin"]
QUESTION = "What are the key warnings and contraindications for amiodarone?"

def http_json(url, payload=None, timeout=600):
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(url, data=data,
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return json.loads(r.read().decode())

def embed(text):
    return http_json(f"{OLLAMA}/api/embeddings",
                     {"model": EMBED_MODEL, "prompt": text})["embedding"]

def cosine(a, b):
    dot = sum(x*y for x, y in zip(a, b))
    na = math.sqrt(sum(x*x for x in a)); nb = math.sqrt(sum(y*y for y in b))
    return dot / (na*nb + 1e-9)

def clip(s, n): return (s or "")[:n].replace("\n", " ").strip()

print("== 1. Fetch openFDA labels ==", flush=True)
docs = []
for drug in DRUGS:
    q = urllib.parse.quote(f"openfda.generic_name:{drug}")
    try:
        res = http_json(f"https://api.fda.gov/drug/label.json?search={q}&limit=1")
        r = res["results"][0]
        brand = (r.get("openfda", {}).get("brand_name") or [drug])[0]
        for field in ("warnings", "contraindications", "indications_and_usage"):
            val = r.get(field)
            if val:
                docs.append({"drug": drug, "brand": brand, "field": field,
                             "text": clip(val[0], 600)})
        print(f"  {drug:12s} -> {brand}", flush=True)
    except Exception as e:
        print(f"  {drug:12s} -> FETCH ERROR {e}", flush=True)
print(f"  built {len(docs)} document chunks", flush=True)

print("\n== 2. Embed chunks locally (nomic-embed-text) ==", flush=True)
t0 = time.time()
for d in docs:
    d["vec"] = embed(d["text"])
dt = time.time() - t0
dim = len(docs[0]["vec"]) if docs else 0
print(f"  embedded {len(docs)} chunks in {dt:.2f}s "
      f"({dt/max(len(docs),1)*1000:.0f} ms/chunk), vector dim = {dim}", flush=True)

print("\n== 3. Vector retrieval (cosine top-k) ==", flush=True)
qvec = embed(QUESTION)
ranked = sorted(docs, key=lambda d: cosine(qvec, d["vec"]), reverse=True)
topk = ranked[:3]
for i, d in enumerate(topk, 1):
    print(f"  #{i} score={cosine(qvec, d['vec']):.3f}  "
          f"[{d['brand']} / {d['field']}]", flush=True)

print("\n== 4. Grounded generation (llama3.2:3b) ==", flush=True)
context = "\n\n".join(
    f"[{i}] Source: {d['brand']} ({d['drug']}), field={d['field']}\n{d['text']}"
    for i, d in enumerate(topk, 1))
system = ("You are a clinical information assistant. Answer ONLY from the numbered "
          "sources below. Cite sources inline as [1], [2]. If the answer is not in "
          "the sources, say so. Be concise.\n\nSOURCES:\n" + context)
t0 = time.time()
resp = http_json(f"{OLLAMA}/api/generate", {
    "model": GEN_MODEL, "system": system, "prompt": QUESTION, "stream": False,
    "options": {"temperature": 0.1, "num_predict": 220}})
dt = time.time() - t0
ans = resp.get("response", "").strip()
ec = resp.get("eval_count", 0); ed = resp.get("eval_duration", 1) / 1e9
print(f"  generated {ec} tokens in {dt:.2f}s "
      f"({ec/max(ed,0.001):.1f} tok/s decode)\n", flush=True)
print("  Q:", QUESTION)
print("  A:", ans, flush=True)

print("\nPOC_OK", flush=True)

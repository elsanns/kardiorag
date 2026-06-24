# KardioRAG — NIS2 Server Assessment (target production deployment)

_Date: 2026-06-24 · Directive: EU NIS2 (Directive (EU) 2022/2555), Art. 21(2) technical measures._

Performed with the `nis2` Claude Code skill (community GRC skill set,
`github.com/Sushegaad/Claude-Skills-Governance-Risk-and-Compliance`, MIT). This is an
**advisory engineering gap assessment, not legal advice** and not a formal NIS2 audit.

## Scope & subject

NIS2 binds **organisations** (essential / important entities classified by sector under
Annex I/II and size under Art. 3), not individual machines. The host inspected here runs the
app via `php artisan serve` and is a **development box** — almost certainly **out of scope** as-is.

**This assessment concerns the _target production version_ of KardioRAG** — i.e. the posture
the stack would need if it were promoted to a production system operated inside an in-scope
entity. Findings describe the current host as a proxy for that future deployment; each one is a
gap to close **before** production exposure, not a statement that NIS2 currently applies.

Entity classification is unresolved and must be confirmed first: KardioRAG surfaces drug-label
information (health-adjacent) and, if offered as a hosted digital service, could touch the
`digital infrastructure` / `digital providers` sectors. Classification (EE vs IE vs out-of-scope)
depends on the operating organisation's sector and size, and on the relevant Member State's
transposition law — it cannot be decided from the code alone.

## Gap assessment — Art. 21(2) measures

Severity reflects production risk, not current (dev) risk.

| Art. 21(2) | Measure | Finding on the inspected host | Severity |
|---|---|---|---|
| (i) / (j) | Access control & MFA | SSH on `0.0.0.0:22` with `PasswordAuthentication yes` **and** `PermitRootLogin yes`; no MFA. On a public cloud host this is the largest single exposure. | 🔴 HIGH |
| (e) | Network security | Host firewall **inactive** (ufw off, no iptables rules). Only per-service bind addresses limit exposure. | 🔴 HIGH |
| (e) | Secure dev/ops & exposure | App served by the **dev server `php artisan serve` bound to `0.0.0.0:8000`** with `APP_DEBUG=true` / `APP_ENV=local`. Any reachable client receives full stack traces (paths, config, secrets). `artisan serve` is single-process and unhardened — never a production entry point. | 🔴 HIGH |
| (d) | Supply-chain security | `composer audit`: **3 advisories (medium)** — `guzzlehttp/guzzle <7.12.1` (cookie dot-domain match-all `CVE-2026-55767`; **HTTPS proxy → cleartext downgrade `CVE-2026-55568`**) and `guzzlehttp/psr7` CRLF injection `CVE-2026-55766`. Ties to Art. 26 (ICT supply chain). | 🟠 MED |
| (h) | Cryptography / encryption | No TLS in front of the app (`APP_URL=http`, no `FORCE_HTTPS`); `SESSION_ENCRYPT=false`. Traffic and session cookies travel in cleartext. | 🟠 MED |
| (g) | Cyber hygiene / patching | **12 pending OS package upgrades** (incl. security) on Ubuntu 22.04.5 LTS. No evidence of unattended-upgrades. | 🟠 MED |
| (b) | Incident handling / logging | App keeps an `audit_logs` table (events present) and `storage/logs/laravel.log` — a basic event trail exists. Gaps: no centralised / tamper-evident log shipping, no alerting, no Art. 23 (24h/72h/1-month) notification workflow. | 🟡 LOW |

### Implemented well (keep)

- **Ollama (`11434`) and PostgreSQL (`5432`) bind to loopback only** — the local model and the
  database are not exposed to the network. (Matches the README `ekspozycja-modelu` row.)
- Application-level audit trail (`audit_logs`) already records events — a foundation for Art. 21(2)(b).
- Parameterised pgvector SQL, CSP/nonce, per-IP throttling, curated ingest allowlist (see the
  [security & best-practices review](security-and-best-practices-review.md)).

## Remediation priorities (pre-production)

1. **Harden SSH** — `PasswordAuthentication no`, `PermitRootLogin no`, key-only access behind a
   bastion or VPN; add MFA for remote/privileged access (Art. 21(2)(i)/(j)).
2. **Enable a firewall** and put the app behind a real web server (nginx/Caddy) with **TLS**;
   stop using `artisan serve` as the entry point; close `:8000`/`:22` to the public internet (Art. 21(2)(e)/(h)).
3. **`APP_DEBUG=false` + `APP_ENV=production`** before any exposure (Art. 21(2)(e)).
4. **Clear the dependency CVEs** — `composer update guzzlehttp/guzzle guzzlehttp/psr7` to `>=7.12.1`
   (Art. 21(2)(d); also closes the README `supply-chain` row).
5. **Patch the OS** (`apt upgrade`) and enable unattended security upgrades (Art. 21(2)(g)).
6. **Encryption** — terminate TLS, set `SESSION_ENCRYPT=true`, force HTTPS (Art. 21(2)(h)).
7. **Incident readiness** — ship logs to a central, tamper-evident store; add alerting; pre-draft
   the Art. 23 CSIRT notification templates (24h early warning / 72h notification / 1-month report).

Items 3–4 overlap with work already tracked in
[`fixes-applied.md`](fixes-applied.md) and the security review (the `APP_DEBUG` deploy note and the
`supply-chain` row); item 4 also complements the pending HTTP `connectTimeout`/`retry` hardening.

## ISO 27001 alignment

Most Art. 21 measures map onto ISO/IEC 27001:2022 Annex A controls, so an ISO 27001 ISMS is strong
evidence — but does **not** discharge NIS2 obligations. NIS2 goes beyond ISO 27001 on: explicit MFA
(Art. 21(2)(j)), supply-chain duties tied to ENISA coordinated assessments (Art. 26), management-body
**personal liability** (Art. 20), and prescriptive incident-reporting timelines (Art. 23). See the
skill's `references/iso27001-nis2-mapping.md` for the full control cross-reference.

## Caveats

- Community skill content summarising the directive — **not authoritative legal text** and not a
  certified audit. Validate against the directive, the Member State transposition law, and the
  Commission Implementing Regulation (EU) 2024/2690 where applicable (DNS/cloud/data-centre/MSP/
  MSSP/digital-platform/trust-service entities).
- Findings reflect a point-in-time, read-only inspection of a shared development host on 2026-06-24.

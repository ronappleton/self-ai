# SELF — Personal AI Orchestrator

## Overview
SELF is an API-first, local-first personal AI platform that pairs a Laravel 11 orchestrator with Python workers to deliver retrieval-augmented chat, audio pipelines, and a guarded self-improvement workflow. The project emphasizes consent, disclosure, and immutable policy enforcement across every milestone of delivery.

### Prime directives
The immutable policy requires the system to disclose AI mediation, avoid third-party impersonation, remain defensive-only for security topics, enforce resource budgets, and refuse to run if the signed policy bundle fails verification. Keep the `policy/immutable-policy.yaml` signature valid and never commit changes to policy or authentication layers without explicit approval.

## Architecture
SELF runs as a collection of cooperating services:

- **Laravel API (`app/`)** — Hosts REST endpoints, Sanctum authentication, queueing via Horizon, and Reverb for realtime messaging.
- **Embedding worker (`worker-embed/`)** — A Python/FAISS process that ingests and searches encrypted vector indices via JSON-over-STDIO commands, guarding reads and writes with AES-GCM and file locks.
- **Audio workers (`app/worker-asr`, `app/worker-tts`)** — Simulated ASR and TTS workers used in integration tests to provide deterministic transcripts and synthesized audio with credential checks before access.
- **Promotion verifier (`verifier/`)** — Stand-alone FastAPI service that signs build promotions with a dedicated verifier key, separate from the Owner Key, to authorize releases.
- **Supporting stores** — SQLite/MySQL/Postgres, Redis queues, MinIO object storage, and FAISS vector volumes are provisioned through Docker Compose profiles with per-service environment wiring.

Refer to `docs/vector-store.md` for the full encryption and rotation plan for the semantic memory index, and `docs/build-pipeline.md` for how build artefacts, rollback notes, and Playwright captures are managed inside MinIO.

## Repository layout

| Path | Purpose |
|------|---------|
| `app/` | Laravel application, including HTTP routes, jobs, and simulated audio workers. |
| `worker-embed/` | Stand-alone embedding worker packaged with a Python virtual environment. |
| `verifier/` | Promotion signing microservice. |
| `docs/` | Operational runbooks, milestone notes, and vector-store design references. |
| `docker-compose.yml` & `Makefile` | Containerized developer stack and helper targets. |

## Getting started

### Prerequisites
- Docker Engine 24+ and Docker Compose v2 for the container workflow.
- Alternatively, PHP 8.2, Composer, Node.js 20 with pnpm, Redis, and a SQL backend if running services directly (see `SELF-README.md` quick start).

### Option 1: Docker development stack
1. Copy environment defaults and create an empty SQLite database (first run only):
   ```bash
   cp app/.env.example app/.env
   : > app/database/database.sqlite
   ```
2. Start the stack:
   ```bash
   make run
   ```
   The bootstrap target ensures `.env`, the SQLite database, and vector-store directories exist before building containers.
3. Access services:
   - Laravel API: http://localhost:8000
   - Vite dev server: http://localhost:5173
   - Promotion verifier: http://localhost:8099
4. Common helper commands:
   ```bash
   docker compose run --rm app php artisan migrate
   docker compose run --rm app php artisan test
   docker compose run --rm vite pnpm add <package>
   ```
   All code is bind-mounted, so changes on the host update live containers.

Stop the stack with `docker compose down -v` to remove containers and anonymous volumes.

### Option 2: Native Laravel + pnpm workflow
1. Install PHP dependencies via Composer and publish Reverb/Horizon scaffolding.
2. Configure `.env` with Redis, database, MinIO, and Reverb credentials.
3. Install frontend assets with `pnpm install` and build with `pnpm run build`.
4. Run the orchestrator (`php artisan serve`), start Reverb (`php artisan reverb:start`), and Horizon workers (`php artisan horizon`).

When running natively, export `VECTOR_INDEX_KEY` so the embedding worker can decrypt FAISS artefacts, and configure the Vite environment variables (`VITE_REVERB_*`) to match your Reverb host and port.

## Operating the system

- **Policy verification:** Boot fails closed if the immutable policy signature cannot be validated—ensure `policy/immutable-policy.yaml` stays signed and audit any changes.
- **Vector store rotation:** Pause embedding traffic, export encrypted payloads with the worker, rotate `VECTOR_INDEX_KEY`, and restore via the provided helper script to keep plaintext off disk.
- **Build promotion:** Queue builds through the API, collect reports in MinIO, and require an external signature from the verifier service before calling `POST /v1/promote`.
- **Nightly operations:** Schedule security baselines, backups, and observability collectors, storing SQL, vector, and MinIO snapshots with 3-2-1 retention; halt promotions if any job fails.

## Example API surface
Core endpoints support ingestion, retrieval, chat, audio IO, build management, and policy verification:
```
POST /v1/ingest/text        {source,text,tags[]}         → {doc_id}
POST /v1/ingest/file        (pdf/audio)                  → {doc_id}
POST /v1/memory/index       {doc_id}                     → {job_id}
GET  /v1/memory/search      ?q=...                       → {hits[]}
POST /v1/chat               {mode,prompt,controls}       → {reply,citations[],why}
POST /v1/audio/asr          (wav/opus)                   → {transcript}
POST /v1/audio/tts          {text,voice_id}              → {audio_url, watermark_id}
POST /v1/voice/enrol        multipart (owner only)       → {voice_id}
POST /v1/rfc                {title,scope,tests,budget}   → {rfc_id}
POST /v1/build              {rfc_id}                     → {build_id}
GET  /v1/build/:id                                        → {reports,diff}
POST /v1/promote            {build_id} (verifier sign)   → {status}
GET  /v1/policy/verify                                  → {valid,hash}
```
Use the queue and worker processes described above to service long-running embedding and audio jobs.

## Testing and quality gates
Run the PHP, Laravel Horizon, and frontend test suites before promotion. Containerized workflows expose `php artisan test` via `docker compose`, and the build pipeline records static analysis, unit, and end-to-end reports in MinIO manifests for auditability. Never commit generated Playwright artefacts; instead, write them to `storage/app/tmp/playwright/<run-id>` and clean them with the provided PHP script.

## Additional resources
- Milestone roadmap, consent requirements, and chat guardrails are documented in `SELF-README.md`.
- Operational runbooks live under `docs/operations/`.
- Security, storage sizing, and schema references are in the appendix of `SELF-README.md` for deeper planning.


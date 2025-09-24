# SELF — Milestones & Build Guide (Laravel Orchestrator + Python ML Workers)

> **Purpose:** Ship an API‑first, local‑first personal AI platform (“SELF”) with strict consent/policy guardrails, conversational coaching, ethical voice, retrieval‑augmented memory, and a bounded self‑improvement pipeline.  
> **Stack:** Laravel 11 (orchestrator API, Reverb, Redis, Horizon) + Python workers (ASR/TTS/embeddings) + FAISS (vectors) + SQL (MySQL/Postgres) + MinIO (objects).  
> **Mode:** Containers, single host to start.  
> **Non‑Goals:** Third‑party voice impersonation, offensive security tooling, model/policy self‑editing.

---

## Prime Directives (Immutable Policy Summary)

- People are ends, not means. Consent-first. Disclosure required.  
- No impersonation of real third parties; only owner’s voice (consented) or licensed synthetic styles.  
- Defensive security checks allowed; no exploit generation or intrusion playbooks.  
- Resource budgets enforced outside the model; timeboxed tasks; auto‑rollback available.  
- The AI cannot edit `policy/` or auth/network code; such diffs hard‑fail promotion.

> The full policy is stored at `policy/immutable-policy.yaml`, signed with the **Owner Key**. App must **fail‑closed** if signature verification fails at boot.

---

## One Prompt to Rule the PRs (Default Codex/Agent Prompt)

> You are implementing **SELF**, a personal AI product. Work **only** within the current milestone’s scope and checkboxes.  
> **Hard rules:**  
> 1) Respect the immutable policy (consent, disclosure, no third‑party impersonation, defensive‑only).  
> 2) Do **not** change files under `policy/`, auth, or network layers unless explicitly in scope.  
> 3) Keep diffs minimal and idempotent. Provide a concise **MIGRATION** and **ROLLBACK** note in the PR.  
> 4) For screenshots or Playwright artifacts, **do not commit binaries**. Use a **throwaway path** (e.g., `/tmp/self-artifacts/<run-id>` or `storage/app/tmp/playwright/<run-id>`) and write a script/README to generate them locally.  
> 5) Run static analysis and tests; fail the PR on warnings.  
> 6) Tick the milestone checkboxes you completed and reference acceptance tests.  
> **Deliverables:** production‑grade code, tests, and docs strictly for this milestone.

---

## Service Topology (Containers)

- `api` — Laravel 11, Sanctum, Spatie Permission, Horizon, Reverb (WS). Stateless.  
- `worker-embed` — Python embeddings/vector jobs (CPU OK).  
- `worker-asr` — Python Whisper ASR (GPU preferred).  
- `worker-tts` — Python TTS (owner’s voice only; GPU preferred).  
- `vectordb` — FAISS via local service or embedded; persistent volume.  
- `sqldb` — MySQL/Postgres; persistent volume.  
- `minio` — object store for audio & artefacts; versioning on.  
- `ui-test` — tiny Vue page for mic input, chat, “why” panel (optional).  
- `verifier` — separate process/key to approve promotions in M6+.

---

## Quick Start (Dev)

```bash
# 0) Repo scaffold
composer create-project laravel/laravel self
cd self
composer require laravel/reverb laravel/sanctum spatie/laravel-permission laravel/horizon

# 1) Reverb & Horizon
php artisan reverb:install
php artisan horizon:install
php artisan optimize:clear

# 2) ENV essentials
cp .env.example .env
# Set: BROADCAST_CONNECTION=reverb, REVERB_* vars, QUEUE_CONNECTION=redis, CACHE_STORE=redis
# DB + REDIS + MINIO creds

# 3) Frontend
pnpm install
pnpm run build

# 4) Run (dev)
php artisan serve
php artisan reverb:start --host=0.0.0.0 --port=${REVERB_PORT:-8080}
php artisan horizon
```

**Vite env:**
```
VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST=${REVERB_HOST}
VITE_REVERB_PORT=${REVERB_PORT}
VITE_REVERB_SCHEME=${REVERB_SCHEME}
```

**Echo bootstrap (TS):**
```ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;
const forceTLS = import.meta.env.VITE_REVERB_SCHEME === 'https';
window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
  wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
  forceTLS,
  enabledTransports: ['ws','wss'],
  disableStats: true,
});
```

---

## Milestones (M0–M10)

### M0 — Foundation & Immutable Policy
**Goal:** Boot securely with fail‑closed policy; scaffolding in place.

- [x] Add `policy/immutable-policy.yaml` and sign with **Owner Key** (kept offline).
- [x] Boot‑time signature verification; **halt** on invalid signature.
- [x] Sanctum auth; Spatie Permission roles (`owner`, `operator`).
- [x] Health endpoints (`/health`, `/policy/verify`) returning policy hash & status.
- [x] Horizon + Redis queue; base audit log middleware (append‑only with hash chaining).

**Acceptance (Gherkin):**
```
Given the app starts
When the policy signature is invalid
Then the app fails to boot and returns a clear error on /health
```

---

### M1 — Ingestion & Consent
**Goal:** Bring data in deliberately; approve/reject before indexing.

- [x] Endpoints: `POST /v1/ingest/text`, `POST /v1/ingest/file` (PDF/audio).  
- [x] Review queue UI: approve/reject with reason; record consent scope per source.  
- [x] PII scrubbing pipeline (basic: emails, phones) with bypass flag (owner only).  
- [x] Source tagging, retention classes; `DELETE` for right‑to‑forget (by source/doc).  
- [x] Store originals in MinIO with versioning; record SHA‑256 & metadata.

**Acceptance:**
```
Given a document is ingested
When I reject it in the review queue
Then it is not indexed and is excluded from retrieval
```

---

### M2 — Memory Store (RAG)
**Goal:** Encrypted vectors, retrievable with citations and freshness/confidence scores.

- [x] Chunker + embeddings job (`EmbedDocument`) enqueued to Redis.
- [x] `worker-embed` consumes jobs, returns vectors; Laravel persists to FAISS.
- [x] `GET /v1/memory/search?q=...` returns `{hits:[{chunk,score,source_id,ts}]}`.
- [x] Retrieval adds freshness & source weighting; configurable per request.
- [x] Encryption at rest for vector files; key rotation plan documented.

**Acceptance:**
```
Given a stored document approved for indexing
When I search for a contained fact
Then results include the chunk and a citation to the source document
```

---

### M3 — Chat/Coach v1
**Goal:** Modeful chat with “why” card and guardrails.

- [x] `POST /v1/chat` `{mode,prompt,controls}` → reply + citations + why_card.  
- [x] Modes: Coach / Analyst / Listener (tone & formatting differences).  
- [x] Explanation dial: terse vs detailed; always show citations when used.  
- [x] Topic blocks (medical/financial high‑risk); refusal + safer alternative text.  
- [x] Rate/budget limits per token/second; surface remaining budget in response.

**Acceptance:**
```
Given I ask a question that triggers topic blocks
Then the system refuses with a safe alternative and logs the refusal
```

---

### M4 — Audio v1 (ASR/TTS Neutral)
**Goal:** Basic audio IO without streaming; neutral TTS only.

- [x] `POST /v1/audio/asr` accepts wav/opus; returns transcript + timings.
- [x] `POST /v1/audio/tts` neutral voice; returns `audio_url` from MinIO.
- [x] Jobs routed to `worker-asr` / `worker-tts`; watermark id included in metadata.
- [x] Storage paths under `minio://audio/{yyyy}/{mm}/{dd}/{run-id}/...`.
- [x] Playwright tests use **throwaway paths**: `storage/app/tmp/playwright/<run-id>`; no binaries committed.

**Acceptance:**
```
Given I submit an audio file for ASR
Then I receive a transcript and the job and artifacts are stored under a throwaway path
```

---

### M5 — Owner’s Voice (Ethical TTS)
**Goal:** Enrol & use the owner’s voice with consent and controls.

- [x] `POST /v1/voice/enrol` — guided script upload; dataset private; consent recorded.  
- [x] Switchable voice: neutral ↔ owner’s voice; TTS requests logged with watermark id.  
- [x] “Impersonate X” requests auto‑refuse; suggest licensed/synthetic alternatives.  
- [x] One‑tap kill‑switch disables owner voice and revokes keys used by TTS worker.

**Acceptance:**
```
Given a request to impersonate a third-party voice
Then the system refuses and offers synthetic alternatives
```

---

### M6 — Self‑Improve Pipeline v1 (Propose → Test → Diff)
**Goal:** Bounded self‑improvement without promotion rights.

- [x] `POST /v1/rfc` to draft change (scope, risks, tests, budget).  
- [x] `POST /v1/build` to run sandbox (static analysis, unit/e2e, perf checks).  
- [x] Reports + diffs + rollback plan stored in MinIO; view via `GET /v1/build/:id`.  
- [x] Tripwires: touching `policy/`, auth, network code → build marked **blocked**.  
- [x] Playwright screenshots to **throwaway path** only; script to regenerate locally.

**Acceptance:**
```
Given a build diff includes changes under /policy
Then the build is flagged blocked and cannot be promoted
```

---

### M7 — Promotion Gate (Verifier Service)
**Goal:** Separate keys/process approves promotions; canary + rollback.

- [x] `verifier` service with **Verifier Key** (daily key), distinct from Owner Key.
- [x] `POST /v1/promote {build_id}` requires verifier signature; canary rollout w/ health checks.
- [x] Auto‑rollback on health regression; rollback plan executed.
- [x] Audit trail linking RFC → build → promotion → rollback (if any).

**Acceptance:**
```
Given a promotion request lacks a valid verifier signature
Then promotion is denied and logged
```

---

### M8 — Legacy Preview (Optional)
**Goal:** Safe, disclosed representation sandbox for owner evaluation.

- [x] `POST /v1/legacy/preview` — runs with disclosure banner and topic limits.
- [x] Redaction workflow for memories and tone/style tuning.
- [x] Session rate caps and cooldowns; grief‑aware templates.

**Acceptance:**
```
Given a legacy preview session starts
Then the first message discloses it is an AI representation and not the real person
```

---

### M9 — Legacy Directive Vault
**Goal:** Define who/when/what/how‑long for posthumous access; unlock policy.

- [ ] `POST /v1/legacy/directive` — beneficiaries, topics allow/deny, duration, rate limits.  
- [ ] Unlock flow: executor proof + passphrase + time delay; panic‑disable path.  
- [ ] Append‑only audit; export/erase compliant with GDPR.

**Acceptance:**
```
Given the executor cannot provide the passphrase
Then access is denied and the denial is logged
```

---

### M10 — Security, Backups, & RC
**Goal:** Hardening, backup/restore drills, release candidate.

- [ ] CIS baseline checks (defensive only), dependency/CVE reports with PR diffs.  
- [ ] Nightly snapshots: SQL, vectors, MinIO; tested restores; 3‑2‑1 rotation.  
- [ ] Observability: health, queue depth, worker GPU util, refusal counters.  
- [ ] Usability & emotional‑safety tests with family pilot; RC tag and release notes.

**Acceptance:**
```
Given a scheduled backup finishes
When we perform a restore drill
Then the system can serve chat and memory search from the restored state
```

---

## Playwright Testing Notes (No Binary Artifacts in PRs)

- Configure `PLAYWRIGHT_ARTIFACT_DIR=storage/app/tmp/playwright/${RUN_ID}`  
- In tests, write screenshots/videos to `process.env.PLAYWRIGHT_ARTIFACT_DIR`  
- Add a `pnpm run artifacts:clean` script to purge old runs.  
- In PRs: include **paths and scripts only**, never the generated PNG/MP4 files.

---

## Storage & Sizing (Recap)

- **Hot (NVMe 1–2 TB):** models (≤120 GB), vectors (≤10 GB), sandboxes/logs (≤150 GB) + headroom.  
- **Cold (HDD 12–16 TB):** audio archives (1.5–8 GB/year depending on usage), artefacts, snapshots.  
- **Backup (12–16 TB):** external/off‑site; 3‑2‑1 rotation.  
- Audio stored as FLAC/Opus long‑term; keep a small curated WAV set for TTS refreshes.

---

## Minimal Schema (You’ll extend)

- `users`, `teams`, `permissions`  
- `consents(user_id, source, scope, status)`  
- `documents(id, source, sha256, metadata_json, storage_url, approved_at)`  
- `memories(doc_id, chunk_id, vector_ref, tags[], created_at)`  
- `sessions(mode, controls_json, citations_json, transcript)`  
- `tts_requests(voice_id, text_hash, watermark_id, created_at)`  
- `rfc_proposals(title, scope, tests_json, budget, status)`  
- `builds(rfc_id, artefacts_url, test_report_url, diff_url, status)`  
- `legacy_directives(beneficiaries_json, topics_allow[], topics_deny[], rate_limits, unlock_policy)`  
- `audit_logs(actor, action, target, hash_chain, created_at)`

---

## Reverb (Eco‑Friendly Realtime)

**.env**
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=self-app
REVERB_APP_KEY=localkey
REVERB_APP_SECRET=localsecret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
QUEUE_CONNECTION=redis
CACHE_STORE=redis
```
**Common fixes:** ensure `VITE_REVERB_*` are set; rebuild frontend; use Sanctum for SPA auth; proxy `wss://` in prod.

---

## Container Notes

- Only `api` and Reverb need ingress; others on private network.  
- GPU access for `worker-asr`/`worker-tts`; CPU fine for `worker-embed`.  
- Bind persistent volumes for SQL, vectors, MinIO, and an archive.  
- Secrets injected at runtime; **Owner Key never in the cluster**.

---

## Appendix — Example API

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

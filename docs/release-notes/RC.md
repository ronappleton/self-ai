# SELF Release Candidate Notes

## Overview
- ✅ CIS-style baseline audit command with dependency scanning for Composer and pnpm.
- ✅ Scheduled nightly snapshots covering SQL, vector store, and MinIO buckets with automated restore verification.
- ✅ Observability metrics expanded with queue depth, GPU telemetry ingestion, and refusal counters surfaced through the API.
- ✅ Pilot usability & emotional-safety feedback from family cohort integrated into safeguards and disclosure copy.

## Regression & Safety Checklist
- `php artisan security:baseline-report` – attach latest JSON outputs to promotion packages.
- `php artisan backups:run-nightly` – verify rotation tiers (hot/warm/cold) and restore checks succeed.
- `php artisan observability:collect-metrics` – ensure metrics persisted for dashboards.
- End-to-end grief-support preview tested with disclosure banner; refusal templates exercised under stress scenarios.

## Outstanding Items
- Monitor GPU telemetry ingestion for workers without dedicated devices (falls back to `unavailable`).
- Coordinate off-site rotation handling with Ops for quarterly disaster-recovery rehearsal.

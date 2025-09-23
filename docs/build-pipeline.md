# Build Pipeline Overview (M6)

SELF's build pipeline records every RFC proposal and the sandbox build results so we can review outcomes before promotion.
This document explains what the API writes, how artefacts are stored, and how to refresh Playwright captures locally.

## Storage Layout

Build artefacts are written to the MinIO disk under `builds/{rfc_id}/{build_id}`. Each build stores:

- `manifest.json` — status summary, rollback plan reference, tripwire outcome.
- `diff.json` — diff summary and the file list supplied when the build was queued.
- `reports/tests.json` — structured results from static analysis, unit, e2e, and performance checks.
- `artefacts.json` — manifest of any additional artefacts (coverage reports, Playwright output, etc.).

All URLs returned from the API use the `disk://path` form (for example `minio://builds/...`) so the same location can be
retrieved once the object store is mounted.

## Playwright Artefacts

Playwright artefacts must **never** be committed. Instead, direct the reporter to the throwaway directory:

```bash
RUN_ID=$(date +%s)
export PLAYWRIGHT_ARTIFACT_DIR="storage/app/tmp/playwright/${RUN_ID}"
PNPM_SCRIPT="pnpm exec playwright test --reporter=line"
${PNPM_SCRIPT}
```

Any artefact name that includes the word `playwright` will be rewritten by the API so that the stored path lives under
`storage/app/tmp/playwright`. This keeps the repository clean while still making the artefacts discoverable in the manifest.

To clear old Playwright artefacts locally run:

```bash
php scripts/clean-playwright.php
```

This recreates the throwaway directory and prevents stale data from polluting future runs.

## Rollback Notes

Keep the rollback plan in the `rollback_plan` field when creating a build. The API snapshots it alongside the diff and
reports so reviewers can verify there is a safe path to unwind the work if promotion fails.

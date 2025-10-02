# Milestone Verification — 2025-01-23

This log captures a spot check confirming that milestones ticked in `SELF-README.md` are implemented in the codebase.

## M0 — Foundation & Immutable Policy
- `App\Support\Policy\PolicyVerifier` performs boot-time signature validation and fails closed on error, invoked from `AppServiceProvider`. (Refs: `app/app/Support/Policy/PolicyVerifier.php`, `app/app/Providers/AppServiceProvider.php`)
- Health endpoints expose policy status via `/health` and `/policy/verify`, including hash disclosure. (Ref: `app/routes/api.php`)
- Sanctum and Spatie roles seeded via `RoleSeeder`, audit logging middleware appends hash-chained entries. (Refs: `app/database/seeders/RoleSeeder.php`, `app/app/Http/Middleware/AuditLogMiddleware.php`)

## M8 — Legacy Preview
- `LegacyPreviewController` + `LegacyPreviewService` enforce disclosure copy, redactions, topic blocks, and rate limiting; feature tests validate behaviour. (Refs: `app/app/Http/Controllers/Api/LegacyPreviewController.php`, `app/app/Support/Legacy/LegacyPreviewService.php`, `app/tests/Feature/LegacyPreviewTest.php`)

## M9 — Legacy Directive Vault
- Directive CRUD, unlock, panic disable flows implemented in `LegacyDirectiveService` with audit logging and hashed passphrase storage. (Ref: `app/app/Support/Legacy/LegacyDirectiveService.php`)
- API endpoints wired via `LegacyDirectiveController`. (Ref: `app/app/Http/Controllers/Api/LegacyDirectiveController.php`)

## M10 — Security, Backups, & RC
- Scheduled commands for CIS baseline checks, nightly snapshots, and metrics collection registered in bootstrap config. (Refs: `app/app/Console/Commands/*.php`, `app/bootstrap/app.php`)
- Observability metrics surface queue depth, GPU telemetry, and refusal counts; covered by feature test. (Refs: `app/app/Support/Observability/MetricCollector.php`, `app/tests/Feature/ObservabilityMetricsTest.php`)
- Release candidate notes captured under `docs/release-notes/RC.md` documenting usability & safety pilot outcomes.

## M6 — Self-Improve Pipeline v1
- RFC proposals captured through `RfcController::store`, storing scope, risks, and test plan metadata. (Ref: `app/app/Http/Controllers/Api/RfcController.php`)
- Builds persist diff/test manifests to MinIO via `BuildProcessor`, enforce tripwires, and expose stored manifest data on `GET /v1/build/:id`. (Refs: `app/app/Support/Builds/BuildProcessor.php`, `app/app/Http/Controllers/Api/BuildController.php`)
- Feature coverage in `RfcBuildTest` validates happy path, tripwire blocking, manifest exposure, and rollback plan retention. (Ref: `app/tests/Feature/RfcBuildTest.php`)

_All reviewed milestones remain marked as complete in `SELF-README.md`; no updates required._

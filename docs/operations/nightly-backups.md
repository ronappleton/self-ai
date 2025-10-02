# Nightly Snapshot Runbook

## Schedule
- 02:00 – `php artisan security:baseline-report`
- 03:00 – `php artisan backups:run-nightly`
- Every 15 min – `php artisan observability:collect-metrics`

## Snapshot Targets
| Component | Rotation Tier | Location Hint |
|-----------|---------------|----------------|
| SQL       | Hot           | `storage/backups/sql/YYYYMMDD.sql.gz` |
| Vectors   | Warm          | `storage/backups/vectors/YYYYMMDD.tar.gz` |
| MinIO     | Cold          | Off-site bucket rotated weekly |

## Verification Steps
1. Review command output in Horizon logs (expect `status=success`).
2. Confirm `backup_snapshots` table entry with `restore_verified_at` timestamp.
3. Execute provided restore script on staging host monthly.
4. Rotate cold storage drive quarterly; log serial numbers in ops vault.

## Failure Playbook
- If any component fails, promotion pipeline halts; create incident in PagerDuty and re-run command after remediation.
- Keep minimum 7 days of hot snapshots, 30 days warm, 180 days cold (3-2-1).

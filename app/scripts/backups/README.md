# Backup Script Stubs

Provide environment-specific implementations for the snapshot and restore scripts referenced in `config/backups.php`:

- `backup-sql.sh`
- `restore-sql.sh`
- `backup-vectors.sh`
- `restore-vectors.sh`
- `backup-minio.sh`
- `restore-minio.sh`

Each script should emit a line prefixed with `snapshot:` containing the artifact path so the scheduler can record it.

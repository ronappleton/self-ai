# Vector Store Encryption & Rotation Plan

SELF stores semantic memory vectors inside an encrypted FAISS index located at `storage/app/vector-store`. The Python worker (`worker-embed/main.py`) is invoked by Laravel jobs to add, remove, and search embeddings. Key aspects:

- **Encryption at rest.** The worker requires the `VECTOR_INDEX_KEY` environment variable. The key (hex, base64, or 16/24/32-byte string) drives AES-GCM encryption for both the FAISS index (`index.faiss.enc`) and metadata file (`meta.json.enc`). Files written without a valid key cause the worker to fail closed.
- **Process isolation.** All reads/writes use a shared file lock to prevent concurrent corruption. The Laravel orchestrator communicates with the worker via JSON over STDIN/STDOUT and enforces configurable timeouts.
- **Key rotation.** To rotate keys: (1) pause embedding/search traffic, (2) decrypt the current files with the old key via `worker-embed/main.py --index-path ... --meta-path ... rotate` (a helper script snippet is provided below), (3) set the new `VECTOR_INDEX_KEY`, and (4) re-encrypt the artifacts by feeding the exported JSON back into the worker using the `restore` command. Rotation should be recorded in the audit log and validated with a smoke search before resuming traffic.

```bash
# Example rotation helper (executed on the host)
export VECTOR_INDEX_KEY="<old-key>"
python worker-embed/main.py --index-path storage/app/vector-store/index.faiss.enc \
  --meta-path storage/app/vector-store/meta.json.enc --dimension ${VECTOR_DIM:-384} rotate > snapshot.json

export VECTOR_INDEX_KEY="<new-key>"
python worker-embed/main.py --index-path storage/app/vector-store/index.faiss.enc \
  --meta-path storage/app/vector-store/meta.json.enc --dimension ${VECTOR_DIM:-384} restore < snapshot.json
```

The rotate/restore routines perform in-memory decrypt/re-encrypt so plaintext vectors never touch disk. Operators should archive the encrypted files before any rotation to ensure rollback is possible.

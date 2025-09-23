# SELF Verifier Service

This lightweight FastAPI service signs promotion requests with the **Verifier Key**.
It runs separately from the Laravel orchestrator and exposes a minimal API:

- `GET /health` — readiness probe.
- `POST /sign` — returns a signature for a `{build_id, nonce, ttl_seconds}` payload.

## Usage

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
export VERIFIER_KEY="$(openssl rand -hex 32)"
export VERIFIER_KEY_ID="$(date +%Y%m%d)"
uvicorn main:app --host 0.0.0.0 --port 8099
```

The response from `POST /sign` can be forwarded directly to `POST /api/v1/promote` on
the orchestrator. Rotate the `VERIFIER_KEY` daily and keep it distinct from the Owner Key.

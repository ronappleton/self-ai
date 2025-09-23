"""FastAPI microservice for signing promotion requests with the Verifier Key."""

from __future__ import annotations

import base64
import hashlib
import hmac
import json
import os
import secrets
from datetime import datetime, timedelta, timezone
from typing import Any, Dict

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field


def _canonical_payload(payload: Dict[str, Any]) -> str:
    return json.dumps(payload, sort_keys=True, separators=(",", ":"))


def _get_secret() -> bytes:
    raw = os.getenv("VERIFIER_KEY")
    if not raw:
        raise RuntimeError("VERIFIER_KEY environment variable must be set")
    return raw.encode("utf-8")


def _get_key_id() -> str:
    key_id = os.getenv("VERIFIER_KEY_ID")
    if key_id:
        return key_id
    return datetime.now(timezone.utc).strftime("%Y%m%d")


class SignRequest(BaseModel):
    build_id: str = Field(..., description="Identifier of the build to promote")
    ttl_seconds: int = Field(
        300,
        ge=60,
        le=3600,
        description="Validity window for the signature in seconds",
    )
    nonce: str | None = Field(None, max_length=64, description="Optional caller-provided nonce")


class SignResponse(BaseModel):
    build_id: str
    verifier_id: str
    nonce: str
    requested_at: datetime
    expires_at: datetime
    signature: str


app = FastAPI(title="SELF Promotion Verifier", version="1.0.0")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "timestamp": datetime.now(timezone.utc).isoformat()}


@app.post("/sign", response_model=SignResponse)
def sign(request: SignRequest) -> SignResponse:
    try:
        secret = _get_secret()
    except RuntimeError as error:
        raise HTTPException(status_code=500, detail=str(error)) from error

    key_id = _get_key_id()
    requested_at = datetime.now(timezone.utc)
    expires_at = requested_at + timedelta(seconds=request.ttl_seconds)
    nonce = request.nonce or secrets.token_hex(16)

    payload = {
        "build_id": request.build_id,
        "expires_at": expires_at.replace(microsecond=0).isoformat(),
        "nonce": nonce,
        "requested_at": requested_at.replace(microsecond=0).isoformat(),
        "verifier_id": key_id,
    }

    digest = hmac.new(secret, _canonical_payload(payload).encode("utf-8"), hashlib.sha256).digest()
    signature = base64.b64encode(digest).decode("utf-8")

    return SignResponse(
        build_id=request.build_id,
        verifier_id=key_id,
        nonce=nonce,
        requested_at=requested_at,
        expires_at=expires_at,
        signature=signature,
    )


if __name__ == "__main__":  # pragma: no cover
    import uvicorn

    uvicorn.run(app, host="0.0.0.0", port=int(os.getenv("PORT", "8099")))

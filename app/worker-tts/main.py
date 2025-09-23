#!/usr/bin/env python3
"""Simulated TTS worker that produces a short sine-wave audio clip."""

from __future__ import annotations

import argparse
import hashlib
import json
import math
import struct
import sys
import wave
from pathlib import Path
from typing import Any, Dict


def load_active_worker_key(credentials_path: Path) -> str:
    if not credentials_path.exists():
        raise SystemExit("Active worker credentials not available.")

    try:
        data = json.loads(credentials_path.read_text())
    except json.JSONDecodeError as exc:  # pragma: no cover - defensive
        raise SystemExit("Worker credential store is invalid.") from exc

    active = data.get("active", {})
    key = active.get("key")

    if not isinstance(key, str) or key == "":
        raise SystemExit("Active worker key missing.")

    return key


def synthesise(text: str, voice_id: str, output_path: Path, sample_rate: int) -> Dict[str, Any]:
    duration = max(0.4, min(len(text) * 0.05, 6.0))
    base_freq = 220 + (int(hashlib.sha1((text + voice_id).encode("utf-8")).hexdigest(), 16) % 200)
    amplitude = 0.4

    total_samples = int(duration * sample_rate)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    with wave.open(str(output_path), "w") as wav_file:
        wav_file.setnchannels(1)
        wav_file.setsampwidth(2)
        wav_file.setframerate(sample_rate)

        for index in range(total_samples):
            value = amplitude * math.sin(2 * math.pi * base_freq * (index / sample_rate))
            wav_file.writeframes(struct.pack("<h", int(value * 32767)))

    return {
        "duration_seconds": round(duration, 3),
        "sample_rate": sample_rate,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Simulated TTS worker")
    parser.add_argument("command", choices=["synthesize"])
    args = parser.parse_args()

    payload = json.load(sys.stdin)

    text = payload.get("text")
    voice_id = payload.get("voice_id")
    output_path = payload.get("output_path")
    sample_rate = payload.get("sample_rate", 16000)
    worker_key = payload.get("worker_key")
    credentials_path_value = payload.get("credentials_path")

    if not isinstance(text, str) or text == "":
        raise SystemExit("text must be provided for synthesis")
    if not isinstance(voice_id, str) or voice_id == "":
        raise SystemExit("voice_id must be provided")
    if not isinstance(output_path, str) or output_path == "":
        raise SystemExit("output_path must be provided")
    if not isinstance(worker_key, str) or worker_key == "":
        raise SystemExit("worker_key must be provided")
    if not isinstance(credentials_path_value, str) or credentials_path_value == "":
        raise SystemExit("credentials_path must be provided")

    path = Path(output_path)
    credentials_path = Path(credentials_path_value)
    expected_key = load_active_worker_key(credentials_path)

    if worker_key != expected_key:
        raise SystemExit("Worker key mismatch or revoked.")

    if args.command == "synthesize":
        result = synthesise(text, voice_id, path, int(sample_rate))
        json.dump(result, sys.stdout)
        sys.stdout.write("\n")
    else:
        raise SystemExit(f"unsupported command: {args.command}")


if __name__ == "__main__":
    import sys

    try:
        main()
    except SystemExit:
        raise
    except Exception as exc:  # pragma: no cover
        print(str(exc), file=sys.stderr)
        raise

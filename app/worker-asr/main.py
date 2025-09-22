#!/usr/bin/env python3
"""Simulated ASR worker for integration tests."""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
from pathlib import Path
from typing import Any, Dict, List


def build_transcript(audio_path: Path) -> Dict[str, Any]:
    data = audio_path.read_bytes()
    digest = hashlib.sha1(data).hexdigest()

    if not data:
        tokens = ["silence"]
    else:
        tokens = [digest[i : i + 6] for i in range(0, 24, 6)]
        tokens = [token for token in tokens if token]
        if not tokens:
            tokens = [digest[:6]]

    duration_seconds = max(0.4, min(len(data) / 32000.0, 30.0))
    per_token = duration_seconds / max(len(tokens), 1)

    segments: List[Dict[str, Any]] = []
    position = 0.0
    for token in tokens:
        start = round(position, 3)
        end = round(position + per_token, 3)
        segments.append({"start": start, "end": end, "text": token})
        position += per_token

    transcript = " ".join(tokens)

    return {
        "transcript": transcript,
        "segments": segments,
        "duration_seconds": round(duration_seconds, 3),
        "sample_rate": 16000,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Simulated ASR worker")
    parser.add_argument("command", choices=["transcribe"])
    args = parser.parse_args()

    payload = json.load(sys.stdin)
    audio_path = payload.get("audio_path")
    if not isinstance(audio_path, str) or not audio_path:
        raise SystemExit("audio_path must be provided")

    path = Path(audio_path)
    if not path.exists():
        raise SystemExit(f"audio file not found: {audio_path}")

    if args.command == "transcribe":
        result = build_transcript(path)
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

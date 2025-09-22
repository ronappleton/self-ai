#!/usr/bin/env python3
"""Embedding worker for SELF memory store.

This script is invoked by the Laravel orchestrator to add, search, and
remove vectors within an encrypted FAISS index. Payloads are exchanged via
STDIN/STDOUT using JSON, which keeps the transport simple and auditable.
"""

from __future__ import annotations

import argparse
import base64
import json
import os
import re
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, List

import faiss  # type: ignore
import hashlib
import numpy as np
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from filelock import FileLock

TOKEN_PATTERN = re.compile(r"[a-z0-9']+", re.IGNORECASE)


class ConfigurationError(RuntimeError):
    """Raised when required configuration is missing or invalid."""


@dataclass
class WorkerConfig:
    index_path: Path
    meta_path: Path
    dimension: int
    encryption_key: bytes

    @property
    def lock_path(self) -> Path:
        return self.index_path.with_suffix(self.index_path.suffix + '.lock')


class VectorStore:
    """Encrypted FAISS-backed vector store."""

    def __init__(self, config: WorkerConfig) -> None:
        self.config = config
        self._index: faiss.Index | None = None
        self._meta: dict | None = None

    def add(self, text: str) -> int:
        if not text.strip():
            raise ValueError('Cannot embed empty text input.')

        index = self._load_index()
        meta = self._load_meta()

        vector = self._embed(text)
        vector = vector.reshape(1, -1)
        faiss.normalize_L2(vector)

        vector_id = int(meta['next_vector_id'])
        meta['next_vector_id'] = vector_id + 1

        ids = np.array([vector_id], dtype=np.int64)
        index.add_with_ids(vector, ids)

        self._save_index(index)
        self._save_meta(meta)

        return vector_id

    def remove(self, vector_ids: Iterable[int]) -> int:
        index = self._load_index()
        ids = np.fromiter((int(v) for v in vector_ids), dtype=np.int64)
        if ids.size == 0:
            return 0

        removed = index.remove_ids(ids)
        self._save_index(index)
        return int(removed)

    def search(self, query: str, top_k: int) -> List[dict]:
        index = self._load_index()
        if index.ntotal == 0:
            return []

        vector = self._embed(query)
        vector = vector.reshape(1, -1)
        faiss.normalize_L2(vector)

        distances, ids = index.search(vector, top_k)
        results: List[dict] = []
        for score, vector_id in zip(distances[0], ids[0]):
            if vector_id == -1:
                continue
            results.append({'vector_id': int(vector_id), 'score': float(score)})
        return results

    def export_plaintext(self) -> dict:
        index = self._load_index()
        meta = self._load_meta()
        serialized = faiss.serialize_index(index)
        return {
            'index': base64.b64encode(bytes(serialized)).decode('utf-8'),
            'meta': meta,
        }

    def import_plaintext(self, payload: dict) -> None:
        encoded_index = payload.get('index')
        meta = payload.get('meta')
        if not isinstance(encoded_index, str) or not isinstance(meta, dict):
            raise ConfigurationError('Invalid payload for restore command.')

        data = base64.b64decode(encoded_index)
        arr = np.frombuffer(data, dtype=np.uint8)
        index = faiss.deserialize_index(arr)

        if meta.get('dimension') != self.config.dimension:
            raise ConfigurationError('Restored metadata dimension mismatch.')

        self._save_index(index)
        self._save_meta(meta)

    def _embed(self, text: str) -> np.ndarray:
        dimension = self.config.dimension
        vector = np.zeros(dimension, dtype=np.float32)
        tokens = TOKEN_PATTERN.findall(text.lower())
        if not tokens:
            return vector

        for token in tokens:
            digest = hash_token(token)
            index = digest % dimension
            magnitude = 1.0 + (len(token) / 10.0)
            sign = -1.0 if (digest >> 31) & 1 else 1.0
            vector[index] += magnitude * sign

        norm = np.linalg.norm(vector)
        if norm > 0:
            vector /= norm
        return vector

    def _load_index(self) -> faiss.Index:
        if self._index is not None:
            return self._index

        lock = FileLock(str(self.config.lock_path))
        with lock:
            if self.config.index_path.exists():
                data = decrypt_bytes(self.config.index_path.read_bytes(), self.config.encryption_key)
                arr = np.frombuffer(data, dtype=np.uint8)
                self._index = faiss.deserialize_index(arr)
            else:
                base_index = faiss.IndexFlatIP(self.config.dimension)
                self._index = faiss.IndexIDMap2(base_index)
        return self._index

    def _save_index(self, index: faiss.Index) -> None:
        lock = FileLock(str(self.config.lock_path))
        with lock:
            serialized = faiss.serialize_index(index)
            encrypted = encrypt_bytes(bytes(serialized), self.config.encryption_key)
            self.config.index_path.parent.mkdir(parents=True, exist_ok=True)
            self.config.index_path.write_bytes(encrypted)
            self._index = index

    def _load_meta(self) -> dict:
        if self._meta is not None:
            return self._meta

        meta_default = {'dimension': self.config.dimension, 'next_vector_id': 1}
        if not self.config.meta_path.exists():
            self._meta = meta_default
            return self._meta

        lock = FileLock(str(self.config.lock_path))
        with lock:
            data = decrypt_bytes(self.config.meta_path.read_bytes(), self.config.encryption_key)
            meta = json.loads(data.decode('utf-8'))

        if meta.get('dimension') != self.config.dimension:
            raise ConfigurationError('Stored index dimension does not match requested dimension.')
        self._meta = meta
        return self._meta

    def _save_meta(self, meta: dict) -> None:
        lock = FileLock(str(self.config.lock_path))
        with lock:
            payload = json.dumps(meta, ensure_ascii=False).encode('utf-8')
            encrypted = encrypt_bytes(payload, self.config.encryption_key)
            self.config.meta_path.parent.mkdir(parents=True, exist_ok=True)
            self.config.meta_path.write_bytes(encrypted)
            self._meta = meta


def hash_token(token: str) -> int:
    digest = hashlib.sha1(token.encode('utf-8')).digest()
    value = 0
    for byte in digest[:8]:
        value = (value << 8) | int(byte)
    return value


def encrypt_bytes(data: bytes, key: bytes) -> bytes:
    aesgcm = AESGCM(key)
    nonce = os.urandom(12)
    ciphertext = aesgcm.encrypt(nonce, data, None)
    return nonce + ciphertext


def decrypt_bytes(data: bytes, key: bytes) -> bytes:
    if len(data) < 13:
        raise ValueError('Encrypted payload is too short.')
    nonce = data[:12]
    ciphertext = data[12:]
    aesgcm = AESGCM(key)
    return aesgcm.decrypt(nonce, ciphertext, None)


def load_key(value: str | None) -> bytes:
    if not value:
        raise ConfigurationError('VECTOR_INDEX_KEY is required for encryption.')
    candidate = value.strip()

    # Hex encoded
    try:
        return bytes.fromhex(candidate)
    except ValueError:
        pass

    # Base64 encoded
    try:
        return base64.b64decode(candidate)
    except base64.binascii.Error:
        pass

    if len(candidate) in {16, 24, 32}:
        return candidate.encode('utf-8')

    raise ConfigurationError('VECTOR_INDEX_KEY must be provided as hex, base64, or raw bytes (16/24/32 length).')


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description='SELF embedding worker')
    parser.add_argument('--index-path', required=True, help='Encrypted FAISS index path')
    parser.add_argument('--meta-path', required=True, help='Encrypted metadata path')
    parser.add_argument('--dimension', type=int, default=int(os.environ.get('VECTOR_EMBED_DIMENSION', '384')))
    subparsers = parser.add_subparsers(dest='command', required=True)

    subparsers.add_parser('add', help='Add a new embedding to the store')
    subparsers.add_parser('remove', help='Remove embeddings by vector id')
    subparsers.add_parser('search', help='Search the vector store for the closest matches')
    subparsers.add_parser('rotate', help='Export decrypted index/meta for key rotation')
    subparsers.add_parser('restore', help='Import decrypted index/meta with new key')

    return parser.parse_args()


def main() -> None:
    args = parse_args()
    key = load_key(os.environ.get('VECTOR_INDEX_KEY'))
    config = WorkerConfig(
        index_path=Path(args.index_path),
        meta_path=Path(args.meta_path),
        dimension=int(args.dimension),
        encryption_key=key,
    )
    store = VectorStore(config)

    if args.command == 'add':
        payload = json.load(sys.stdin)
        vector_id = store.add(payload['text'])
        json.dump({'vector_id': vector_id}, sys.stdout)
        sys.stdout.flush()
        return

    if args.command == 'remove':
        payload = json.load(sys.stdin)
        removed = store.remove(payload.get('vector_ids', []))
        json.dump({'removed': removed}, sys.stdout)
        sys.stdout.flush()
        return

    if args.command == 'search':
        payload = json.load(sys.stdin)
        top_k = int(payload.get('top_k', 5))
        if top_k < 1:
            top_k = 1
        results = store.search(payload['query'], top_k)
        json.dump({'results': results}, sys.stdout)
        sys.stdout.flush()
        return

    if args.command == 'rotate':
        payload = store.export_plaintext()
        sys.stdout.write(json.dumps(payload))
        sys.stdout.flush()
        return

    if args.command == 'restore':
        payload = json.load(sys.stdin)
        store.import_plaintext(payload)
        json.dump({'status': 'ok'}, sys.stdout)
        sys.stdout.flush()
        return

    raise SystemExit('Unsupported command requested.')


if __name__ == '__main__':
    try:
        main()
    except ConfigurationError as exc:
        sys.stderr.write(f'configuration_error: {exc}\n')
        sys.exit(2)
    except Exception as exc:  # pragma: no cover - surfaced to orchestrator
        sys.stderr.write(f'worker_error: {exc}\n')
        sys.exit(1)

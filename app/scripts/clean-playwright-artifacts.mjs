#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const defaultRelativeDir = 'storage/app/tmp/playwright';
const targetDir = path.resolve(projectRoot, process.env.PLAYWRIGHT_ARTIFACT_DIR ?? defaultRelativeDir);
const maxAgeHours = Number.parseInt(process.env.PLAYWRIGHT_ARTIFACT_MAX_AGE_HOURS ?? '168', 10);
const cutoff = Date.now() - Math.max(maxAgeHours, 1) * 60 * 60 * 1000;

function removeEntry(entryPath) {
  try {
    fs.rmSync(entryPath, { recursive: true, force: true });
    console.log(`removed: ${entryPath}`);
  } catch (error) {
    console.warn(`failed to remove ${entryPath}:`, error.message);
  }
}

function cleanDirectory(basePath) {
  if (!fs.existsSync(basePath)) {
    console.log(`playwright artifact directory not found: ${basePath}`);
    return;
  }

  const entries = fs.readdirSync(basePath, { withFileTypes: true });
  let removed = 0;

  for (const entry of entries) {
    const fullPath = path.join(basePath, entry.name);
    try {
      const stats = fs.statSync(fullPath);
      if (stats.isDirectory() || stats.isFile()) {
        if (stats.mtimeMs < cutoff) {
          removeEntry(fullPath);
          removed += 1;
        }
      }
    } catch (error) {
      console.warn(`skipping ${fullPath}:`, error.message);
    }
  }

  if (removed === 0) {
    console.log('no artifacts required removal');
  }
}

cleanDirectory(targetDir);

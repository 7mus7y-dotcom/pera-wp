#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
DIST_DIR="${PLUGIN_DIR}/assets/dist"
BUILD_STAMP_FILE="${DIST_DIR}/.build"

mkdir -p "${DIST_DIR}"
# Deploy/build stamp for cache-busting when deployments preserve asset mtimes.
date -u +"%Y-%m-%dT%H:%M:%SZ" > "${BUILD_STAMP_FILE}"

echo "Stamped ${BUILD_STAMP_FILE}"

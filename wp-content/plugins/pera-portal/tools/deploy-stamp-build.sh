#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

"${PLUGIN_DIR}/tools/stamp-dist-build.sh"

echo "Portal build stamp updated."

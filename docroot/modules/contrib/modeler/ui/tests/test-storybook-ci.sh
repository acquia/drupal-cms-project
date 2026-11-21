#!/bin/bash
# CI helper: build Storybook, serve it, run a11y tests, clean up.
#
# Usage:
#   ./test-storybook-ci.sh              # default: build + test
#   ./test-storybook-ci.sh --skip-build # reuse existing storybook-static/
#
# Requires: http-server, wait-on, @storybook/test-runner (all in devDeps)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
UI_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$UI_DIR"

PORT=6006
STATIC_DIR="tests/storybook-static"
SKIP_BUILD=0
SERVER_PID=""

for arg in "$@"; do
  case "$arg" in
    --skip-build) SKIP_BUILD=1 ;;
  esac
done

cleanup() {
  if [[ -n "$SERVER_PID" ]]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
}
trap cleanup EXIT

# --- Build ---
if [[ "$SKIP_BUILD" -eq 0 ]]; then
  echo "=== Building Storybook ==="
  npx storybook build -o "$STATIC_DIR" 2>&1
fi

if [[ ! -f "$STATIC_DIR/index.html" ]]; then
  echo "ERROR: $STATIC_DIR/index.html not found. Run without --skip-build first." >&2
  exit 1
fi

# --- Serve ---
echo "=== Starting HTTP server on port $PORT ==="
npx http-server "$STATIC_DIR" --port "$PORT" -s &
SERVER_PID=$!

echo "=== Waiting for server ==="
for i in $(seq 1 30); do
  if curl -sf "http://127.0.0.1:$PORT" >/dev/null 2>&1; then
    echo "Server is ready on port $PORT"
    break
  fi
  if [[ "$i" -eq 30 ]]; then
    echo "ERROR: Server failed to start within 30 seconds" >&2
    exit 1
  fi
  sleep 1
done

# --- Test ---
echo "=== Running test-storybook ==="
npx test-storybook --url "http://127.0.0.1:$PORT"

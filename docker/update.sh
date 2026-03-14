#!/bin/bash
# Silent background update script for Sal GTD
# Run via cron: */5 * * * * /path/to/salgtd/docker/update.sh >> /var/log/salgtd-update.log 2>&1
#
# Flow:
#   1. Check GitHub for new commits (skip if already building or build-ready)
#   2. If new commit: pull, build image silently (app stays running)
#   3. Write /data/build-ready with new commit hash
#   4. Wait for user to click "Apply Update" (writes /data/update-apply)
#   5. Swap containers with new image, clean up signals

set -e

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DATA_DIR="${SALGTD_DATA_DIR:-/var/lib/docker/volumes/salgtd_gtd-data/_data}"
COMPOSE_FILE="$REPO_DIR/docker-compose.yml"

cd "$REPO_DIR"

# --- Phase 1: Check if user wants to apply a ready build ---
if [ -f "$DATA_DIR/update-apply" ]; then
    echo "[$(date)] Applying update..."
    COMMIT_HASH=$(git rev-parse --short HEAD)
    COMMIT_HASH=$COMMIT_HASH docker compose -f "$COMPOSE_FILE" up -d --no-build
    rm -f "$DATA_DIR/update-apply"
    rm -f "$DATA_DIR/build-ready"
    echo "[$(date)] Update applied. Running commit: $COMMIT_HASH"
    exit 0
fi

# --- Phase 2: Skip if a build is already ready and waiting ---
if [ -f "$DATA_DIR/build-ready" ]; then
    exit 0
fi

# --- Phase 3: Check for new commits ---
CURRENT_COMMIT=$(git rev-parse HEAD)
git fetch origin main --quiet 2>/dev/null || exit 0
REMOTE_COMMIT=$(git rev-parse origin/main)

if [ "$CURRENT_COMMIT" = "$REMOTE_COMMIT" ]; then
    exit 0  # Up to date
fi

echo "[$(date)] New commit detected: ${REMOTE_COMMIT:0:7} (current: ${CURRENT_COMMIT:0:7})"

# --- Phase 4: Pull and build silently ---
git pull origin main --quiet

COMMIT_HASH=$(git rev-parse --short HEAD)
echo "[$(date)] Building image for $COMMIT_HASH..."

if COMMIT_HASH=$COMMIT_HASH docker compose -f "$COMPOSE_FILE" build --build-arg COMMIT_HASH="$COMMIT_HASH" 2>&1; then
    echo "$COMMIT_HASH" > "$DATA_DIR/build-ready"
    echo "[$(date)] Build ready. Waiting for user to apply."
else
    echo "[$(date)] Build failed!"
    exit 1
fi
